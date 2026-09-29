<?php
/**
 * scripts/verify_job_posting.php — end-to-end verification of the job-posting
 * path against schema v2, through a running Apache (Task 6 Commit 2:
 * C-20 closed across all layers, C-38 code gap closed, C-39 advanced, C-44
 * slice, UC-05 step 15 unblocked).
 *
 * Covers: a Verified employer publishes a job through the real form -> the row
 * inserts with an integer min_years_experience and a real work_arrangement;
 * T2 fires and the vacancy lands in a seeded seeker's feed with a real
 * percentage that is skill_score x geo_multiplier; the seeker can open the
 * vacancy (GET /job/view is 200 with the province name, not a 500); a Pending
 * employer is still refused; a non-numeric / out-of-range experience is a form
 * error with no row; employment_type 'Freelance' and requirement_type
 * 'Optional' are rejected and appear nowhere in the form; a pending-status
 * skill still inserts but is excluded from scoring (C-42); and the four prior
 * harnesses stay green.
 *
 * Creates no users (it drives the seeded fixture employers), tags every job it
 * posts '[C2-verify]', and deletes them on exit. Like the other harnesses it
 * refuses to run outside local dev.
 *
 *   php scripts/seed_demo.php --scores
 *   php scripts/verify_job_posting.php
 *     [env: SIKAP_BASE_URL=http://localhost/sikaphub  SIKAP_SESS_PATH=C:/xampp/tmp]
 */

define('BASE_PATH', dirname(__DIR__) . '/');

foreach (file(BASE_PATH . '.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (strpos(trim($line), '#') === 0) continue;
    [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
    $_ENV[trim($k)] = trim($v);
}

if (PHP_SAPI !== 'cli' || ($_ENV['APP_ENV'] ?? '') !== 'development') {
    fwrite(STDERR, "REFUSING: verify_job_posting.php runs only from the CLI with APP_ENV=development.\n");
    exit(1);
}

require BASE_PATH . 'config/Database.php';
$db = Database::getInstance()->getConnection();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

const UA      = 'sikaphub-verify/1.0';
const JOB_TAG = '[C2-verify]';
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

$sessions = [];
function cleanup()
{
    global $db, $SAVE, $sessions;
    // deleting the job cascades job_required_skills and job_match_scores
    $db->exec("DELETE FROM job_postings WHERE job_title LIKE '" . str_replace("'", "''", JOB_TAG) . "%'");
    foreach ($sessions as $sid) { @unlink("$SAVE/sess_$sid"); }
}
register_shutdown_function('cleanup');
cleanup();
$sessions = [];

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
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT      => UA,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HEADERFUNCTION => function ($c, $line) use (&$hdrs) {
            $p = strpos($line, ':');
            if ($p !== false) $hdrs[strtolower(trim(substr($line, 0, $p)))] = trim(substr($line, $p + 1));
            return strlen($line);
        },
    ];
    $h = ['Expect:'];
    if ($sid !== null) $h[] = 'Cookie: PHPSESSID=' . $sid;
    $opts[CURLOPT_HTTPHEADER] = $h;
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($post ?? []);
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    if ($body === false) { fwrite(STDERR, "curl failed for $path: " . curl_error($ch) . "\n"); exit(1); }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $body, $hdrs];
}

function locationOf($h) { return $h['location'] ?? ''; }
function jobCount($db) { return (int) $db->query("SELECT COUNT(*) FROM job_postings WHERE job_title LIKE '" . str_replace("'", "''", JOB_TAG) . "%'")->fetchColumn(); }

$verifiedUser = $man['employers']['guimba_agricorp']['user_id'];   // verified_status 'Verified'
$pendingUser  = $man['employers']['cabanatuan_tech']['user_id'];   // verified_status 'Pending'
$mariaUser    = $man['seekers']['maria']['user_id'];
$mariaId      = $man['seekers']['maria']['jobseeker_id'];
$pendingSkill = (int) $man['pending_skill']['skill_id'];           // 'GraphQL', status 'pending'

// A municipality in Maria's home province that is NOT her home municipality and
// NOT one of her preferred locations -> geo tier 0.75 (same province).
$mariaHome = (int) $db->query("SELECT home_municipality_id FROM job_seekers WHERE jobseeker_id = $mariaId")->fetchColumn();
$homeProv  = (int) $db->query("SELECT province_id FROM lib_municipalities WHERE municipality_id = $mariaHome")->fetchColumn();
$sameProvMuni = (int) $db->query(
    "SELECT m.municipality_id FROM lib_municipalities m
     WHERE m.province_id = $homeProv AND m.municipality_id <> $mariaHome
       AND m.municipality_id NOT IN (SELECT municipality_id FROM preferred_work_locations WHERE jobseeker_id = $mariaId)
     ORDER BY m.municipality_id LIMIT 1"
)->fetchColumn();
$provName = (string) $db->query("SELECT province_name FROM lib_provinces WHERE province_id = $homeProv")->fetchColumn();

$sV = sess($verifiedUser, 'employer');

// Maria has skill 27 (Bookkeeping) and 37 (MS Office); she lacks 29 (Financial
// Reporting). Verified against the seed.
$goodForm = [
    'csrf_token'           => 'testtok0',
    'job_title'            => JOB_TAG . ' Cooperative Accountant',
    'job_description'      => 'Keeps the books for the cooperative. Demonstration vacancy.',
    'min_years_experience' => '3',
    'salary_range'         => '22000',
    'employment_type'      => 'Contract',
    'work_arrangement'     => 'Hybrid',
    'municipality_id'      => $sameProvMuni,
    'skills'              => [27, 29],
    'requirement_type'    => [27 => 'Mandatory', 29 => 'Mandatory'],
];

// ================================================================ 1. publish
echo "\n=== 1. Verified employer publishes a job through the form ===\n";
[$c, $b] = http('GET', '/post-job', $sV);
check("GET /post-job renders (HTTP 200)", $c === 200);
check("form offers a work_arrangement field (C-39)", strpos($b, 'name="work_arrangement"') !== false);
check("min_years_experience is a number input (C-38 / Q-17)",
    (bool) preg_match('/name="min_years_experience"[^>]*type="number"|type="number"[^>]*name="min_years_experience"/', $b));
check("form no longer offers 'Freelance'", strpos($b, 'Freelance') === false);
// C-20 is a requirement_type concern only — narrow the check to option values
// and the skill-row builder JS. (The "Salary Range (Optional)" label is a
// different, correct use of the word.)
check("no requirement_type option value 'Optional' in the page or its JS",
    strpos($b, 'value="Optional"') === false && stripos($b, 'Optional (Nice') === false);
check("the skill-row builder offers Mandatory and Preferred only",
    strpos($b, 'value="Preferred"') !== false && strpos($b, 'value="Mandatory"') !== false);
check("municipality select is grouped by province", strpos($b, '<optgroup label="' . htmlspecialchars($provName)) !== false);

$before = jobCount($db);
[$c, $b, $h] = http('POST', '/post-job', $sV, $goodForm);
check("POST publish -> 302", $c === 302);
check("redirects to the dashboard with job_posted=1", strpos(locationOf($h), 'job_posted=1') !== false);
check("exactly one job row created", jobCount($db) === $before + 1);

$job = $db->query("SELECT * FROM job_postings WHERE job_title = " . $db->quote($goodForm['job_title']))->fetch(PDO::FETCH_ASSOC);
$jobId = (int) $job['job_id'];
check("min_years_experience stored as the integer 3", (string) $job['min_years_experience'] === '3');
check("work_arrangement stored as 'Hybrid'", $job['work_arrangement'] === 'Hybrid');
check("employment_type stored as 'Contract'", $job['employment_type'] === 'Contract');
check("job_status is 'Open'", $job['job_status'] === 'Open');
$rs = $db->query("SELECT skill_id, requirement_type FROM job_required_skills WHERE job_id = $jobId ORDER BY skill_id")->fetchAll(PDO::FETCH_KEY_PAIR);
check("both required-skill rows written as Mandatory", $rs === ['27' => 'Mandatory', '29' => 'Mandatory']);

// ================================================================ 2. T2 + feed
echo "\n=== 2. T2 fires -> vacancy scored -> in Maria's feed with skill x geo ===\n";
$score = $db->query("SELECT * FROM job_match_scores WHERE job_id = $jobId AND jobseeker_id = $mariaId")->fetch(PDO::FETCH_ASSOC);
check("T2 wrote a job_match_scores row for Maria", $score !== false);
check("geo_multiplier is the 0.75 same-province tier", (float) $score['geo_multiplier'] === 0.75);
check("skill_score is 0.5 (1 of 2 Mandatory met)", (float) $score['skill_score'] === 0.5);
check("final_score == round(skill_score x geo, 4) = 0.3750",
    number_format((float) $score['final_score'], 4) === '0.3750');
check("mandatory_total 2, mandatory_met 1", (int) $score['mandatory_total'] === 2 && (int) $score['mandatory_met'] === 1);

$sMaria = sess($mariaUser, 'jobseeker');
[$c, $feed] = http('GET', '/dashboard', $sMaria);
check("Maria's dashboard renders (HTTP 200)", $c === 200);
check("the new vacancy appears in her feed", strpos($feed, htmlspecialchars($goodForm['job_title'])) !== false);
check("it shows a real 38% match (ROUND(0.375*100)), not 'pending'",
    (bool) preg_match('/38%/', $feed));

// ================================================================ 3. UC-05 step 15
echo "\n=== 3. seeker opens the vacancy (UC-05 step 15) ===\n";
[$c, $show] = http('GET', '/job/view?id=' . $jobId, $sMaria);
check("GET /job/view -> 200 (was a 500 on the province_name bug)", $c === 200);
check("the page shows the province name", strpos($show, htmlspecialchars($provName)) !== false);
check("no SQL error leaked", stripos($show, 'SQLSTATE') === false && stripos($show, 'province_name') === false);

// ================================================================ 4. publish gate
echo "\n=== 4. Pending employer still cannot publish ===\n";
$sP = sess($pendingUser, 'employer');
$before = jobCount($db);
[$c, $b, $h] = http('POST', '/post-job', $sP, array_merge($goodForm, ['job_title' => JOB_TAG . ' Pending Attempt']));
check("POST /post-job -> 302 to pending_verification", $c === 302 && strpos(locationOf($h), 'pending_verification') !== false);
check("no job row created", jobCount($db) === $before);

// ================================================================ 5. experience validation
echo "\n=== 5. min_years_experience validation ===\n";
foreach (['two' => 'non-numeric', '' => 'blank', '99' => 'out of range', '3.5' => 'fractional'] as $val => $label) {
    $before = jobCount($db);
    [$c, $b] = http('POST', '/post-job', $sV, array_merge($goodForm, [
        'job_title' => JOB_TAG . ' Exp ' . $label,
        'min_years_experience' => $val,
    ]));
    check("$label experience -> HTTP 200 form error", $c === 200);
    check("$label experience -> no job row", jobCount($db) === $before);
}
// the good form preserves its other values on the bounce
[$c, $b] = http('POST', '/post-job', $sV, array_merge($goodForm, ['job_title' => JOB_TAG . ' Preserve', 'min_years_experience' => 'bad']));
check("the re-rendered form preserves the submitted job title", strpos($b, JOB_TAG . ' Preserve') !== false);

// ================================================================ 6. employment_type
echo "\n=== 6. employment_type 'Freelance' rejected ===\n";
$before = jobCount($db);
[$c, $b] = http('POST', '/post-job', $sV, array_merge($goodForm, [
    'job_title' => JOB_TAG . ' Freelance Attempt', 'employment_type' => 'Freelance',
]));
check("Freelance -> HTTP 200 form error", $c === 200);
check("Freelance -> no job row", jobCount($db) === $before);

// ================================================================ 7. requirement_type
echo "\n=== 7. requirement_type 'Optional' rejected ===\n";
$before = jobCount($db);
$rsBefore = (int) $db->query("SELECT COUNT(*) FROM job_required_skills")->fetchColumn();
[$c, $b] = http('POST', '/post-job', $sV, array_merge($goodForm, [
    'job_title' => JOB_TAG . ' Optional Attempt',
    'requirement_type' => [27 => 'Mandatory', 29 => 'Optional'],
]));
check("'Optional' requirement_type -> HTTP 200 form error", $c === 200);
check("'Optional' -> no job row", jobCount($db) === $before);
check("'Optional' -> no job_required_skills row", (int) $db->query("SELECT COUNT(*) FROM job_required_skills")->fetchColumn() === $rsBefore);
check("Preferred-only submission is rejected (BR-3 / E1)",
    http('POST', '/post-job', $sV, array_merge($goodForm, [
        'job_title' => JOB_TAG . ' PrefOnly', 'requirement_type' => [27 => 'Preferred', 29 => 'Preferred'],
    ]))[0] === 200 && jobCount($db) === $before);

// ================================================================ 8. pending skill (C-42)
echo "\n=== 8. a pending-status skill inserts but is excluded from scoring (C-42) ===\n";
[$c, $b, $h] = http('POST', '/post-job', $sV, [
    'csrf_token' => 'testtok0',
    'job_title'  => JOB_TAG . ' Pending Skill Job',
    'job_description' => 'Carries the still-unapproved skill as Preferred.',
    'min_years_experience' => '0',
    'salary_range' => '',
    'employment_type' => 'Full-time',
    'work_arrangement' => 'On-site',
    'municipality_id' => $sameProvMuni,
    'skills' => [27, 37, $pendingSkill],
    'requirement_type' => [27 => 'Mandatory', 37 => 'Preferred', $pendingSkill => 'Preferred'],
]);
check("publish -> 302", $c === 302);
$job2 = (int) $db->query("SELECT job_id FROM job_postings WHERE job_title = " . $db->quote(JOB_TAG . ' Pending Skill Job'))->fetchColumn();
check("all three job_required_skills rows inserted (incl. the pending skill)",
    (int) $db->query("SELECT COUNT(*) FROM job_required_skills WHERE job_id = $job2")->fetchColumn() === 3);
$score2 = $db->query("SELECT * FROM job_match_scores WHERE job_id = $job2 AND jobseeker_id = $mariaId")->fetch(PDO::FETCH_ASSOC);
check("Maria was scored on this job", $score2 !== false);
check("preferred_total counts only the approved Preferred skill (1, not 2)", (int) $score2['preferred_total'] === 1);
check("mandatory met 1 of 1, skill_score 1.0000", (int) $score2['mandatory_met'] === 1 && number_format((float) $score2['skill_score'], 4) === '1.0000');

// min_years_experience = 0 -> "No experience required", not "0"
[$c, $show2] = http('GET', '/job/view?id=' . $job2, $sMaria);
check("job/show renders experience 0 as 'No experience required'",
    $c === 200 && strpos($show2, 'No experience required') !== false);
[$c, $feed2] = http('GET', '/dashboard', $sMaria);
check("the feed card renders experience 0 as 'No experience required'",
    strpos($feed2, 'No experience required') !== false);

// ================================================================ 9. Job::getMunicipalities removed
echo "\n=== 9. no third copy of the municipality query ===\n";
require_once BASE_PATH . 'app/models/Job.php';
check("Job::getMunicipalities() is deleted (the post-job form uses Profile::getMunicipalities())",
    !method_exists('Job', 'getMunicipalities'));

// ================================================================ 10. regression
echo "\n=== 10. regression: prior harnesses still green ===\n";
cleanup();               // drop our test jobs before the feed/doc harnesses run
$sessions = [];
$php = PHP_BINARY;
foreach (['verify_employer_builder.php', 'verify_documents.php', 'verify_feed.php', 'http_verify.php'] as $script) {
    $out = []; $rc = 0;
    exec(escapeshellarg($php) . ' ' . escapeshellarg(BASE_PATH . 'scripts/' . $script) . ' 2>&1', $out, $rc);
    check("$script exits 0 (" . trim(end($out)) . ")", $rc === 0);
}

echo "\n----------------------------------------\n";
echo "PASS: $pass   FAIL: $fail\n";
exit($fail ? 1 : 0);
