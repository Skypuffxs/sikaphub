<?php
/**
 * scripts/verify_documents.php — end-to-end verification of the document
 * authorization gateway (GET /admin/view-document) through a running Apache.
 *
 * Covers Commit A: DB-first resolution, role/ownership policy, Active-account
 * requirement, served-Content-Type whitelist + nosniff, uniform 403s that do
 * not reveal existence, path-traversal rejection, logged-out rejection, no
 * direct HTTP access to storage/ (with and without the root .htaccess), and
 * the MIME-derived stored extension in FileUpload::secureUpload().
 *
 *   php scripts/seed_demo.php
 *   php scripts/verify_documents.php
 *     [env: SIKAP_BASE_URL=http://localhost/sikaphub  SIKAP_SESS_PATH=C:/xampp/tmp]
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("verify_documents.php is a command-line tool.\n");
}

define('BASE_PATH', dirname(__DIR__) . '/');

foreach (file(BASE_PATH . '.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (strpos(trim($line), '#') === 0) continue;
    [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
    $_ENV[trim($k)] = trim($v);
}
// This harness mutates the filesystem (it moves the root .htaccess aside to
// prove storage/.htaccess holds on its own). Refuse to run anywhere but a
// local development box.
if (PHP_SAPI !== 'cli' || ($_ENV['APP_ENV'] ?? '') !== 'development') {
    fwrite(STDERR, "REFUSING: verify_documents.php runs only from the CLI with APP_ENV=development.\n");
    exit(1);
}

require BASE_PATH . 'config/Database.php';
$db = Database::getInstance()->getConnection();

$manifestPath = BASE_PATH . 'storage/demo_manifest.json';
if (!is_file($manifestPath)) {
    fwrite(STDERR, "no storage/demo_manifest.json — run: php scripts/seed_demo.php\n");
    exit(1);
}
$man = json_decode(file_get_contents($manifestPath), true);

const UA = 'sikaphub-verify/1.0';
$BASEURL = rtrim(getenv('SIKAP_BASE_URL') ?: 'http://localhost/sikaphub', '/');
$SAVE = rtrim(getenv('SIKAP_SESS_PATH') ?: (ini_get('session.save_path') ?: 'C:/xampp/tmp'), '/\\');

$pass = 0; $fail = 0;
function check($l, $c) { global $pass, $fail; echo ($c ? "  PASS  " : "  FAIL  ") . $l . "\n"; $c ? $pass++ : $fail++; }

function makeSession($saveDir, $userId, $role, $status = 'Active') {
    $sid = bin2hex(random_bytes(16));
    $data = 'user_id|i:' . (int) $userId . ';'
        . 'ua_hash|s:64:"' . hash('sha256', UA) . '";'
        . 'last_activity|i:' . time() . ';'
        . 'role|s:' . strlen($role) . ':"' . $role . '";'
        . 'account_status|s:' . strlen($status) . ':"' . $status . '";'
        . 'csrf_token|s:8:"testtok0";';
    file_put_contents("$saveDir/sess_$sid", $data);
    return $sid;
}

/** @return array [httpCode, body, rawHeaders] */
function fetch($base, $sid, $path) {
    $ch = curl_init($base . $path);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT      => UA,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HEADER         => true,
    ];
    if ($sid !== null) {
        $opts[CURLOPT_HTTPHEADER] = ['Cookie: PHPSESSID=' . $sid];
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    if ($raw === false) {
        fwrite(STDERR, "curl failed for {$path}: " . curl_error($ch) . "\n(is Apache up?)\n");
        exit(1);
    }
    $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hsize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return [$code, substr($raw, $hsize), substr($raw, 0, $hsize)];
}

function header_val($rawHeaders, $name) {
    foreach (explode("\r\n", $rawHeaders) as $line) {
        if (stripos($line, $name . ':') === 0) {
            return trim(substr($line, strlen($name) + 1));
        }
    }
    return null;
}
function is_pdf_body($b)      { return strncmp($b, '%PDF', 4) === 0; }
function is_png_body($b)      { return strncmp($b, "\x89PNG", 4) === 0; }
function is_error_view($b)    { return strpos($b, 'S.I.K.A.P. Hub') !== false && strpos($b, 'Go back') !== false; }

$D = $man['documents'];
$adminId   = (int) $db->query("SELECT user_id FROM users WHERE role='admin' ORDER BY user_id LIMIT 1")->fetchColumn();
$guimbaU   = $man['employers']['guimba_agricorp']['user_id'];
$cabU      = $man['employers']['cabanatuan_tech']['user_id'];
$mariaU    = $man['seekers']['maria']['user_id'];
$jaysonU   = $man['seekers']['jayson']['user_id'];
$andreaU   = $man['seekers']['andrea']['user_id'];

$sids = [];
$sess = function ($userId, $role, $status = 'Active') use (&$sids, $SAVE) {
    $sid = makeSession($SAVE, $userId, $role, $status);
    $sids[] = $sid;
    return $sid;
};
$url = fn($file) => '/admin/view-document?file=' . rawurlencode($file);

// ---------------------------------------------------------------- 1. admin OK
echo "\n=== 1. admin fetches a permit ===\n";
[$c, $b, $h] = fetch($BASEURL, $sess($adminId, 'admin'), $url($D['guimba_permit']['file']));
check("HTTP 200", $c === 200);
check("body is the PDF", is_pdf_body($b));
check("Content-Type: application/pdf", stripos((string) header_val($h, 'Content-Type'), 'application/pdf') === 0);
check("X-Content-Type-Options: nosniff", strcasecmp((string) header_val($h, 'X-Content-Type-Options'), 'nosniff') === 0);
check("Content-Disposition does not echo the filename",
    strpos((string) header_val($h, 'Content-Disposition'), $D['guimba_permit']['file']) === false);

// ---------------------------------------------------------------- 2. seeker -> permit
echo "\n=== 2. seeker fetches that permit ===\n";
[$c, $b] = fetch($BASEURL, $sess($mariaU, 'jobseeker'), $url($D['guimba_permit']['file']));
check("HTTP 403", $c === 403);
check("not the PDF", !is_pdf_body($b));
check("renders the error view", is_error_view($b));
$seekerDenyBody = $b;

// ---------------------------------------------------------------- 3. other employer -> permit
echo "\n=== 3. Cabanatuan employer fetches Guimba's permit ===\n";
[$c, $b] = fetch($BASEURL, $sess($cabU, 'employer'), $url($D['guimba_permit']['file']));
check("HTTP 403", $c === 403);
check("not the PDF", !is_pdf_body($b));

// ---------------------------------------------------------------- 4. employer <-> applicant docs
echo "\n=== 4. employer and applicant documents ===\n";
[$c, $b, $h] = fetch($BASEURL, $sess($guimbaU, 'employer'), $url($D['maria_resume']['file']));
check("4a Guimba employer -> Maria's resume (she applied to their job): 200 + PDF",
    $c === 200 && is_pdf_body($b));
[$c, $b] = fetch($BASEURL, $sess($guimbaU, 'employer'), $url($D['andrea_resume']['file']));
check("4b Guimba employer -> Andrea's resume (she applied to a DIFFERENT employer): 403",
    $c === 403 && !is_pdf_body($b));
[$c, $b, $h] = fetch($BASEURL, $sess($cabU, 'employer'), $url($D['andrea_resume']['file']));
check("4b' Cabanatuan employer -> Andrea's resume (she applied to THEIR job): 200 + PDF",
    $c === 200 && is_pdf_body($b));
[$c, $b, $h] = fetch($BASEURL, $sess($guimbaU, 'employer'), $url($D['maria_photo']['file']));
check("4c Guimba employer -> Maria's photo: 200 + PNG + image/png",
    $c === 200 && is_png_body($b) && stripos((string) header_val($h, 'Content-Type'), 'image/png') === 0);
[$c, $b] = fetch($BASEURL, $sess($mariaU, 'jobseeker'), $url($D['maria_resume']['file']));
check("4d Maria -> her own resume: 200 + PDF", $c === 200 && is_pdf_body($b));
[$c, $b] = fetch($BASEURL, $sess($jaysonU, 'jobseeker'), $url($D['maria_resume']['file']));
check("4e Jayson -> Maria's resume: 403", $c === 403 && !is_pdf_body($b));

// ------------------------------------------------- account_status gate (addition 3)
// The gateway reads users.account_status live (same authority as the feed).
// seed_demo seeds a dedicated Suspended employer whose job Andrea applied to,
// so the ownership JOIN passes and account_status is the only thing left to
// deny on — no live row is mutated by this harness.
echo "\n=== 4f. suspended employer ===\n";
$suspU = $man['employers']['suspended_staffing']['user_id'];
check("seeded employer is Suspended in the DB",
    $db->query("SELECT account_status FROM users WHERE user_id = $suspU")->fetchColumn() === 'Suspended');
[$c, $b] = fetch($BASEURL, $sess($suspU, 'employer'), $url($D['andrea_resume']['file']));
check("Suspended employer -> resume of a seeker who applied to their job: 403",
    $c === 403 && !is_pdf_body($b));

// ---------------------------------------------------------------- 5. unreferenced file
echo "\n=== 5. file on disk, referenced by no row ===\n";
check("fixture file exists on disk",
    is_file(BASE_PATH . 'storage/documents/' . $D['unreferenced']['file']));
[$c, $b] = fetch($BASEURL, $sess($adminId, 'admin'), $url($D['unreferenced']['file']));
check("admin -> unreferenced file: 403", $c === 403);
check("not served off disk", !is_pdf_body($b));
check("response body identical to the seeker-denied case (no existence signal)",
    $b === $seekerDenyBody);

// ---------------------------------------------------------------- 6. path traversal
echo "\n=== 6. path traversal in ?file ===\n";
foreach ([
    '../../config/Database.php' => 'class Database',
    '....//....//.env'          => 'DB_NAME',
    '..\\..\\.env'              => 'DB_NAME',
] as $probe => $needle) {
    [$c, $b] = fetch($BASEURL, $sess($adminId, 'admin'), '/admin/view-document?file=' . rawurlencode($probe));
    check("`$probe` -> 403", $c === 403);
    check("`$probe` -> file contents not disclosed", strpos($b, $needle) === false);
}

// ---------------------------------------------------------------- 7. logged out + direct storage
echo "\n=== 7. logged-out and direct storage access ===\n";
[$c, $b, $h] = fetch($BASEURL, null, $url($D['guimba_permit']['file']));
check("logged-out -> not served (302 to /login)", $c === 302);
check("logged-out -> redirected to login", strpos((string) header_val($h, 'Location'), '/login') !== false);
check("logged-out -> no PDF in body", !is_pdf_body($b));

[$c, $b] = fetch($BASEURL, null, '/storage/documents/' . $D['guimba_permit']['file']);
check("direct GET /storage/... -> not served (root .htaccess rewrite)", $c !== 200 && !is_pdf_body($b));

$rootHt = BASE_PATH . '.htaccess';
$moved = false;
register_shutdown_function(function () use ($rootHt, &$moved) {
    if ($moved && is_file($rootHt . '.verify-bak')) {
        @rename($rootHt . '.verify-bak', $rootHt);
    }
});
if (is_file($rootHt) && @rename($rootHt, $rootHt . '.verify-bak')) {
    $moved = true;
    clearstatcache();
    [$c, $b] = fetch($BASEURL, null, '/storage/documents/' . $D['guimba_permit']['file']);
    check("direct GET /storage/... with root .htaccess removed -> still not served (storage/.htaccess)",
        $c !== 200 && !is_pdf_body($b));
    @rename($rootHt . '.verify-bak', $rootHt);
    $moved = false;
    clearstatcache();
} else {
    echo "  SKIP  could not move root .htaccess to test storage/.htaccess in isolation\n";
}

// ---------------------------------------------------------------- 8. MIME-derived extension
echo "\n=== 8. FileUpload stores a MIME-derived extension ===\n";
require BASE_PATH . 'app/helpers/FileUpload.php';
$rm = new ReflectionMethod('FileUpload', 'extensionForMime');
$rm->setAccessible(true);
check("application/pdf -> pdf", $rm->invoke(null, 'application/pdf') === 'pdf');
check("image/jpeg -> jpg", $rm->invoke(null, 'image/jpeg') === 'jpg');
check("image/png -> png", $rm->invoke(null, 'image/png') === 'png');
$threw = false;
try { $rm->invoke(null, 'application/x-msdownload'); }
catch (Throwable $e) { $threw = ($e->getPrevious() ?: $e) instanceof RuntimeException; }
check("an unmapped MIME type throws (fail-closed, no guessed extension)", $threw);
$src = file_get_contents(BASE_PATH . 'app/helpers/FileUpload.php');
check("secureUpload() no longer derives the stored name from the user's extension",
    strpos($src, "pathinfo(\$fileArray['name'], PATHINFO_EXTENSION)") === false);
check("a PDF uploaded as evil.php would be stored .pdf (MIME-derived), not .php",
    $rm->invoke(null, 'application/pdf') === 'pdf');
$storageHt = BASE_PATH . 'storage/.htaccess';
check("storage/.htaccess exists with a deny rule",
    is_file($storageHt) && preg_match('/Require all denied|Deny from all/i', file_get_contents($storageHt)));

// ---------------------------------------------------------------- cleanup
foreach ($sids as $sid) { @unlink("$SAVE/sess_$sid"); }

echo "\n----------------------------------------\n";
echo "PASS: $pass   FAIL: $fail\n";
exit($fail ? 1 : 0);
