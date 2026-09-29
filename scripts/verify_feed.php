<?php
/**
 * scripts/verify_feed.php — DB-level verification of the seeker recommendation
 * feed (UC-05 steps 11-14 / M2 §5.2, §6.6 / C-35, C-24). Asserts the 4-query
 * budget (constant in card count), the D-14 dual-key sort and its tiebreakers,
 * the "match pending" handling of unscored jobs, the skill breakdown, and the
 * Applied-state row. Run against the demo fixture:
 *
 *   php scripts/seed_demo.php --scores   (needs the matching engine on :8000)
 *   php scripts/verify_feed.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("verify_feed.php is a command-line tool.\n");
}

define('BASE_PATH', dirname(__DIR__) . '/');

foreach (file(BASE_PATH . '.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (strpos(trim($line), '#') === 0) continue;
    [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
    $_ENV[trim($k)] = trim($v);
}
require BASE_PATH . 'config/Database.php';
require BASE_PATH . 'app/models/JobSeeker.php';

$db = Database::getInstance()->getConnection();
$manifestPath = BASE_PATH . 'storage/demo_manifest.json';
if (!is_file($manifestPath)) {
    fwrite(STDERR, "no storage/demo_manifest.json — run: php scripts/seed_demo.php --scores\n");
    exit(1);
}
$man = json_decode(file_get_contents($manifestPath), true);

$pass = 0; $fail = 0;
function check($label, $cond) {
    global $pass, $fail;
    echo ($cond ? "  PASS  " : "  FAIL  ") . $label . "\n";
    $cond ? $pass++ : $fail++;
}
function comSelect($db) {
    return (int) $db->query("SHOW SESSION STATUS LIKE 'Com_select'")->fetch(PDO::FETCH_ASSOC)['Value'];
}

$model = new JobSeeker();

// helper mirroring JobSeekerController::partitionRequiredSkills()
function partition($rows, $approved) {
    $set = array_flip(array_map('intval', $approved));
    $out = [];
    foreach ($rows as $r) {
        $j = (int) $r['job_id'];
        $out[$j] ??= ['matched' => [], 'unmatched' => [], 'pending' => []];
        if ($r['status'] === 'pending') $out[$j]['pending'][] = $r['skill_name'];
        elseif ($r['status'] === 'approved')
            $out[$j][isset($set[(int)$r['skill_id']]) ? 'matched' : 'unmatched'][] = $r['skill_name'];
    }
    return $out;
}

foreach (['maria', 'jayson', 'andrea'] as $who) {
    $jid = $man['seekers'][$who]['jobseeker_id'];
    echo "\n=== $who (jobseeker_id=$jid) ===\n";

    $before = comSelect($db);
    $jobs = $model->getRecommendationFeed($jid);
    $approved = $model->getApprovedSkillIds($jid);
    $reqRows = $model->getRequiredSkillsForJobs(array_column($jobs, 'job_id'));
    $ctx = $model->getSeekerContext($jid);
    $after = comSelect($db);
    // SHOW SESSION STATUS increments Com_show_status, not Com_select.
    $queries = $after - $before;
    echo "  Com_select delta: $queries\n";
    check("query budget is 4", $queries === 4);

    $bd = partition($reqRows, $approved);
    $order = array_map(fn($j) => $j['job_id'], $jobs);
    echo "  feed order:\n";
    foreach ($jobs as $j) {
        echo sprintf("     - %-34s %s%-8s prof=%s\n", $j['job_title'],
            $j['verified_status'] === 'Verified' ? 'V  ' : 'NV ',
            $j['final_score'] === null ? 'pending' : $j['final_score'],
            $j['matched_mandatory_prof_ordinal']);
    }

    if ($who === 'maria') {
        $fs = fn($name) => $man['jobs'][$name];
        $posFarm = array_search($fs('farm_supervisor'), $order);
        $posPoultry = array_search($fs('poultry_lead'), $order);
        check("all 3 verified-employer jobs precede every pending-employer job",
            max($posFarm, $posPoultry, array_search($fs('bookkeeper'), $order))
            < min(array_search($fs('csr'), $order), array_search($fs('web_developer'), $order), array_search($fs('nurse'), $order)));
        check("farm_supervisor (ord 5) ranks above poultry_lead (ord 4)", $posFarm < $posPoultry);
        check("farm_supervisor ordinal == 5", (int)$jobs[$posFarm]['matched_mandatory_prof_ordinal'] === 5);
        check("poultry_lead ordinal == 4", (int)$jobs[$posPoultry]['matched_mandatory_prof_ordinal'] === 4);
        $ok = true;
        foreach ($jobs as $j) if ($j['final_score'] !== null && $j['match_percentage'] === null) $ok = false;
        check("every scored card has a non-null match_percentage", $ok);
        $web = $jobs[array_search($fs('web_developer'), $order)];
        check("web_developer preferred_total == 2 (GraphQL excluded)", (int)$web['preferred_total'] === 2);
        check("web_developer breakdown lists GraphQL as pending",
            in_array('GraphQL', $bd[$fs('web_developer')]['pending'] ?? []));
        check("GraphQL not in matched/unmatched of web_developer",
            !in_array('GraphQL', array_merge($bd[$fs('web_developer')]['matched'], $bd[$fs('web_developer')]['unmatched'])));
        $types = $db->query("SELECT DISTINCT requirement_type FROM job_required_skills")->fetchAll(PDO::FETCH_COLUMN);
        check("requirement_type values are only Mandatory/Preferred", !array_diff($types, ['Mandatory', 'Preferred']));
        check("maria has home_municipality_id", !empty($ctx['home_municipality_id']));
        check("warehouse_draft absent from feed", !in_array($fs('warehouse_draft'), $order));
        check("feed has exactly 6 open jobs", count($jobs) === 6);

        // Applied-state row (seed_demo: Maria applied to bookkeeper).
        $appliedRows = array_filter($jobs, fn($j) => $j['application_id'] !== null);
        check("exactly one card carries an application_id", count($appliedRows) === 1);
        $appliedJob = array_values($appliedRows)[0];
        check("the applied card is the bookkeeper vacancy", (int)$appliedJob['job_id'] === $fs('bookkeeper'));
        check("every other card has application_id NULL (working Apply button)",
            count(array_filter($jobs, fn($j) => $j['application_id'] === null)) === 5);
        check("feed row count unchanged by the application (still 6)", count($jobs) === 6);
    }

    if ($who === 'jayson') {
        $fs = fn($name) => $man['jobs'][$name];
        $posWeb = array_search($fs('web_developer'), $order);
        $posFarm = array_search($fs('farm_supervisor'), $order);
        check("verified score=0 jobs outrank Jayson's 1.0000 web_developer (pending employer)", $posFarm < $posWeb);
        check("web_developer final_score == 1.0000 for Jayson", (float)$jobs[$posWeb]['final_score'] === 1.0);
        check("jayson home_municipality_id is NULL", empty($ctx['home_municipality_id']));
        $geos = $db->query("SELECT DISTINCT geo_multiplier FROM job_match_scores WHERE jobseeker_id=$jid")->fetchAll(PDO::FETCH_COLUMN);
        check("all Jayson geo_multiplier rows == 1.00 (neutral, C-21): [" . implode(',', $geos) . "]",
            count($geos) === 1 && (float)$geos[0] === 1.0);
    }

    if ($who === 'andrea') {
        $allPending = true; $anyZeroPct = false;
        foreach ($jobs as $j) {
            if ($j['final_score'] !== null) $allPending = false;
            if ($j['match_percentage'] === 0 || $j['match_percentage'] === '0') $anyZeroPct = true;
        }
        check("Andrea sees every open job (6) as Match pending", $allPending && count($jobs) === 6);
        check("no fabricated 0% on any Andrea card", !$anyZeroPct);
        check("awaiting-scoring count == 6 for Andrea",
            count(array_filter($jobs, fn($j) => $j['final_score'] === null)) === 6);
    }

    $order2 = array_map(fn($j) => $j['job_id'], $model->getRecommendationFeed($jid));
    check("feed order identical across two consecutive requests", $order === $order2);
}

echo "\n=== query-count flatness (1 card vs 6) ===\n";
$jid = $man['seekers']['maria']['jobseeker_id'];
$db->beginTransaction();
$openIds = $db->query("SELECT job_id FROM job_postings WHERE job_status='Open'")->fetchAll(PDO::FETCH_COLUMN);
$keep = (int) $openIds[0];
$db->exec("UPDATE job_postings SET job_status='Closed' WHERE job_status='Open' AND job_id <> $keep");
$b = comSelect($db);
$j1 = $model->getRecommendationFeed($jid);
$model->getApprovedSkillIds($jid);
$model->getRequiredSkillsForJobs(array_column($j1, 'job_id'));
$model->getSeekerContext($jid);
$a = comSelect($db);
$db->rollBack();
$q1 = $a - $b;
echo "  1-card feed Com_select delta: $q1\n";
check("query count with 1 card == 4 (same as with 6)", $q1 === 4);

echo "\n----------------------------------------\n";
echo "PASS: $pass   FAIL: $fail\n";
exit($fail ? 1 : 0);
