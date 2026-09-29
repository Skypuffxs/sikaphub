<?php
/**
 * scripts/verify_admin_verification.php — end-to-end verification of the PESO
 * admin employer-verification path (UC-03), through a running Apache.
 * Task 6 Commit 3: C-36 advanced, C-44 slice, D-18 implemented.
 *
 * Covers:
 *   1. the pending-employer queue: the permit link is populated
 *      (business_permit_file, not the old $emp['business_permit']) and opens
 *      200 with the right MIME through the Commit A document gateway;
 *   2. approve -> verified_status 'Verified', verified_at set, one audit_logs
 *      'employer_verified' row, one notifications 'employer_verified' row; the
 *      publish gate then opens and that employer can POST a job;
 *   3. reject -> verified_status 'Rejected', verified_at set, the employer's
 *      Open postings become 'Suspended' in the same transaction (D-18), the
 *      audit_logs 'employer_rejected' row records the count, and Maria's feed
 *      row count drops by exactly the number suspended;
 *   4. a forced notifications-insert failure (FK violation — the employer's
 *      user_id does not exist) leaves the verification decision committed and
 *      is logged, never rolled back;
 *   5. an invalid status value -> redirect with ?error=invalid_status, never a
 *      blank 200;
 *   6. a seeker session POSTing /admin/verify-employer -> 403 + errors view,
 *      no state change; a POST with a bad CSRF token is refused by the base
 *      Controller, no state change;
 *   7. fixture teardown leaves no invalid rows — no '[C3-verify]' employer, no
 *      @demo-av user, and no employers row with a dangling user_id anywhere
 *      (a crashed run must not strand an invalid row in the pending list);
 *   8. regression: verify_job_posting / verify_employer_builder /
 *      verify_documents / verify_feed / http_verify still green.
 *
 * It creates throwaway @demo-av.sikaphub.local users and '[C3-verify]'-named
 * employers and self-cleans them; like the other harnesses it refuses to run
 * outside local dev. Case 4 sets FOREIGN_KEY_CHECKS=0 for a single row write
 * (a session setting, not DDL) and restores it immediately.
 *
 *   php scripts/seed_demo.php --scores
 *   php scripts/verify_admin_verification.php
 *     [env: SIKAP_BASE_URL=http://localhost/sikaphub  SIKAP_SESS_PATH=C:/xampp/tmp]
 */

define('BASE_PATH', dirname(__DIR__) . '/');

foreach (file(BASE_PATH . '.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (strpos(trim($line), '#') === 0) continue;
    [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
    $_ENV[trim($k)] = trim($v);
}

if (PHP_SAPI !== 'cli' || ($_ENV['APP_ENV'] ?? '') !== 'development') {
    fwrite(STDERR, "REFUSING: verify_admin_verification.php runs only from the CLI with APP_ENV=development.\n");
    exit(1);
}

require BASE_PATH . 'config/Database.php';
require BASE_PATH . 'app/models/JobSeeker.php';
$db = Database::getInstance()->getConnection();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

const UA        = 'sikaphub-verify/1.0';
const AV_DOMAIN = '@demo-av.sikaphub.local';
const EMP_TAG   = '[C3-verify]';
$BASEURL = rtrim(getenv('SIKAP_BASE_URL') ?: 'http://localhost/sikaphub', '/');
$SAVE    = rtrim(getenv('SIKAP_SESS_PATH') ?: (ini_get('session.save_path') ?: 'C:/xampp/tmp'), '/\\');

$manifestPath = BASE_PATH . 'storage/demo_manifest.json';
if (!is_file($manifestPath)) {
    fwrite(STDERR, "no storage/demo_manifest.json — run: php scripts/seed_demo.php --scores\n");
    exit(1);
}
$man = json_decode(file_get_contents($manifestPath), true);

$pass = 0; $fail = 0; $skip = 0;
function check($l, $c) { global $pass, $fail; echo ($c ? "  PASS  " : "  FAIL  ") . $l . "\n"; $c ? $pass++ : $fail++; }
function skip($l)      { global $skip; echo "  SKIP  " . $l . "\n"; $skip++; }

// ---------------------------------------------------------------- cleanup
$sessions = [];
function cleanup()
{
    global $db, $SAVE, $sessions;
    try {
        $db->exec("SET FOREIGN_KEY_CHECKS=0");
        $ids = $db->query("SELECT employer_id FROM employers WHERE company_name LIKE '" . str_replace("'", "''", EMP_TAG) . "%'")
            ->fetchAll(PDO::FETCH_COLUMN);
        if ($ids) {
            $in = implode(',', array_map('intval', $ids));
            $db->exec("DELETE FROM job_match_scores WHERE job_id IN (SELECT job_id FROM job_postings WHERE employer_id IN ($in))");
            $db->exec("DELETE FROM notifications WHERE entity_type='employer' AND entity_id IN ($in)");
            $db->exec("DELETE FROM job_postings WHERE employer_id IN ($in)");
            $db->exec("DELETE FROM employers WHERE employer_id IN ($in)");
        }
        $db->exec("DELETE FROM users WHERE email LIKE '%" . AV_DOMAIN . "'");
    } finally {
        $db->exec("SET FOREIGN_KEY_CHECKS=1");
    }
    foreach ($sessions as $sid) { @unlink("$SAVE/sess_$sid"); }
}
register_shutdown_function('cleanup');
cleanup();          // clear leftovers from any aborted run
$sessions = [];

// ---------------------------------------------------------------- helpers
function mkUser($db, $role, $status = 'Active')
{
    $email = $role . '.' . bin2hex(random_bytes(5)) . AV_DOMAIN;
    $db->prepare("INSERT INTO users (email, email_verified_at, role, account_status, last_login_at)
                  VALUES (:e, NOW(), :r, :s, NOW())")
       ->execute([':e' => $email, ':r' => $role, ':s' => $status]);
    return (int) $db->lastInsertId();
}

/** A throwaway employer with $openJobs Open postings. Pending by default. */
function mkEmployer($db, $label, $municipalityId, $verified = 'Pending', $openJobs = 0, $danglingUser = false)
{
    $userId = $danglingUser ? 2000000000 : mkUser($db, 'employer', 'Active');
    if ($danglingUser) $db->exec("SET FOREIGN_KEY_CHECKS=0");
    $db->prepare(
        "INSERT INTO employers
            (user_id, company_name, contact_person, company_email, company_phone,
             municipality_id, business_permit_file, verified_status, verified_at)
         VALUES (:u, :c, 'HR', :ce, '09170000000', :m, :permit, :vs,
                 " . ($verified === 'Pending' ? 'NULL' : 'NOW()') . ")"
    )->execute([
        ':u' => $userId,
        ':c' => EMP_TAG . ' ' . $label,
        ':ce' => 'hr.' . bin2hex(random_bytes(3)) . AV_DOMAIN,
        ':m' => $municipalityId,
        ':permit' => 'c3verify_' . bin2hex(random_bytes(8)) . '.pdf',   // no file on disk; not fetched
        ':vs' => $verified,
    ]);
    $employerId = (int) $db->lastInsertId();   // read BEFORE any further statement resets it
    if ($danglingUser) $db->exec("SET FOREIGN_KEY_CHECKS=1");

    $jobIds = [];
    for ($i = 0; $i < $openJobs; $i++) {
        $db->prepare(
            "INSERT INTO job_postings
                (employer_id, job_title, job_description, min_years_experience,
                 employment_type, work_arrangement, municipality_id, job_status)
             VALUES (:e, :t, 'Demo vacancy for the admin-verification harness.',
                     0, 'Full-time', 'On-site', :m, 'Open')"
        )->execute([':e' => $employerId, ':t' => EMP_TAG . " {$label} Vacancy " . ($i + 1), ':m' => $municipalityId]);
        $jid = (int) $db->lastInsertId();
        $db->prepare("INSERT INTO job_required_skills (job_id, skill_id, requirement_type) VALUES (:j, 27, 'Mandatory')")
           ->execute([':j' => $jid]);
        $jobIds[] = $jid;
    }
    return ['employer_id' => $employerId, 'user_id' => $userId, 'job_ids' => $jobIds];
}

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
function locationOf($h) { return $h['location'] ?? ''; }

$feedModel = new JobSeeker();
$mariaId   = (int) $man['seekers']['maria']['jobseeker_id'];
$feedCount = fn() => count($feedModel->getRecommendationFeed($mariaId));

$adminId = (int) $db->query("SELECT user_id FROM users WHERE role='admin' ORDER BY user_id LIMIT 1")->fetchColumn();
$sAdmin  = sess($adminId, 'admin');
$anyMuni = (int) $db->query("SELECT municipality_id FROM lib_municipalities ORDER BY municipality_id LIMIT 1")->fetchColumn();

// A municipality that is in Maria's feed reach — any Open job is, the feed is
// Open-only with no geo filter. Use her home municipality for clarity.
$mariaMuni = (int) $db->query("SELECT home_municipality_id FROM job_seekers WHERE jobseeker_id = $mariaId")->fetchColumn()
    ?: $anyMuni;

$auditCount = function ($employerId, $action) use ($db) {
    $s = $db->prepare("SELECT COUNT(*) FROM audit_logs WHERE entity_type='employer' AND entity_id=:e AND action_type=:a");
    $s->execute([':e' => $employerId, ':a' => $action]);
    return (int) $s->fetchColumn();
};
$notifCount = function ($employerId, $event) use ($db) {
    $s = $db->prepare("SELECT COUNT(*) FROM notifications WHERE entity_type='employer' AND entity_id=:e AND event_type=:v");
    $s->execute([':e' => $employerId, ':v' => $event]);
    return (int) $s->fetchColumn();
};
$empRow = fn($id) => $db->query("SELECT * FROM employers WHERE employer_id = " . (int) $id)->fetch(PDO::FETCH_ASSOC);

// ================================================================ 1. queue + permit link
echo "\n=== 1. pending-employer queue: permit link is populated and opens ===\n";
[$c, $b] = http('GET', '/admin/dashboard', $sAdmin);
check("admin dashboard renders (HTTP 200)", $c === 200);
// The seed's Cabanatuan Tech employer is Pending with a real permit file.
$cabPermit = $man['documents']['cabanatuan_permit']['file'];
check("permit link uses the real column (?file=<business_permit_file>), not an empty href",
    strpos($b, 'view-document?file=' . urlencode($cabPermit)) !== false
    && strpos($b, 'view-document?file="') === false);
[$c, $b, $h] = http('GET', '/admin/view-document?file=' . rawurlencode($cabPermit), $sAdmin);
check("that permit opens: HTTP 200 through the Commit A gateway", $c === 200);
check("served as application/pdf", stripos($h['content-type'] ?? '', 'application/pdf') === 0);
check("body is a PDF", strncmp($b, '%PDF', 4) === 0);

// ================================================================ 2. approve
echo "\n=== 2. approve -> Verified + verified_at + audit + notification + gate opens ===\n";
$appr = mkEmployer($db, 'ApproveTarget', $anyMuni, 'Pending', 0);
[$c, $b, $hh] = http('POST', '/admin/verify-employer', $sAdmin,
    ['csrf_token' => 'testtok0', 'employer_id' => $appr['employer_id'], 'status' => 'Verified']);
check("POST approve -> 302", $c === 302);
check("redirects to the dashboard with ?success=verified", strpos(locationOf($hh), 'success=verified') !== false);
$row = $empRow($appr['employer_id']);
check("verified_status = 'Verified'", ($row['verified_status'] ?? '') === 'Verified');
check("verified_at is set", !empty($row['verified_at']) && $row['verified_at'] !== '0000-00-00 00:00:00');
check("exactly one audit_logs 'employer_verified' row", $auditCount($appr['employer_id'], 'employer_verified') === 1);
check("exactly one notifications 'employer_verified' row for the employer's user",
    $notifCount($appr['employer_id'], 'employer_verified') === 1
    && (int) $db->query("SELECT user_id FROM notifications WHERE entity_type='employer' AND entity_id="
        . (int) $appr['employer_id'])->fetchColumn() === $appr['user_id']);

$sAppr = sess($appr['user_id'], 'employer', 'Active');
$jobForm = [
    'csrf_token'           => 'testtok0',
    'job_title'            => EMP_TAG . ' Post-Approval Job',
    'job_description'      => 'Confirms the publish gate opened after verification.',
    'min_years_experience' => '0',
    'salary_range'         => '18000',
    'employment_type'      => 'Full-time',
    'work_arrangement'     => 'On-site',
    'municipality_id'      => $anyMuni,
    'skills'              => [27],
    'requirement_type'    => [27 => 'Mandatory'],
];
[$c, $b, $hh] = http('POST', '/post-job', $sAppr, $jobForm);
check("newly-verified employer can POST /post-job -> 302 (gate open, not pending_verification)",
    $c === 302 && strpos(locationOf($hh), 'pending_verification') === false);
check("the job row was created",
    (int) $db->query("SELECT COUNT(*) FROM job_postings WHERE job_title = " . $db->quote($jobForm['job_title']))->fetchColumn() === 1);

// ================================================================ 3. reject -> D-18
echo "\n=== 3. reject -> Rejected + Open postings Suspended (D-18) + feed drops ===\n";
$rej = mkEmployer($db, 'RejectTarget', $mariaMuni, 'Pending', 2);
$feedBefore = $feedCount();
check("both of the reject-target's Open jobs are in Maria's feed to begin with",
    $feedBefore >= 2
    && count(array_intersect($rej['job_ids'],
        array_map('intval', array_column($feedModel->getRecommendationFeed($mariaId), 'job_id')))) === 2);

[$c, $b, $hh] = http('POST', '/admin/verify-employer', $sAdmin,
    ['csrf_token' => 'testtok0', 'employer_id' => $rej['employer_id'], 'status' => 'Rejected']);
check("POST reject -> 302 with ?success=rejected", $c === 302 && strpos(locationOf($hh), 'success=rejected') !== false);
$row = $empRow($rej['employer_id']);
check("verified_status = 'Rejected'", ($row['verified_status'] ?? '') === 'Rejected');
check("verified_at is set", !empty($row['verified_at']));
$in = implode(',', array_map('intval', $rej['job_ids']));
$suspended = (int) $db->query("SELECT COUNT(*) FROM job_postings WHERE job_id IN ($in) AND job_status='Suspended'")->fetchColumn();
check("both previously-Open postings are now 'Suspended'", $suspended === 2);
$auditRow = $db->query("SELECT description FROM audit_logs WHERE entity_type='employer' AND entity_id="
    . (int) $rej['employer_id'] . " AND action_type='employer_rejected' ORDER BY log_id DESC LIMIT 1")->fetchColumn();
check("one audit_logs 'employer_rejected' row", $auditCount($rej['employer_id'], 'employer_rejected') === 1);
check("the audit row records the suspended count (\"2\")", is_string($auditRow) && strpos($auditRow, '2') !== false);
check("one notifications 'employer_rejected' row", $notifCount($rej['employer_id'], 'employer_rejected') === 1);
$feedAfter = $feedCount();
check("Maria's feed row count dropped by exactly the number suspended (2)", $feedBefore - $feedAfter === 2);
check("neither suspended job is in Maria's feed any more",
    count(array_intersect($rej['job_ids'],
        array_map('intval', array_column($feedModel->getRecommendationFeed($mariaId), 'job_id')))) === 0);

// ================================================================ 4. notification insert fails, decision stands
echo "\n=== 4. forced notifications-insert failure leaves the decision committed ===\n";
$dangle = mkEmployer($db, 'DanglingUser', $mariaMuni, 'Pending', 1, true);   // user_id 2000000000 (no such user)
// The web request logs via mod_php, whose error_log target is not knowable
// from this CLI process. Resolve it from ini_get('error_log'), or an explicit
// SIKAP_ERROR_LOG override for environments (a fresh clone) where the two
// SAPIs disagree. If neither resolves to a readable file, the log assertion is
// SKIPPED — never reported as passed.
$errLog = '';
foreach (array_filter([getenv('SIKAP_ERROR_LOG') ?: null, ini_get('error_log') ?: null]) as $cand) {
    if (is_file($cand) && is_readable($cand)) { $errLog = $cand; break; }
}
$logSizeBefore = ($errLog !== '') ? filesize($errLog) : null;

[$c, $b, $hh] = http('POST', '/admin/verify-employer', $sAdmin,
    ['csrf_token' => 'testtok0', 'employer_id' => $dangle['employer_id'], 'status' => 'Rejected']);
check("POST reject still returns the success redirect, not a 500", $c === 302 && strpos(locationOf($hh), 'success=rejected') !== false);
$row = $empRow($dangle['employer_id']);
check("verification decision is committed (verified_status = 'Rejected')", ($row['verified_status'] ?? '') === 'Rejected');
check("verified_at is set despite the notification failure", !empty($row['verified_at']));
$dangleJobStatus = $db->query("SELECT job_status FROM job_postings WHERE job_id = " . (int) $dangle['job_ids'][0])->fetchColumn();
check("the D-18 suspension still applied", $dangleJobStatus === 'Suspended');
check("no notifications row was written (the FK insert really failed)",
    $notifCount($dangle['employer_id'], 'employer_rejected') === 0);
check("the audit_logs row was still written", $auditCount($dangle['employer_id'], 'employer_rejected') === 1);
if ($logSizeBefore !== null) {
    clearstatcache();
    $tail = (string) file_get_contents($errLog, false, null, max(0, $logSizeBefore - 1));
    check("the failure was logged ([notify] … failed) in $errLog", strpos($tail, '[notify]') !== false);
} else {
    skip("log-line assertion — no mod_php error_log resolvable "
        . "(set SIKAP_ERROR_LOG to assert it); the catch block calls error_log()");
}

// ================================================================ 5. invalid status
echo "\n=== 5. invalid status value -> redirect with a message, never a blank 200 ===\n";
$badStat = mkEmployer($db, 'BadStatusTarget', $anyMuni, 'Pending', 0);
[$c, $b, $hh] = http('POST', '/admin/verify-employer', $sAdmin,
    ['csrf_token' => 'testtok0', 'employer_id' => $badStat['employer_id'], 'status' => 'Banana']);
check("invalid status -> 302 (not a blank 200)", $c === 302);
check("redirects with ?error=invalid_status", strpos(locationOf($hh), 'error=invalid_status') !== false);
check("employer status unchanged ('Pending')", ($empRow($badStat['employer_id'])['verified_status'] ?? '') === 'Pending');
check("no audit / notification rows written", $auditCount($badStat['employer_id'], 'employer_verified') === 0
    && $auditCount($badStat['employer_id'], 'employer_rejected') === 0
    && (int) $db->query("SELECT COUNT(*) FROM notifications WHERE entity_type='employer' AND entity_id="
        . (int) $badStat['employer_id'])->fetchColumn() === 0);

// ================================================================ 6. authorization + CSRF
echo "\n=== 6. non-admin POST -> 403; bad CSRF -> refused by base Controller ===\n";
$target6 = mkEmployer($db, 'AuthzTarget', $anyMuni, 'Pending', 0);
$seekerU = mkUser($db, 'jobseeker', 'Active');
$sSeeker = sess($seekerU, 'jobseeker', 'Active');
[$c, $b] = http('POST', '/admin/verify-employer', $sSeeker,
    ['csrf_token' => 'testtok0', 'employer_id' => $target6['employer_id'], 'status' => 'Verified']);
check("seeker POST /admin/verify-employer -> 403", $c === 403);
check("responds with the errors view (403), not a blank body", strpos($b, 'S.I.K.A.P. Hub') !== false && strpos($b, '403') !== false);
check("employer status unchanged by the seeker attempt", ($empRow($target6['employer_id'])['verified_status'] ?? '') === 'Pending');

[$c, $b] = http('POST', '/admin/verify-employer', $sAdmin,
    ['csrf_token' => 'WRONG', 'employer_id' => $target6['employer_id'], 'status' => 'Verified']);
check("admin POST with a bad CSRF token is refused by the base Controller",
    stripos($b, 'CSRF') !== false || stripos($b, 'Security Violation') !== false);
check("employer status unchanged after the bad-CSRF attempt", ($empRow($target6['employer_id'])['verified_status'] ?? '') === 'Pending');

// ================================================================ 7. fixture teardown + integrity
echo "\n=== 7. fixture teardown leaves no invalid rows ===\n";
// Drop our own fixtures now (also so the seeded world the other harnesses
// assert against is pristine — they check exact feed counts and query budgets).
cleanup();
$sessions = [];
check("no '[C3-verify]' employer row survives", (int) $db->query(
    "SELECT COUNT(*) FROM employers WHERE company_name LIKE '" . str_replace("'", "''", EMP_TAG) . "%'")->fetchColumn() === 0);
check("the throwaway @demo-av fixture users are gone", (int) $db->query(
    "SELECT COUNT(*) FROM users WHERE email LIKE '%" . AV_DOMAIN . "'")->fetchColumn() === 0);
check("no employers row with a dangling user_id anywhere (a crashed run would strand one in the pending list)",
    (int) $db->query("SELECT COUNT(*) FROM employers e LEFT JOIN users u ON u.user_id = e.user_id WHERE u.user_id IS NULL")->fetchColumn() === 0);
check("no job_postings row orphaned from its employer",
    (int) $db->query("SELECT COUNT(*) FROM job_postings jp LEFT JOIN employers e ON e.employer_id = jp.employer_id WHERE e.employer_id IS NULL")->fetchColumn() === 0);

// ================================================================ 8. regression
echo "\n=== 8. regression: prior harnesses still green ===\n";
$php = PHP_BINARY;
foreach (['verify_job_posting.php', 'verify_employer_builder.php', 'verify_documents.php', 'verify_feed.php', 'http_verify.php'] as $script) {
    $out = []; $rc = 0;
    exec(escapeshellarg($php) . ' ' . escapeshellarg(BASE_PATH . 'scripts/' . $script) . ' 2>&1', $out, $rc);
    check("$script exits 0 (" . trim((string) end($out)) . ")", $rc === 0);
}

echo "\n----------------------------------------\n";
echo "PASS: $pass   FAIL: $fail   SKIP: $skip\n";
exit($fail ? 1 : 0);
