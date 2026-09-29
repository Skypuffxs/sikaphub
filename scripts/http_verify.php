<?php
/**
 * scripts/http_verify.php — end-to-end verification of GET /dashboard through
 * a running Apache (UC-05 steps 11-14 / C-35, C-44). Crafts a real PHP session
 * file for each demo seeker, fetches the dashboard over HTTP, and asserts the
 * rendered HTML: verification badge tiers, match % vs "Match pending", the
 * skill chips, the Applied state, the awaiting-scoring notice, the location
 * nudge, the real profile-completeness meter, zero score rows written on load,
 * and a forced DB error rendering errors/500 with no SQL leaked. Run:
 *
 *   php scripts/seed_demo.php --scores
 *   php scripts/http_verify.php
 *     [env: SIKAP_BASE_URL=http://localhost/sikaphub  SIKAP_SESS_PATH=C:/xampp/tmp]
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("http_verify.php is a command-line tool.\n");
}

define('BASE_PATH', dirname(__DIR__) . '/');

foreach (file(BASE_PATH . '.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (strpos(trim($line), '#') === 0) continue;
    [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
    $_ENV[trim($k)] = trim($v);
}
require BASE_PATH . 'config/Database.php';
$db = Database::getInstance()->getConnection();

$manifestPath = BASE_PATH . 'storage/demo_manifest.json';
if (!is_file($manifestPath)) {
    fwrite(STDERR, "no storage/demo_manifest.json — run: php scripts/seed_demo.php --scores\n");
    exit(1);
}
$man = json_decode(file_get_contents($manifestPath), true);

const UA = 'sikaphub-verify/1.0';
$BASEURL = rtrim(getenv('SIKAP_BASE_URL') ?: 'http://localhost/sikaphub', '/');
$SAVE = rtrim(getenv('SIKAP_SESS_PATH') ?: (ini_get('session.save_path') ?: 'C:/xampp/tmp'), '/\\');

$pass = 0; $fail = 0;
function check($l, $c) { global $pass, $fail; echo ($c ? "  PASS  " : "  FAIL  ") . $l . "\n"; $c ? $pass++ : $fail++; }

function makeSession($saveDir, $userId) {
    $sid = bin2hex(random_bytes(16));
    $data = "user_id|i:{$userId};"
        . "ua_hash|s:64:\"" . hash('sha256', UA) . "\";"
        . "last_activity|i:" . time() . ";"
        . "role|s:9:\"jobseeker\";"
        . "account_status|s:6:\"Active\";"
        . "csrf_token|s:8:\"testtok0\";";
    file_put_contents("$saveDir/sess_$sid", $data);
    return $sid;
}

function fetchPage($baseUrl, $sid, $path) {
    $ch = curl_init($baseUrl . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => UA,
        CURLOPT_HTTPHEADER => ['Cookie: PHPSESSID=' . $sid],
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($body === false) {
        fwrite(STDERR, "curl failed for {$path}: {$err}\n(is Apache up at {$GLOBALS['BASEURL']} ?)\n");
        exit(1);
    }
    return [$code, $body];
}

foreach (['maria', 'jayson', 'andrea'] as $who) {
    echo "\n=== $who (HTTP) ===\n";
    $uid = $man['seekers'][$who]['user_id'];
    $jid = $man['seekers'][$who]['jobseeker_id'];
    $sid = makeSession($SAVE, $uid);

    $rowsBefore = (int) $db->query("SELECT COUNT(*) FROM job_match_scores WHERE jobseeker_id=$jid")->fetchColumn();
    [$code, $html] = fetchPage($BASEURL, $sid, '/dashboard');
    $rowsAfter = (int) $db->query("SELECT COUNT(*) FROM job_match_scores WHERE jobseeker_id=$jid")->fetchColumn();

    check("HTTP 200", $code === 200);
    check("zero job_match_scores rows written during GET /dashboard (was $rowsBefore, now $rowsAfter)", $rowsBefore === $rowsAfter);
    check("page is the seeker dashboard", strpos($html, 'Recommended for You') !== false);

    if ($who === 'maria') {
        check("shows a Verified employer badge", strpos($html, 'Verified employer') !== false);
        check("shows a match percentage (100%)", strpos($html, '100%') !== false);
        check("no awaiting-scoring notice", strpos($html, 'awaiting scoring') === false);
        check("no location prompt (has home municipality)", strpos($html, 'complete your location') === false);
        check("Farm Operations Supervisor before Poultry Farm Lead",
            strpos($html, 'Farm Operations Supervisor') < strpos($html, 'Poultry Farm Lead'));
        check("real profile completeness rendered (100%), not hardcoded 'width: 90%'",
            strpos($html, 'Excellent &middot; 100%') !== false || strpos($html, 'Excellent · 100%') !== false);
        check("hardcoded 90% strength bar is gone", strpos($html, 'width: 90%') === false);
        check("GraphQL chip shown as awaiting approval", strpos($html, 'GraphQL') !== false && strpos($html, 'awaiting approval') !== false);
        // Applied state (seed_demo: Maria applied to bookkeeper).
        check("exactly one card shows the Applied state", preg_match_all('/>\s*Applied\s*</', $html) === 1);
        check("the other 5 cards show a working Apply button", substr_count($html, '1-Click Apply') === 5);
    }
    if ($who === 'jayson') {
        check("location prompt shown (no home municipality)", strpos($html, 'complete your location') !== false);
        check("prompt is a nudge, not a penalty warning", strpos($html, 'not lowered') !== false);
        check("Web Developer 100% present", strpos($html, '100%') !== false);
    }
    if ($who === 'andrea') {
        check("all 6 cards show the 'Pending' match state", substr_count($html, 'leading-tight">Pending<') === 6);
        check("zero scored match badges rendered", substr_count($html, 'text-indigo-700">') === 0);
        check("awaiting-scoring notice shown", strpos($html, 'awaiting scoring') !== false);
    }
    @unlink("$SAVE/sess_$sid");
}

echo "\n=== forced DB error ===\n";
$sid = makeSession($SAVE, $man['seekers']['maria']['user_id']);
$db->exec("ALTER TABLE job_match_scores RENAME TO job_match_scores_x");
[$code, $html] = fetchPage($BASEURL, $sid, '/dashboard');
$db->exec("ALTER TABLE job_match_scores_x RENAME TO job_match_scores");
check("forced missing table -> HTTP 500", $code === 500);
check("renders errors/500 view, not a blank page", strpos($html, 'Something went wrong') !== false && strlen($html) > 200);
check("no raw SQL / stack trace leaked", stripos($html, 'SQLSTATE') === false && stripos($html, 'job_match_scores_x') === false);
@unlink("$SAVE/sess_$sid");

echo "\n----------------------------------------\n";
echo "PASS: $pass   FAIL: $fail\n";
exit($fail ? 1 : 0);
