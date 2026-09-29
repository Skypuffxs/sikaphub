<?php
/**
 * scripts/verify_employer_builder.php — end-to-end verification of the employer
 * profile builder on GET/POST /build-profile through a running Apache
 * (Commit 1: C-29 close, C-32 employer half, C-44 slice).
 *
 * Covers: fresh employer lands on /build-profile (not /onboarding); the form
 * renders; a POST with a real PDF permit inserts exactly one employers row with
 * verified_status 'Pending' and flips the account to 'Active'; the dashboard
 * then shows the under-review banner; the publish gate still refuses POST
 * /post-job; re-edit pre-fills and never clears business_permit_file; a
 * cross-municipality barangay resolves to NULL; an unknown company_size becomes
 * NULL not a PDOException; a create with no permit is a form error with no row
 * and no orphan file; a DB failure after a successful upload (forced with an
 * invalid municipality FK) leaves no row AND no orphan; company name / address
 * / municipality are editable on re-edit and a Verified-employer name change is
 * audited; the seeker photo upload now goes through FileUpload::secureUpload();
 * GET /onboarding is 404; the uploaded permit is fetchable by an admin and 403
 * to another employer; and verify_documents / verify_feed / http_verify still
 * pass.
 *
 * It creates throwaway @demo-eb.sikaphub.local users, so — like
 * verify_documents.php — it refuses to run outside local dev.
 *
 *   php scripts/verify_employer_builder.php
 *     [env: SIKAP_BASE_URL=http://localhost/sikaphub  SIKAP_SESS_PATH=C:/xampp/tmp]
 */

define('BASE_PATH', dirname(__DIR__) . '/');

foreach (file(BASE_PATH . '.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (strpos(trim($line), '#') === 0) continue;
    [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
    $_ENV[trim($k)] = trim($v);
}

if (PHP_SAPI !== 'cli' || ($_ENV['APP_ENV'] ?? '') !== 'development') {
    fwrite(STDERR, "REFUSING: verify_employer_builder.php runs only from the CLI with APP_ENV=development.\n");
    exit(1);
}

require BASE_PATH . 'config/Database.php';
$db = Database::getInstance()->getConnection();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

const UA        = 'sikaphub-verify/1.0';
const EB_DOMAIN = '@demo-eb.sikaphub.local';   // isolated from seed_demo's @demo.sikaphub.local
$BASEURL = rtrim(getenv('SIKAP_BASE_URL') ?: 'http://localhost/sikaphub', '/');
$SAVE    = rtrim(getenv('SIKAP_SESS_PATH') ?: (ini_get('session.save_path') ?: 'C:/xampp/tmp'), '/\\');

$pass = 0; $fail = 0;
function check($l, $c) { global $pass, $fail; echo ($c ? "  PASS  " : "  FAIL  ") . $l . "\n"; $c ? $pass++ : $fail++; }

// ---------------------------------------------------------------- fixtures / cleanup
$PDF = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
$permitSrc = sys_get_temp_dir() . '/eb_permit_' . bin2hex(random_bytes(4)) . '.pdf';

$sessions = [];
$createdPermits = [];   // basenames written to storage/documents/ by our POSTs
$createdPhotos  = [];   // basenames written to storage/uploads/profile_photos/

function docCount()   { return count(glob(BASE_PATH . 'storage/documents/*')); }
function photoCount() { return count(glob(BASE_PATH . 'storage/uploads/profile_photos/*')); }

function cleanup()
{
    global $db, $SAVE, $sessions, $permitSrc, $createdPermits, $createdPhotos;
    // collect the files our POSTs stored, then drop the throwaway users
    // (cascades employers / job_seekers / job_match_scores)
    foreach ($db->query("SELECT business_permit_file FROM employers e JOIN users u ON u.user_id = e.user_id WHERE u.email LIKE '%" . EB_DOMAIN . "'")->fetchAll(PDO::FETCH_COLUMN) as $f) {
        $createdPermits[] = $f;
    }
    foreach ($db->query("SELECT js.profile_photo FROM job_seekers js JOIN users u ON u.user_id = js.user_id WHERE u.email LIKE '%" . EB_DOMAIN . "' AND js.profile_photo IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $f) {
        $createdPhotos[] = $f;
    }
    foreach (array_unique(array_filter($createdPermits)) as $f) {
        if (preg_match('/^[0-9a-f]{32}\.(pdf|png|jpe?g)$/', $f)) {
            $p = BASE_PATH . 'storage/documents/' . $f;
            if (is_file($p)) unlink($p);
        }
    }
    foreach (array_unique(array_filter($createdPhotos)) as $f) {
        if (preg_match('/^[0-9a-f]{32}\.(png|jpe?g|webp)$/', $f)) {
            $p = BASE_PATH . 'storage/uploads/profile_photos/' . $f;
            if (is_file($p)) unlink($p);
        }
    }
    $db->exec("DELETE FROM users WHERE email LIKE '%" . EB_DOMAIN . "'");
    foreach ($sessions as $sid) { @unlink("$SAVE/sess_$sid"); }
    if (is_file($permitSrc)) unlink($permitSrc);
}
register_shutdown_function('cleanup');
cleanup();   // clear any leftovers from a previous aborted run
$sessions = [];
file_put_contents($permitSrc, $PDF);   // the upload fixture — created after cleanup()

// ---------------------------------------------------------------- helpers
function mkUser($db, $role, $status)
{
    $email = $role . '.' . bin2hex(random_bytes(5)) . EB_DOMAIN;
    $db->prepare("INSERT INTO users (email, email_verified_at, role, account_status, last_login_at)
                  VALUES (:e, NOW(), :r, :s, NOW())")
       ->execute([':e' => $email, ':r' => $role, ':s' => $status]);
    return (int) $db->lastInsertId();
}

function sess($userId, $role, $status)
{
    global $SAVE, $sessions;
    $sid = bin2hex(random_bytes(16));
    $data = 'user_id|i:' . (int) $userId . ';'
        . 'ua_hash|s:64:"' . hash('sha256', UA) . '";'
        . 'last_activity|i:' . time() . ';'
        . 'role|s:' . strlen($role) . ':"' . $role . '";'
        . 'account_status|s:' . strlen($status) . ':"' . $status . '";'
        . 'csrf_token|s:8:"testtok0";';
    file_put_contents("$SAVE/sess_$sid", $data);
    $sessions[] = $sid;
    return $sid;
}

/** @return array [code, body, headers[]] */
function http($method, $path, $sid = null, array $post = null, array $files = [])
{
    global $BASEURL;
    $respHeaders = [];
    $ch = curl_init($BASEURL . $path);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT      => UA,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HEADERFUNCTION => function ($c, $line) use (&$respHeaders) {
            $p = strpos($line, ':');
            if ($p !== false) {
                $respHeaders[strtolower(trim(substr($line, 0, $p)))] = trim(substr($line, $p + 1));
            }
            return strlen($line);
        },
    ];
    $headers = ['Expect:'];
    if ($sid !== null) $headers[] = 'Cookie: PHPSESSID=' . $sid;
    $opts[CURLOPT_HTTPHEADER] = $headers;
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        $fields = $post ?? [];
        foreach ($files as $name => $spec) {
            // string -> a PDF; [path, mime, filename] -> anything else
            [$path, $mime, $fname] = is_array($spec) ? $spec : [$spec, 'application/pdf', 'permit.pdf'];
            $fields[$name] = new CURLFile(str_replace('\\', '/', $path), $mime, $fname);
        }
        $opts[CURLOPT_POSTFIELDS] = $fields;
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    if ($body === false) { fwrite(STDERR, "curl failed for $path: " . curl_error($ch) . "\n"); exit(1); }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $body, $respHeaders];
}

function locationOf($headers)
{
    return $headers['location'] ?? '';
}

$baseForm = [
    'csrf_token'          => 'testtok0',
    'company_name'        => 'Verify EB Corp',
    'contact_person'      => 'HR Lead',
    'company_email'       => 'hr@verify-eb.example',
    'company_phone'       => '09170000000',
    'street_name'         => '123 Test Street',
    'industry'            => 'Information Technology',
    'company_size'        => '11-50 Employees',
    'company_description' => 'A demonstration employer for the builder harness.',
    'company_website'     => 'https://verify-eb.example',
];

// municipalities: one with no barangays (barangay always resolves NULL there),
// one that has barangays (source of a deliberately-mismatched barangay_id).
$noBrgyMuni  = (int) $db->query("SELECT m.municipality_id FROM lib_municipalities m
    LEFT JOIN lib_barangays b ON b.municipality_id = m.municipality_id
    WHERE b.barangay_id IS NULL ORDER BY m.municipality_id LIMIT 1")->fetchColumn();
$brgyRow = $db->query("SELECT municipality_id, barangay_id FROM lib_barangays ORDER BY barangay_id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$otherMuniBarangay = (int) $brgyRow['barangay_id'];

// ================================================================ 1. fresh employer
echo "\n=== 1. fresh employer: role picker -> /build-profile -> create ===\n";
$u1 = mkUser($db, 'employer', 'Pending');
$s1 = sess($u1, 'employer', 'Pending');

[$c, $b, $h] = http('GET', '/build-profile', $s1);
check("GET /build-profile renders (HTTP 200)", $c === 200);
check("it is the employer builder, not a redirect to /onboarding",
    strpos($b, 'Build Your Company Profile') !== false && strpos($b, 'name="business_permit"') !== false);

[$c] = http('GET', '/onboarding', $s1);
check("GET /onboarding -> 404", $c === 404);

$before = docCount();
[$c, $b, $h] = http('POST', '/build-profile', $s1,
    $baseForm + ['municipality_id' => $noBrgyMuni],
    ['business_permit' => $permitSrc]);
check("POST create -> 302", $c === 302);
check("redirects to the employer dashboard", strpos(locationOf($h), '/employer/dashboard') !== false);

$row = $db->query("SELECT * FROM employers WHERE user_id = $u1")->fetch(PDO::FETCH_ASSOC);
check("exactly one employers row created", $row !== false
    && (int) $db->query("SELECT COUNT(*) FROM employers WHERE user_id = $u1")->fetchColumn() === 1);
check("verified_status = 'Pending'", ($row['verified_status'] ?? '') === 'Pending');
check("users.account_status flipped to 'Active'",
    $db->query("SELECT account_status FROM users WHERE user_id = $u1")->fetchColumn() === 'Active');
check("company_size mapped '11-50 Employees' -> enum '11-50'", ($row['company_size'] ?? '') === '11-50');
check("business_permit_file stored under a randomized name", (bool) preg_match('/^[0-9a-f]{32}\.pdf$/', $row['business_permit_file'] ?? ''));
check("permit landed in storage/documents/ (one new file)", docCount() === $before + 1);
$createdPermits[] = $row['business_permit_file'];
$u1Permit = $row['business_permit_file'];

// dashboard banner + publish gate
$s1a = sess($u1, 'employer', 'Active');
[$c, $b] = http('GET', '/employer/dashboard', $s1a);
check("employer dashboard renders (HTTP 200)", $c === 200);
check("shows the 'Account Under Review' banner", strpos($b, 'Account Under Review') !== false);

[$c, $b, $h] = http('POST', '/post-job', $s1a, ['csrf_token' => 'testtok0', 'job_title' => 'x']);
check("POST /post-job is still refused by the verification gate",
    $c === 302 && strpos(locationOf($h), 'pending_verification') !== false);

// ================================================================ 2. re-edit
echo "\n=== 2. re-edit: pre-fills, keeps the permit, name/address/municipality editable ===\n";
[$c, $b] = http('GET', '/build-profile', $s1a);
check("GET /build-profile (edit) renders (HTTP 200)", $c === 200);
check("pre-fills the company name", strpos($b, 'value="Verify EB Corp"') !== false);
check("company name field is editable on re-edit (not readonly)",
    (bool) preg_match('/name="company_name"[^>]*value="Verify EB Corp"[^>]*>/', $b)
    && strpos($b, 'name="company_name" value="Verify EB Corp" readonly') === false);
check("municipality is a <select> on re-edit", strpos($b, 'name="municipality_id" id="municipality_id"') !== false);
check("shows 'permit on file', not a new upload field",
    strpos($b, 'on file') !== false && strpos($b, 'name="business_permit"') === false);

$otherMuni = (int) $db->query("SELECT m.municipality_id FROM lib_municipalities m
    LEFT JOIN lib_barangays b ON b.municipality_id = m.municipality_id
    WHERE b.barangay_id IS NULL AND m.municipality_id <> $noBrgyMuni ORDER BY m.municipality_id LIMIT 1")->fetchColumn();
[$c, $b, $h] = http('POST', '/build-profile', $s1a, [
    'csrf_token'      => 'testtok0',
    'company_name'    => 'Verify EB Corp (Renamed)',
    'contact_person'  => 'New HR Person',
    'company_email'   => 'hr@verify-eb.example',
    'company_phone'   => '09171112222',
    'street_name'     => '456 New Avenue',
    'municipality_id' => $otherMuni,
    'industry'        => 'Healthcare',
    'company_size'    => '51-200 Employees',
    'company_description' => 'Edited description.',
    'company_website'    => 'https://verify-eb.example',
]);
check("POST edit -> 302 to dashboard", $c === 302 && strpos(locationOf($h), '/employer/dashboard') !== false);
$row = $db->query("SELECT * FROM employers WHERE user_id = $u1")->fetch(PDO::FETCH_ASSOC);
check("business_permit_file unchanged by the edit", ($row['business_permit_file'] ?? '') === $u1Permit);
check("company_phone updated", ($row['company_phone'] ?? '') === '09171112222');
check("company_name updated (editable on re-edit)", ($row['company_name'] ?? '') === 'Verify EB Corp (Renamed)');
check("street_name updated", ($row['street_name'] ?? '') === '456 New Avenue');
check("municipality_id updated", (int) ($row['municipality_id'] ?? 0) === $otherMuni);
check("exactly one employers row after edit", (int) $db->query("SELECT COUNT(*) FROM employers WHERE user_id = $u1")->fetchColumn() === 1);
$auditBefore = (int) $db->query("SELECT COUNT(*) FROM audit_logs WHERE entity_type='employer' AND entity_id=" . (int) $row['employer_id'])->fetchColumn();
check("no audit row for the name change while NOT Verified", $auditBefore === 0);

// 2b — same edit path, but the employer is Verified: the name change is audited
echo "\n=== 2b. Verified-employer company-name change is audited ===\n";
$db->prepare("UPDATE employers SET verified_status='Verified' WHERE user_id = $u1")->execute();
[$c, $b, $h] = http('POST', '/build-profile', $s1a, [
    'csrf_token'      => 'testtok0',
    'company_name'    => 'Verify EB Corp (Verified Name)',
    'contact_person'  => 'New HR Person',
    'company_email'   => 'hr@verify-eb.example',
    'company_phone'   => '09171112222',
    'street_name'     => '456 New Avenue',
    'municipality_id' => $otherMuni,
]);
check("POST edit -> 302", $c === 302);
$row = $db->query("SELECT * FROM employers WHERE user_id = $u1")->fetch(PDO::FETCH_ASSOC);
check("company_name updated to the new value", ($row['company_name'] ?? '') === 'Verify EB Corp (Verified Name)');
check("verified_status is NOT changed by the model (Q-22 policy pending)", ($row['verified_status'] ?? '') === 'Verified');
$audit = $db->query("SELECT * FROM audit_logs WHERE entity_type='employer' AND entity_id=" . (int) $row['employer_id']
    . " ORDER BY log_id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
check("an audit_logs row was written for the Verified name change",
    $audit !== false && stripos($audit['description'], 'company name') !== false);
check("the audit row is attributed to the employer's user", (int) ($audit['user_id'] ?? 0) === $u1);
// leave u1 Pending again so step 8's 'different employer' check is unaffected
$db->prepare("UPDATE employers SET verified_status='Pending' WHERE user_id = $u1")->execute();

// ================================================================ 3. barangay mismatch
echo "\n=== 3. barangay not in the chosen municipality -> NULL ===\n";
$u2 = mkUser($db, 'employer', 'Pending');
$s2 = sess($u2, 'employer', 'Pending');
[$c, $b, $h] = http('POST', '/build-profile', $s2,
    $baseForm + ['municipality_id' => $noBrgyMuni, 'barangay_id' => $otherMuniBarangay],
    ['business_permit' => $permitSrc]);
check("POST create -> 302", $c === 302);
$row = $db->query("SELECT * FROM employers WHERE user_id = $u2")->fetch(PDO::FETCH_ASSOC);
$createdPermits[] = $row['business_permit_file'] ?? null;
check("cross-municipality barangay_id stored as NULL", $row !== false && $row['barangay_id'] === null);

// ================================================================ 4. unknown company_size
echo "\n=== 4. unknown company_size -> NULL, not a PDOException ===\n";
$u3 = mkUser($db, 'employer', 'Pending');
$s3 = sess($u3, 'employer', 'Pending');
[$c, $b, $h] = http('POST', '/build-profile', $s3,
    array_merge($baseForm, ['municipality_id' => $noBrgyMuni, 'company_size' => 'Galaxy Class']),
    ['business_permit' => $permitSrc]);
check("POST create -> 302 (no 500)", $c === 302);
$row = $db->query("SELECT * FROM employers WHERE user_id = $u3")->fetch(PDO::FETCH_ASSOC);
$createdPermits[] = $row['business_permit_file'] ?? null;
check("employers row created", $row !== false);
check("company_size stored as NULL", $row !== false && $row['company_size'] === null);

// ================================================================ 5. no permit on create
echo "\n=== 5. create with no permit -> form error, no row, no orphan ===\n";
$u4 = mkUser($db, 'employer', 'Pending');
$s4 = sess($u4, 'employer', 'Pending');
$before = docCount();
[$c, $b] = http('POST', '/build-profile', $s4, $baseForm + ['municipality_id' => $noBrgyMuni]);
check("HTTP 200 (form re-rendered, not a redirect)", $c === 200);
check("shows a permit-required validation message", stripos($b, 'business permit') !== false);
check("no employers row created", (int) $db->query("SELECT COUNT(*) FROM employers WHERE user_id = $u4")->fetchColumn() === 0);
check("no orphan file in storage/documents/", docCount() === $before);

// ================================================================ 6. DB failure after upload
echo "\n=== 6. DB failure after a successful upload -> no row AND no orphan ===\n";
// Force the INSERT to fail INSIDE the transaction, after the permit upload has
// already succeeded, with a municipality_id that violates the employers FK.
// No schema is touched.
$u5 = mkUser($db, 'employer', 'Pending');
$s5 = sess($u5, 'employer', 'Pending');
$badMuni = 1 + (int) $db->query("SELECT MAX(municipality_id) FROM lib_municipalities")->fetchColumn();
$before = docCount();
[$c, $b] = http('POST', '/build-profile', $s5,
    array_merge($baseForm, ['municipality_id' => $badMuni]),
    ['business_permit' => $permitSrc]);
check("request fails with HTTP 500", $c === 500);
check("no raw SQL / FK detail leaked", stripos($b, 'SQLSTATE') === false && stripos($b, 'foreign key') === false);
check("no employers row for that user", (int) $db->query("SELECT COUNT(*) FROM employers WHERE user_id = $u5")->fetchColumn() === 0);
check("uploaded permit was unlinked on rollback (no orphan)", docCount() === $before);

// ================================================================ 7. /onboarding is gone
echo "\n=== 7. /onboarding purged ===\n";
[$c] = http('GET', '/onboarding');
check("GET /onboarding -> 404 (no session)", $c === 404);
[$c] = http('POST', '/onboarding', null, ['csrf_token' => 'testtok0']);
check("POST /onboarding -> 404", $c === 404);
check("OnboardingController.php deleted", !is_file(BASE_PATH . 'app/controllers/OnboardingController.php'));
check("Onboarding.php model deleted", !is_file(BASE_PATH . 'app/models/Onboarding.php'));
check("auth/onboarding_employer.php deleted", !is_file(BASE_PATH . 'app/views/auth/onboarding_employer.php'));
check("no 'onboarding' route left in public/index.php",
    strpos(file_get_contents(BASE_PATH . 'public/index.php'), "'/onboarding'") === false);

// ================================================================ 8. permit through the Commit A gateway
echo "\n=== 8. uploaded permit via /admin/view-document ===\n";
$adminId = (int) $db->query("SELECT user_id FROM users WHERE role = 'admin' ORDER BY user_id LIMIT 1")->fetchColumn();
$sAdmin  = sess($adminId, 'admin', 'Active');
[$c, $b] = http('GET', '/admin/view-document?file=' . rawurlencode($u1Permit), $sAdmin);
check("admin fetches the new permit: 200 + PDF body", $c === 200 && strncmp($b, '%PDF', 4) === 0);

$s2a = sess($u2, 'employer', 'Active');
[$c, $b] = http('GET', '/admin/view-document?file=' . rawurlencode($u1Permit), $s2a);
check("a different employer fetching that permit: 403", $c === 403 && strncmp($b, '%PDF', 4) !== 0);

// ================================================================ 10. seeker photo upload
echo "\n=== 10. seeker photo now routes through FileUpload::secureUpload() ===\n";
$sk  = mkUser($db, 'jobseeker', 'Pending');
$skS = sess($sk, 'jobseeker', 'Pending');
$anyMuni = (int) $db->query("SELECT municipality_id FROM lib_municipalities ORDER BY municipality_id LIMIT 1")->fetchColumn();
$seekerForm = [
    'csrf_token' => 'testtok0', 'first_name' => 'Photo', 'last_name' => 'Tester',
    'home_municipality_id' => $anyMuni, 'profile_visibility' => 'Public',
    'desired_job_type' => 'Full-time', 'preferred_work_setup' => 'On-site',
    'skills' => '', 'preferred_municipality_ids' => [$anyMuni],
];

// A genuine 1x1 PNG, uploaded with a misleading .jpg name.
$pngSrc = sys_get_temp_dir() . '/eb_photo_' . bin2hex(random_bytes(4)) . '.bin';
file_put_contents($pngSrc, base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
));
[$c, $b, $h] = http('POST', '/build-profile', $skS, $seekerForm,
    ['profile_photo' => [$pngSrc, 'image/png', 'headshot.jpg']]);
check("valid image accepted -> 302 to /dashboard", $c === 302 && strpos(locationOf($h), '/dashboard') !== false);
$photo = (string) $db->query("SELECT profile_photo FROM job_seekers WHERE user_id = $sk")->fetchColumn();
$createdPhotos[] = $photo;
check("stored under a MIME-derived .png extension, not the .jpg it was named",
    (bool) preg_match('/^[0-9a-f]{32}\.png$/', $photo));
check("the photo file exists on disk",
    $photo !== '' && is_file(BASE_PATH . 'storage/uploads/profile_photos/' . $photo));

// A non-image with an image name -> rejected by magic-byte validation.
$skS2 = sess($sk, 'jobseeker', 'Active');
$badImg = sys_get_temp_dir() . '/eb_notimg_' . bin2hex(random_bytes(4)) . '.txt';
file_put_contents($badImg, "this is plain text, definitely not an image\n");
$pbefore = photoCount();
[$c, $b] = http('POST', '/build-profile', $skS2, $seekerForm,
    ['profile_photo' => [$badImg, 'image/jpeg', 'evil.jpg']]);
check("non-image named evil.jpg -> HTTP 400", $c === 400);
check("profile_photo unchanged in the DB",
    (string) $db->query("SELECT profile_photo FROM job_seekers WHERE user_id = $sk")->fetchColumn() === $photo);
check("no new file landed in storage/uploads/profile_photos/", photoCount() === $pbefore);
@unlink($pngSrc);
@unlink($badImg);

// ================================================================ 11. regression
echo "\n=== 11. regression: prior harnesses still green ===\n";
$php = PHP_BINARY;
foreach (['verify_documents.php', 'verify_feed.php', 'http_verify.php'] as $script) {
    $out = [];
    $rc = 0;
    exec(escapeshellarg($php) . ' ' . escapeshellarg(BASE_PATH . 'scripts/' . $script) . ' 2>&1', $out, $rc);
    $tail = trim(implode("\n", array_slice($out, -1)));
    check("$script exits 0 ($tail)", $rc === 0);
}

echo "\n----------------------------------------\n";
echo "PASS: $pass   FAIL: $fail\n";
exit($fail ? 1 : 0);
