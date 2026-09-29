<?php
/**
 * scripts/verify_review_candidate.php — end-to-end verification of the employer
 * "Review Candidate" page (UC-13, GET/POST /employer/review-candidate), through
 * a running Apache.
 *
 * Fixes the HTTP 500: Employer::getApplicationDetails() selected js.resume_file
 * and js.street_address, neither of which exists in schema v2 (the column is
 * street_name; resumes live in resume_uploads). The uncaught PDOException made
 * the page a 500 for every valid application. Registered as C-51.
 *
 * Covers:
 *   1. the Guimba employer opens Maria's application (bookkeeper vacancy) ->
 *      HTTP 200, not 500; her name, municipality and the FROZEN
 *      applications.ai_match_score all render;
 *   2. with no resume_uploads row for the seeker, the résumé section shows the
 *      honest empty state and emits no document link;
 *   3. the Cabanatuan employer requesting that same app_id is refused by the
 *      ownership JOIN — 302 to the dashboard, none of Maria's details in the body;
 *   4. Maria's profile-photo link resolves through the hardened document gateway
 *      — 200 for the Guimba employer, 403 for the Cabanatuan employer;
 *   5. a resume_uploads row makes the résumé section render a working gateway
 *      link; removing it returns the empty state (the row is seeded and cleaned
 *      by this harness, not by hand, and is restored byte-for-byte so
 *      verify_documents stays green);
 *   6. a status update from this page changes the status, is scoped to the
 *      owning employer, and a cross-employer attempt changes nothing;
 *   7. the score shown is applications.ai_match_score frozen at application time,
 *      NOT a live job_match_scores read — proven on a throwaway
 *      employer/seeker/application whose two scores deliberately disagree
 *      (the live Maria fixture row is never mutated: six other harnesses
 *      depend on it);
 *   8. regression: verify_documents / verify_feed / http_verify /
 *      verify_job_posting / verify_employer_builder / verify_admin_verification
 *      still green.
 *
 * Throwaway rows use the @demo-rc.sikaphub.local domain and a '[RC-verify]'
 * company/job tag and are self-cleaned. Like the other harnesses it refuses to
 * run outside local dev.
 *
 *   php scripts/seed_demo.php --scores
 *   php scripts/verify_review_candidate.php
 *     [env: SIKAP_BASE_URL=http://localhost/sikaphub  SIKAP_SESS_PATH=C:/xampp/tmp]
 */

define('BASE_PATH', dirname(__DIR__) . '/');

foreach (file(BASE_PATH . '.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (strpos(trim($line), '#') === 0) continue;
    [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
    $_ENV[trim($k)] = trim($v);
}

if (PHP_SAPI !== 'cli' || ($_ENV['APP_ENV'] ?? '') !== 'development') {
    fwrite(STDERR, "REFUSING: verify_review_candidate.php runs only from the CLI with APP_ENV=development.\n");
    exit(1);
}

require BASE_PATH . 'config/Database.php';
$db = Database::getInstance()->getConnection();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

const UA        = 'sikaphub-verify/1.0';
const RC_DOMAIN = '@demo-rc.sikaphub.local';
const RC_TAG    = '[RC-verify]';
$BASEURL = rtrim(getenv('SIKAP_BASE_URL') ?: 'http://localhost/sikaphub', '/');
$SAVE    = rtrim(getenv('SIKAP_SESS_PATH') ?: (ini_get('session.save_path') ?: 'C:/xampp/tmp'), '/\\');

$manifestPath = BASE_PATH . 'storage/demo_manifest.json';
if (!is_file($manifestPath)) {
    fwrite(STDERR, "no storage/demo_manifest.json — run: php scripts/seed_demo.php --scores\n");
    exit(1);
}
$man = json_decode(file_get_contents($manifestPath), true);

$pass = 0; $fail = 0;
function check($l, $c) { global $pass, $fail; echo ($c ? "  PASS  " : "  FAIL  ") . $l . "\n"; $c ? $pass++ : $fail++; }

// ---------------------------------------------------------------- teardown
$sessions = [];
function cleanup()
{
    global $db, $SAVE, $sessions;
    try {
        $db->exec("SET FOREIGN_KEY_CHECKS=0");
        $ids = $db->query("SELECT employer_id FROM employers WHERE company_name LIKE '" . str_replace("'", "''", RC_TAG) . "%'")
            ->fetchAll(PDO::FETCH_COLUMN);
        if ($ids) {
            $in = implode(',', array_map('intval', $ids));
            $db->exec("DELETE FROM job_match_scores WHERE job_id IN (SELECT job_id FROM job_postings WHERE employer_id IN ($in))");
            $db->exec("DELETE FROM applications      WHERE job_id IN (SELECT job_id FROM job_postings WHERE employer_id IN ($in))");
            $db->exec("DELETE FROM job_postings WHERE employer_id IN ($in)");
            $db->exec("DELETE FROM employers    WHERE employer_id IN ($in)");
        }
        $sids = $db->query("SELECT jobseeker_id FROM job_seekers js JOIN users u ON u.user_id = js.user_id
                            WHERE u.email LIKE '%" . RC_DOMAIN . "'")->fetchAll(PDO::FETCH_COLUMN);
        if ($sids) {
            $in = implode(',', array_map('intval', $sids));
            $db->exec("DELETE FROM job_match_scores WHERE jobseeker_id IN ($in)");
            $db->exec("DELETE FROM applications      WHERE jobseeker_id IN ($in)");
            $db->exec("DELETE FROM resume_uploads    WHERE jobseeker_id IN ($in)");
            $db->exec("DELETE FROM job_seekers       WHERE jobseeker_id IN ($in)");
        }
        $db->exec("DELETE FROM users WHERE email LIKE '%" . RC_DOMAIN . "'");
    } finally {
        $db->exec("SET FOREIGN_KEY_CHECKS=1");
    }
    foreach ($sessions as $sid) { @unlink("$SAVE/sess_$sid"); }
}
register_shutdown_function('cleanup');
cleanup();          // clear leftovers from any aborted run
$sessions = [];

// ---------------------------------------------------------------- helpers
function sess($userId, $role, $status = 'Active')
{
    global $SAVE, $sessions;
    $sid = bin2hex(random_bytes(16));
    file_put_contents("$SAVE/sess_$sid",
        'user_id|i:' . (int) $userId . ';'
        . 'ua_hash|s:64:"' . hash('sha256', UA) . '";'
        . 'last_activity|i:' . time() . ';'
        . 'role|s:' . strlen($role) . ':"' . $role . '";'
        . 'account_status|s:' . strlen($status) . ':"' . $status . '";'
        . 'csrf_token|s:8:"testtok0";');
    $sessions[] = $sid;
    return $sid;
}

/** @return array [code, body, headers[]] */
function http($method, $path, $sid = null, array $post = null)
{
    global $BASEURL;
    $hdrs = [];
    $ch = curl_init($BASEURL . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, UA);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER,
        array_merge(['Expect:'], $sid !== null ? ['Cookie: PHPSESSID=' . $sid] : []));
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($c, $line) use (&$hdrs) {
        $p = strpos($line, ':');
        if ($p !== false) $hdrs[strtolower(trim(substr($line, 0, $p)))] = trim(substr($line, $p + 1));
        return strlen($line);
    });
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post ?? []));
    }
    $body = curl_exec($ch);
    if ($body === false) { fwrite(STDERR, "curl failed for $path: " . curl_error($ch) . "\n(is Apache up?)\n"); exit(1); }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $body, $hdrs];
}
$is_pdf = fn($b) => strncmp((string) $b, '%PDF', 4) === 0;
$is_png = fn($b) => strncmp((string) $b, "\x89PNG", 4) === 0;

// ---------------------------------------------------------------- fixture handles
$guimbaU = (int) $man['employers']['guimba_agricorp']['user_id'];
$cabU    = (int) $man['employers']['cabanatuan_tech']['user_id'];
$mariaJs = (int) $man['seekers']['maria']['jobseeker_id'];
$mariaApp = (int) $man['applications']['maria_bookkeeper']['application_id'];
$mariaScoreFrozen = $man['applications']['maria_bookkeeper']['ai_match_score']; // '0.6000'
$frozenPct = (string) round((float) $mariaScoreFrozen * 100);                   // '60'
$photoFile  = $man['documents']['maria_photo']['file'];
$resumeFile = $man['documents']['maria_resume']['file'];

$docUrl = fn($f) => '/admin/view-document?file=' . rawurlencode($f);
$rcUrl  = fn($id) => '/employer/review-candidate?app_id=' . (int) $id;

// Stash Maria's real resume_uploads row(s) and guarantee restoration even on a
// crash — six other harnesses resolve the document gateway against this row.
$mariaResumeRows = $db->query(
    "SELECT user_id, jobseeker_id, stored_filename, original_filename, file_hash,
            mime_type, file_size_bytes, parse_status, parsed_payload, parse_error, parser_version
     FROM resume_uploads WHERE jobseeker_id = $mariaJs"
)->fetchAll(PDO::FETCH_ASSOC);

$restoreMariaResume = function () use ($db, $mariaJs, $mariaResumeRows) {
    $have = (int) $db->query("SELECT COUNT(*) FROM resume_uploads WHERE jobseeker_id = $mariaJs")->fetchColumn();
    if ($have > 0 || !$mariaResumeRows) return;
    $ins = $db->prepare(
        "INSERT INTO resume_uploads
            (user_id, jobseeker_id, stored_filename, original_filename, file_hash,
             mime_type, file_size_bytes, parse_status, parsed_payload, parse_error, parser_version)
         VALUES (:user_id, :jobseeker_id, :stored_filename, :original_filename, :file_hash,
             :mime_type, :file_size_bytes, :parse_status, :parsed_payload, :parse_error, :parser_version)"
    );
    foreach ($mariaResumeRows as $r) { $ins->execute($r); }
};
register_shutdown_function($restoreMariaResume);

$sGuimba = sess($guimbaU, 'employer');
$sCab    = sess($cabU, 'employer');

// ================================================================ 1. page loads
echo "\n=== 1. Guimba employer opens Maria's application ===\n";
[$c, $b] = http('GET', $rcUrl($mariaApp), $sGuimba);
check("HTTP 200 (was 500 on the resume_file / street_address mismatch)", $c === 200);
check("renders the seeker's name", strpos($b, 'Maria') !== false && strpos($b, 'Santos') !== false);
check("renders her home municipality (Guimba)", strpos($b, 'Guimba') !== false);
check("shows the frozen ai_match_score ({$frozenPct}%)", strpos($b, ">{$frozenPct}%<") !== false);
check("no PHP error / stack trace leaked", stripos($b, 'Fatal error') === false && stripos($b, 'SQLSTATE') === false);

// ================================================================ 5a. resume link present
echo "\n=== 5a. résumé section with a resume_uploads row present ===\n";
check("a resume_uploads row exists for Maria (seed fixture)", count($mariaResumeRows) > 0);
check("résumé link points at the gateway with the stored filename",
    strpos($b, 'view-document?file=' . htmlspecialchars($resumeFile)) !== false);
check("résumé link text rendered", strpos($b, 'View / Download') !== false);
[$rc, $rb] = http('GET', $docUrl($resumeFile), $sGuimba);
check("that gateway link returns 200 + PDF for the Guimba employer", $rc === 200 && $is_pdf($rb));

// ================================================================ 2. empty state
echo "\n=== 2. résumé empty state when the seeker has no upload ===\n";
$db->exec("DELETE FROM resume_uploads WHERE jobseeker_id = $mariaJs");
[$c, $b] = http('GET', $rcUrl($mariaApp), $sGuimba);
check("HTTP 200", $c === 200);
check("honest empty state shown", strpos($b, 'No résumé on file') !== false);
check("no roadmap / release claim in the copy", stripos($b, 'release') === false && stripos($b, 'not yet available') === false);
check("no résumé download link emitted", strpos($b, 'View / Download') === false);
check("still renders the rest of the profile (name)", strpos($b, 'Santos') !== false);

// ================================================================ 5b. restore + link returns
echo "\n=== 5b. restoring the row brings the link back ===\n";
$restoreMariaResume();
check("resume_uploads row restored with the same stored filename",
    $db->query("SELECT stored_filename FROM resume_uploads WHERE jobseeker_id = $mariaJs")->fetchColumn() === $resumeFile);
[$c, $b] = http('GET', $rcUrl($mariaApp), $sGuimba);
check("résumé link is back", strpos($b, 'view-document?file=' . htmlspecialchars($resumeFile)) !== false);

// ================================================================ 3. cross-employer
echo "\n=== 3. Cabanatuan employer requests Maria's application ===\n";
[$c, $b, $h] = http('GET', $rcUrl($mariaApp), $sCab);
check("refused — 302 to the dashboard", $c === 302 && strpos($h['location'] ?? '', 'access_denied_or_not_found') !== false);
check("no seeker details leaked in the body", strpos($b, 'Santos') === false && strpos($b, ">{$frozenPct}%<") === false);

// ================================================================ 4. photo via gateway
echo "\n=== 4. Maria's profile photo through the document gateway ===\n";
[$c, $bd] = http('GET', $docUrl($photoFile), $sGuimba);
check("Guimba employer -> 200 + PNG", $c === 200 && $is_png($bd));
[$c, $bd] = http('GET', $docUrl($photoFile), $sCab);
check("Cabanatuan employer -> 403", $c === 403 && !$is_png($bd));

// ================================================================ 6. status update
echo "\n=== 6. status update is scoped to the owning employer ===\n";
$statusOf = fn() => $db->query("SELECT application_status FROM applications WHERE application_id = $mariaApp")->fetchColumn();
$db->exec("UPDATE applications SET application_status = 'Pending' WHERE application_id = $mariaApp");
[$c, $b, $h] = http('POST', '/employer/review-candidate', $sGuimba,
    ['csrf_token' => 'testtok0', 'app_id' => $mariaApp, 'status' => 'Reviewed']);
check("owning employer POST -> 302 status_updated=1", $c === 302 && strpos($h['location'] ?? '', 'status_updated=1') !== false);
check("application row is now 'Reviewed'", $statusOf() === 'Reviewed');
[$c, $b, $h] = http('POST', '/employer/review-candidate', $sCab,
    ['csrf_token' => 'testtok0', 'app_id' => $mariaApp, 'status' => 'Rejected']);
check("cross-employer POST -> 302 to the dashboard (firewalled before the write)",
    $c === 302 && strpos($h['location'] ?? '', 'access_denied_or_not_found') !== false);
check("application row is UNCHANGED ('Reviewed', not 'Rejected')", $statusOf() === 'Reviewed');
$db->exec("UPDATE applications SET application_status = 'Pending' WHERE application_id = $mariaApp");

// ================================================================ 7. frozen score, not a live read
echo "\n=== 7. score shown is the frozen ai_match_score, not job_match_scores ===\n";
// Throwaway employer + seeker + Open job + application. The application-time
// score and a divergent live job_match_scores row are seeded on purpose.
$db->prepare("INSERT INTO users (email, email_verified_at, role, account_status, last_login_at)
              VALUES (:e, NOW(), 'employer', 'Active', NOW())")->execute([':e' => 'emp.' . bin2hex(random_bytes(4)) . RC_DOMAIN]);
$rcEmpU = (int) $db->lastInsertId();
$db->prepare("INSERT INTO users (email, email_verified_at, role, account_status, last_login_at)
              VALUES (:e, NOW(), 'jobseeker', 'Active', NOW())")->execute([':e' => 'seek.' . bin2hex(random_bytes(4)) . RC_DOMAIN]);
$rcSeekU = (int) $db->lastInsertId();

$muni = (int) $db->query("SELECT municipality_id FROM lib_municipalities ORDER BY municipality_id LIMIT 1")->fetchColumn();
$db->prepare(
    "INSERT INTO employers (user_id, company_name, contact_person, company_email, company_phone,
                            municipality_id, business_permit_file, verified_status, verified_at)
     VALUES (:u, :cn, 'RC Tester', :ce, '09170000000', :m, 'rc_no_such_permit.pdf', 'Verified', NOW())"
)->execute([':u' => $rcEmpU, ':cn' => RC_TAG . ' Co', ':ce' => 'rc' . RC_DOMAIN, ':m' => $muni]);
$rcEmp = (int) $db->lastInsertId();

$db->prepare("INSERT INTO job_seekers (user_id, first_name, last_name, home_municipality_id, profile_visibility, profile_completeness)
              VALUES (:u, 'Rcfirst', 'Rclast', :m, 'Public', 100)")->execute([':u' => $rcSeekU, ':m' => $muni]);
$rcSeek = (int) $db->lastInsertId();

$db->prepare(
    "INSERT INTO job_postings (employer_id, job_title, job_description, min_years_experience,
                               employment_type, work_arrangement, municipality_id, job_status)
     VALUES (:e, :t, 'Throwaway vacancy for the frozen-score proof.', 0, 'Full-time', 'On-site', :m, 'Open')"
)->execute([':e' => $rcEmp, ':t' => RC_TAG . ' Vacancy', ':m' => $muni]);
$rcJob = (int) $db->lastInsertId();

$db->prepare("INSERT INTO applications (jobseeker_id, job_id, ai_match_score, application_status)
              VALUES (:j, :job, 0.7300, 'Pending')")->execute([':j' => $rcSeek, ':job' => $rcJob]);
$rcApp = (int) $db->lastInsertId();

// Divergent live row: final_score 0.1100 — if the page read this instead of the
// frozen 0.7300, it would show 11%.
$db->prepare(
    "INSERT INTO job_match_scores (job_id, jobseeker_id, skill_score, geo_multiplier, final_score,
                                   raw_jaccard, mandatory_met, mandatory_total, preferred_met, preferred_total, engine_version)
     VALUES (:job, :j, 0.1100, 1.00, 0.1100, 0.1100, 0, 1, 0, 0, 'rc-test')"
)->execute([':job' => $rcJob, ':j' => $rcSeek]);

$sRcEmp = sess($rcEmpU, 'employer');
[$c, $b] = http('GET', $rcUrl($rcApp), $sRcEmp);
check("throwaway page loads (HTTP 200)", $c === 200);
check("shows the FROZEN application score (73%)", strpos($b, '>73%<') !== false);
check("does NOT show the divergent live job_match_scores value (11%)", strpos($b, '>11%<') === false);
check("live job_match_scores row really does disagree",
    $db->query("SELECT final_score FROM job_match_scores WHERE job_id = $rcJob AND jobseeker_id = $rcSeek")->fetchColumn() === '0.1100');

cleanup();
$sessions = [];
check("throwaway rows cleaned — no '[RC-verify]' employer remains",
    (int) $db->query("SELECT COUNT(*) FROM employers WHERE company_name LIKE '" . str_replace("'", "''", RC_TAG) . "%'")->fetchColumn() === 0);
check("throwaway @demo-rc users gone",
    (int) $db->query("SELECT COUNT(*) FROM users WHERE email LIKE '%" . RC_DOMAIN . "'")->fetchColumn() === 0);

// ================================================================ 8. regression
echo "\n=== 8. regression: the six prior harnesses still green ===\n";
$restoreMariaResume();  // make sure the world is pristine before the sub-runs
$php = PHP_BINARY;
foreach ([
    'verify_documents.php', 'verify_feed.php', 'http_verify.php',
    'verify_job_posting.php', 'verify_employer_builder.php', 'verify_admin_verification.php',
] as $script) {
    $out = []; $rc = 0;
    exec(escapeshellarg($php) . ' ' . escapeshellarg(BASE_PATH . 'scripts/' . $script) . ' 2>&1', $out, $rc);
    check("$script exits 0 (" . trim((string) end($out)) . ")", $rc === 0);
}

echo "\n----------------------------------------\n";
echo "PASS: $pass   FAIL: $fail\n";
exit($fail ? 1 : 0);
