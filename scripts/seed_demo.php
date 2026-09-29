<?php
/**
 * scripts/seed_demo.php — reproducible demonstration fixture for S.I.K.A.P. Hub.
 *
 * Produces the dataset the seeker-feed evidence in Chapter IV is measured
 * against: three employers (one Verified, one Pending verification, one
 * Verified but with a Suspended user account), six Open vacancies spread
 * across four municipalities in two provinces with Mandatory and Preferred
 * skill requirements (one of them still awaiting admin approval), one Draft
 * and one Closed vacancy that must never surface, and three job seekers — one
 * with a complete Guimba profile, one with no home municipality, and one who
 * is never scored.
 *
 * It also drops real fixture files in storage/ — a business permit per
 * employer, a resume for two seekers, one profile photo, and one document
 * referenced by no row — so scripts/verify_documents.php can exercise the
 * /admin/view-document authorization gateway end to end. Fixture files are
 * cleaned on re-run alongside the demo rows.
 *
 * The fixture is deterministic and self-describing: every logical entity is
 * printed with its real primary key and the same map is written to
 * storage/demo_manifest.json so a verification harness can address rows by
 * name without pinning auto-increment values.
 *
 * Idempotent. Everything it creates is tagged with the e-mail domain
 * @demo.sikaphub.local (plus the single pending skill "GraphQL"); a re-run
 * deletes that set first, so running it twice leaves the same fixture.
 *
 * Local development only. It refuses to run unless APP_ENV=development, and it
 * refuses to run through a web server.
 *
 *   php scripts/seed_demo.php            seed data only
 *   php scripts/seed_demo.php --scores   also run T1 for Maria and Jayson
 *                                        (needs the matching engine on :8000;
 *                                         Andrea is left unscored on purpose)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("seed_demo.php is a command-line tool.\n");
}

define('BASE_PATH', dirname(__DIR__) . '/');

// Minimal .env load — the same parser public/index.php uses.
foreach (file(BASE_PATH . '.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (strpos(trim($line), '#') === 0) {
        continue;
    }
    [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
    $_ENV[trim($k)] = trim($v);
}

if (($_ENV['APP_ENV'] ?? '') !== 'development') {
    fwrite(STDERR, "REFUSING: seed_demo.php runs only with APP_ENV=development (found '"
        . ($_ENV['APP_ENV'] ?? '') . "').\n");
    exit(1);
}

require BASE_PATH . 'config/Database.php';

$withScores = in_array('--scores', array_slice($argv, 1), true);

$db = Database::getInstance()->getConnection();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

const DEMO_DOMAIN = '@demo.sikaphub.local';
const PENDING_SKILL = 'GraphQL';

/*
 * Reference data already present in a fresh schema-v2 database
 * (verified against the live DB before writing this script).
 *
 *   municipalities : Guimba 14, Cabanatuan 1, Cuyapo 10  (Nueva Ecija, prov 1)
 *                    Tarlac City 33 (Tarlac, prov 2)
 *   skills         : 1 PHP, 2 MySQL, 3 JavaScript, 5 HTML/CSS,
 *                    9 Patient Care, 10 Nursing, 12 Medical Records,
 *                    17 Crop Management, 18 Farm Equipment Operation,
 *                    19 Poultry and Livestock, 27 Bookkeeping,
 *                    29 Financial Reporting, 35 Data Entry, 37 MS Office,
 *                    39 Customer Service, 40 Call Handling,
 *                    41 Complaint Resolution
 *
 * Maria's skill set and the job requirements are tuned so she lands a
 * PARTIAL match (0 < skill_score < 1) in every geo tier below 1.00, which
 * is what lets Chapter IV show final_score = skill_score x geo_multiplier
 * for a non-neutral multiplier:
 *
 *   tier 1.00  farm_supervisor / poultry_lead  1.0000 x 1.00 = 1.0000
 *   tier 0.90  bookkeeper (Cuyapo, preferred)  0.6667 x 0.90 = 0.6000
 *   tier 0.75  csr (Cabanatuan, same province) 0.5000 x 0.75 = 0.3750
 *   tier 0.50  nurse (Tarlac, other province)  0.1667 x 0.50 = 0.0833
 */
$MUNI = ['guimba' => 14, 'cabanatuan' => 1, 'cuyapo' => 10, 'tarlac_city' => 33];

echo "seed_demo.php — S.I.K.A.P. Hub demonstration fixture\n";
echo str_repeat('-', 60) . "\n";

/*
 * Fixture files. The document-authorization harness (scripts/verify_documents.php)
 * needs real files on disk owned by real rows, plus one file owned by no row.
 * Generated names are 32 hex chars + extension so cleanup can recognise them
 * and never touch a hand-placed file.
 */
$FIXTURE = [
    // finfo only needs the %PDF magic to report application/pdf.
    'pdf' => "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
        . "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
        . "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\n"
        . "trailer<</Root 1 0 R/Size 4>>\n%%EOF\n",
    // 1x1 PNG.
    'png' => base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    ),
];
const ORPHAN_DOCUMENT = 'demo_unreferenced.pdf';   // referenced by no DB row, on purpose

$putFixture = function (string $subdir, string $ext) use ($FIXTURE): array {
    $bytes = $ext === 'png' ? $FIXTURE['png'] : $FIXTURE['pdf'];
    $mime  = $ext === 'png' ? 'image/png' : 'application/pdf';
    $name  = bin2hex(random_bytes(16)) . '.' . $ext;
    $dir   = BASE_PATH . $subdir;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    file_put_contents($dir . $name, $bytes);
    return ['name' => $name, 'bytes' => $bytes, 'mime' => $mime];
};

$rmFixture = function (string $subdir, ?string $name): void {
    if ($name === null || !preg_match('/^[0-9a-f]{32}\.(pdf|png|jpe?g)$/', $name)) {
        return;
    }
    $p = BASE_PATH . $subdir . $name;
    if (is_file($p)) {
        unlink($p);
    }
};

$db->beginTransaction();
try {
    // ---------------------------------------------------------------- wipe
    $existing = $db->query(
        "SELECT user_id FROM users WHERE email LIKE '%" . DEMO_DOMAIN . "'"
    )->fetchAll(PDO::FETCH_COLUMN);
    $db->prepare("DELETE FROM user_auth_identities WHERE provider_uid LIKE '%" . DEMO_DOMAIN . "'")->execute();
    if ($existing) {
        // Unlink fixture files owned by the demo rows before the cascade drops
        // the rows that name them. The guard in $rmFixture means only our
        // generated files are ever removed.
        $in = implode(',', array_map('intval', $existing));
        foreach ($db->query("SELECT business_permit_file FROM employers WHERE user_id IN ($in)")->fetchAll(PDO::FETCH_COLUMN) as $f) {
            $rmFixture('storage/documents/', $f);
        }
        foreach ($db->query("SELECT profile_photo FROM job_seekers WHERE user_id IN ($in) AND profile_photo IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $f) {
            $rmFixture('storage/uploads/profile_photos/', $f);
        }
        foreach ($db->query("SELECT stored_filename FROM resume_uploads WHERE user_id IN ($in)")->fetchAll(PDO::FETCH_COLUMN) as $f) {
            $rmFixture('storage/uploads/resumes/', $f);
        }
        $db->prepare("DELETE FROM users WHERE email LIKE '%" . DEMO_DOMAIN . "'")->execute();
        echo "removed " . count($existing) . " existing demo user(s), cascaded rows, and fixture files\n";
    }
    if (is_file(BASE_PATH . 'storage/documents/' . ORPHAN_DOCUMENT)) {
        unlink(BASE_PATH . 'storage/documents/' . ORPHAN_DOCUMENT);
    }
    // The pending skill is referenced only by demo jobs (now gone) — drop it
    // so a re-run recreates it cleanly and it never lingers in the vocabulary.
    $db->prepare("DELETE FROM master_skills WHERE skill_name = ? AND status = 'pending'")
        ->execute([PENDING_SKILL]);

    // ---------------------------------------------------------------- helpers
    $mkUser = function (string $handle, string $role, string $accountStatus = 'Active') use ($db): int {
        $email = $handle . DEMO_DOMAIN;
        $db->prepare(
            "INSERT INTO users (email, email_verified_at, role, account_status, last_login_at)
             VALUES (:e, NOW(), :r, :as, NOW())"
        )->execute([':e' => $email, ':r' => $role, ':as' => $accountStatus]);
        $uid = (int) $db->lastInsertId();
        $db->prepare(
            "INSERT INTO user_auth_identities (user_id, provider, provider_uid, last_used_at)
             VALUES (:u, 'email', :uid, NOW())"
        )->execute([':u' => $uid, ':uid' => $email]);
        return $uid;
    };

    $mkEmployer = function (int $userId, string $company, int $municipalityId, string $verified) use ($db, $putFixture): int {
        $permit = $putFixture('storage/documents/', 'pdf')['name'];
        $db->prepare(
            "INSERT INTO employers
                (user_id, company_name, contact_person, company_email, company_phone,
                 municipality_id, industry, company_size, company_description,
                 business_permit_file, verified_status, verified_at)
             VALUES
                (:u, :c, :cp, :ce, :phone, :m, :ind, :size, :desc,
                 :permit, :vs, :vat)"
        )->execute([
            ':u' => $userId, ':c' => $company, ':cp' => 'HR Office',
            ':ce' => 'hr' . preg_replace('/[^a-z]/', '', strtolower($company)) . DEMO_DOMAIN,
            ':phone' => '0917' . str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT),
            ':m' => $municipalityId, ':ind' => 'Demo', ':size' => '11-50',
            ':desc' => $company . ' — demonstration employer.',
            ':permit' => $permit,
            ':vs' => $verified,
            ':vat' => $verified === 'Verified' ? date('Y-m-d H:i:s') : null,
        ]);
        return (int) $db->lastInsertId();
    };

    $mkResume = function (int $userId, int $jobseekerId) use ($db, $putFixture): string {
        $f = $putFixture('storage/uploads/resumes/', 'pdf');
        $db->prepare(
            "INSERT INTO resume_uploads
                (user_id, jobseeker_id, stored_filename, original_filename, file_hash,
                 mime_type, file_size_bytes, parse_status)
             VALUES (:u, :j, :sf, :of, :fh, :mt, :sz, 'pending')"
        )->execute([
            ':u'  => $userId, ':j' => $jobseekerId,
            ':sf' => $f['name'], ':of' => 'resume.pdf',
            ':fh' => hash('sha256', $f['bytes']),
            ':mt' => $f['mime'], ':sz' => strlen($f['bytes']),
        ]);
        return $f['name'];
    };

    $mkSeeker = function (int $userId, string $first, string $last, ?int $homeMunicipalityId, int $completeness) use ($db): int {
        $db->prepare(
            "INSERT INTO job_seekers
                (user_id, first_name, last_name, home_municipality_id,
                 profile_visibility, profile_completeness)
             VALUES (:u, :f, :l, :h, 'Public', :pc)"
        )->execute([
            ':u' => $userId, ':f' => $first, ':l' => $last,
            ':h' => $homeMunicipalityId, ':pc' => $completeness,
        ]);
        $jid = (int) $db->lastInsertId();

        $db->prepare(
            "INSERT INTO job_preferences (jobseeker_id, desired_job_type, preferred_work_setup, expected_salary)
             VALUES (:j, 'Full-time', 'On-site', 18000.00)"
        )->execute([':j' => $jid]);
        $db->prepare(
            "INSERT INTO education (jobseeker_id, degree_level, school_name, year_graduated)
             VALUES (:j, 'Bachelor', 'Nueva Ecija University of Science and Technology', 2021)"
        )->execute([':j' => $jid]);
        $db->prepare(
            "INSERT INTO work_experience (jobseeker_id, job_title, company_name, start_date, end_date, job_description)
             VALUES (:j, 'Assistant', 'Previous Employer', '2022-01-01', '2024-01-01', 'Demo experience row.')"
        )->execute([':j' => $jid]);
        return $jid;
    };

    $addSkills = function (int $jobseekerId, array $skillProficiency) use ($db): void {
        $stmt = $db->prepare(
            "INSERT INTO jobseeker_skills (jobseeker_id, skill_id, proficiency_level)
             VALUES (:j, :s, :p)"
        );
        foreach ($skillProficiency as $skillId => $level) {
            $stmt->execute([':j' => $jobseekerId, ':s' => $skillId, ':p' => $level]);
        }
    };

    $addPreferredLocations = function (int $jobseekerId, array $municipalityIds) use ($db): void {
        $stmt = $db->prepare(
            "INSERT INTO preferred_work_locations (jobseeker_id, municipality_id) VALUES (:j, :m)"
        );
        foreach ($municipalityIds as $m) {
            $stmt->execute([':j' => $jobseekerId, ':m' => $m]);
        }
    };

    $mkJob = function (int $employerId, string $title, int $municipalityId, string $status, string $postedAt, array $mandatory, array $preferred) use ($db): int {
        $db->prepare(
            "INSERT INTO job_postings
                (employer_id, job_title, job_description, min_years_experience,
                 employment_type, work_arrangement, municipality_id, date_posted, job_status)
             VALUES (:e, :t, :d, 1, 'Full-time', 'On-site', :m, :dp, :st)"
        )->execute([
            ':e' => $employerId, ':t' => $title,
            ':d' => $title . ' — demonstration vacancy.',
            ':m' => $municipalityId, ':dp' => $postedAt, ':st' => $status,
        ]);
        $jobId = (int) $db->lastInsertId();
        $stmt = $db->prepare(
            "INSERT INTO job_required_skills (job_id, skill_id, requirement_type) VALUES (:j, :s, :rt)"
        );
        foreach ($mandatory as $s) {
            $stmt->execute([':j' => $jobId, ':s' => $s, ':rt' => 'Mandatory']);
        }
        foreach ($preferred as $s) {
            $stmt->execute([':j' => $jobId, ':s' => $s, ':rt' => 'Preferred']);
        }
        return $jobId;
    };

    // ---------------------------------------------------------------- pending skill
    $db->prepare(
        "INSERT INTO master_skills (category_id, skill_name, status) VALUES (1, ?, 'pending')"
    )->execute([PENDING_SKILL]);
    $graphqlId = (int) $db->lastInsertId();

    // ---------------------------------------------------------------- employers
    $e1User = $mkUser('employer.guimba', 'employer');
    $e2User = $mkUser('employer.cabanatuan', 'employer');
    // A Verified employer whose USERS row is Suspended — the document gateway
    // must refuse to serve applicant documents to it even though the ownership
    // JOIN would otherwise pass (verify_documents.php case 4f).
    $e3User = $mkUser('employer.suspended', 'employer', 'Suspended');
    $e1 = $mkEmployer($e1User, 'Guimba AgriCorp', $MUNI['guimba'], 'Verified');
    $e2 = $mkEmployer($e2User, 'Cabanatuan Tech Solutions', $MUNI['cabanatuan'], 'Pending');
    $e3 = $mkEmployer($e3User, 'Suspended Staffing Co', $MUNI['cabanatuan'], 'Verified');
    $e1Permit = (string) $db->query("SELECT business_permit_file FROM employers WHERE employer_id = $e1")->fetchColumn();
    $e2Permit = (string) $db->query("SELECT business_permit_file FROM employers WHERE employer_id = $e2")->fetchColumn();

    // ---------------------------------------------------------------- vacancies
    $t1 = '2026-08-20 09:00:00';   // Guimba AgriCorp cluster
    $t2 = '2026-08-25 09:00:00';   // Cabanatuan Tech Solutions cluster

    $jobs = [];
    // Two verified-employer jobs that a strong candidate matches identically on
    // score — the summed-proficiency-ordinal tiebreaker has to separate them.
    $jobs['farm_supervisor'] = $mkJob($e1, 'Farm Operations Supervisor', $MUNI['guimba'], 'Open', $t1,
        [17, 18], [19]);
    $jobs['poultry_lead'] = $mkJob($e1, 'Poultry Farm Lead', $MUNI['guimba'], 'Open', $t1,
        [19, 17], [18]);
    $jobs['bookkeeper'] = $mkJob($e1, 'Agri Cooperative Bookkeeper', $MUNI['cuyapo'], 'Open', $t1,
        [27, 29], [37, 35]);
    // Pending-verification employer. The web developer job carries the
    // still-unapproved skill as a Preferred requirement.
    $jobs['web_developer'] = $mkJob($e2, 'Web Developer', $MUNI['cabanatuan'], 'Open', $t2,
        [1, 3], [5, 2, $graphqlId]);
    $jobs['csr'] = $mkJob($e2, 'Customer Service Representative', $MUNI['cabanatuan'], 'Open', $t2,
        [39], [40, 41]);
    $jobs['nurse'] = $mkJob($e2, 'Staff Nurse', $MUNI['tarlac_city'], 'Open', $t2,
        [10, 9], [12, 35]);
    // Must never appear in any feed.
    $jobs['warehouse_draft'] = $mkJob($e1, 'Warehouse Assistant', $MUNI['guimba'], 'Draft', $t1,
        [21], []);
    // Closed vacancy belonging to the Suspended employer. Andrea applied to it
    // while it was open; it is Closed now so it stays out of every feed (the
    // feed is Open-only), leaving it purely as the applicant relationship that
    // proves the account_status gate in verify_documents.php case 4f.
    $jobs['suspended_closed'] = $mkJob($e3, 'Office Staff', $MUNI['cabanatuan'], 'Closed', $t2,
        [37], []);

    // ---------------------------------------------------------------- seekers
    // Maria — complete Guimba profile, prefers Cuyapo for work. An agri
    // cooperative administrator: strong on the two Guimba farm roles (exact
    // 1.0000), partial elsewhere (see the tier table in the header).
    $s1User = $mkUser('seeker.maria', 'jobseeker');
    $s1 = $mkSeeker($s1User, 'Maria', 'Santos', $MUNI['guimba'], 100);
    $addSkills($s1, [
        17 => 'Expert',        // Crop Management       — farm_supervisor / poultry_lead mandatory
        18 => 'Intermediate',  // Farm Equipment Op.    — farm_supervisor mandatory, poultry_lead preferred
        19 => 'Beginner',      // Poultry and Livestock — poultry_lead mandatory, farm_supervisor preferred
        27 => 'Intermediate',  // Bookkeeping           — bookkeeper mandatory (1 of 2 met -> partial)
        37 => 'Expert',        // MS Office             — bookkeeper preferred
        35 => 'Beginner',      // Data Entry            — bookkeeper preferred, nurse preferred (0.50 tier partial)
        39 => 'Intermediate',  // Customer Service      — csr mandatory (0 preferred met -> 0.5000 -> 0.75 tier)
    ]);
    $addPreferredLocations($s1, [$MUNI['cuyapo']]);

    // Jayson — no home municipality (neutral geo + "complete your location"),
    // strong web-developer skill set.
    $s2User = $mkUser('seeker.jayson', 'jobseeker');
    $s2 = $mkSeeker($s2User, 'Jayson', 'Dela Cruz', null, 86);
    $addSkills($s2, [1 => 'Expert', 3 => 'Expert', 5 => 'Intermediate', 2 => 'Intermediate', 7 => 'Beginner', 6 => 'Beginner']);
    $addPreferredLocations($s2, [$MUNI['guimba'], $MUNI['cabanatuan']]);

    // Andrea — complete Cabanatuan profile, deliberately never scored.
    $s3User = $mkUser('seeker.andrea', 'jobseeker');
    $s3 = $mkSeeker($s3User, 'Andrea', 'Reyes', $MUNI['cabanatuan'], 100);
    $addSkills($s3, [10 => 'Expert', 9 => 'Expert', 12 => 'Intermediate', 39 => 'Intermediate', 40 => 'Beginner']);
    $addPreferredLocations($s3, [$MUNI['cabanatuan']]);

    // ---------------------------------------------------------------- documents
    // Real files for the document-authorization harness. Maria gets a profile
    // photo and a resume; Andrea gets a resume too — she has applied to the
    // Cabanatuan employer, so Guimba AgriCorp must NOT be able to read it
    // (case 4b: "applied to a different employer" — the real attack shape).
    $mariaPhoto = $putFixture('storage/uploads/profile_photos/', 'png')['name'];
    $db->prepare("UPDATE job_seekers SET profile_photo = :p WHERE jobseeker_id = :j")
        ->execute([':p' => $mariaPhoto, ':j' => $s1]);
    $mariaResume  = $mkResume($s1User, $s1);
    $andreaResume = $mkResume($s3User, $s3);
    // A file on disk that no database row references — must never be served.
    file_put_contents(BASE_PATH . 'storage/documents/' . ORPHAN_DOCUMENT, $FIXTURE['pdf']);

    // ---------------------------------------------------------------- applications
    // Maria has already applied to the bookkeeper vacancy (Guimba AgriCorp). The
    // feed renders that one card "Applied", the rest keep a working Apply button.
    // ai_match_score is the frozen point-in-time capture (UC-05 A1) — it matches
    // Maria's 0.6000 bookkeeper final_score and never recalculates.
    $db->prepare(
        "INSERT INTO applications (jobseeker_id, job_id, ai_match_score, application_status)
         VALUES (:j, :job, 0.6000, 'Pending')"
    )->execute([':j' => $s1, ':job' => $jobs['bookkeeper']]);
    $appMariaBookkeeper = (int) $db->lastInsertId();
    // Andrea has applied to the Cabanatuan employer's CSR vacancy.
    $db->prepare(
        "INSERT INTO applications (jobseeker_id, job_id, ai_match_score, application_status)
         VALUES (:j, :job, 0.5000, 'Pending')"
    )->execute([':j' => $s3, ':job' => $jobs['csr']]);
    $appAndreaCsr = (int) $db->lastInsertId();
    // ...and, earlier, to the now-Closed Suspended Staffing Co vacancy.
    $db->prepare(
        "INSERT INTO applications (jobseeker_id, job_id, ai_match_score, application_status)
         VALUES (:j, :job, 0.5000, 'Pending')"
    )->execute([':j' => $s3, ':job' => $jobs['suspended_closed']]);
    $appAndreaSuspended = (int) $db->lastInsertId();

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    fwrite(STDERR, "seed failed, rolled back: " . $e->getMessage() . "\n");
    exit(1);
}

// -------------------------------------------------------------------- manifest
$manifest = [
    'generated_at' => date('c'),
    'employers' => [
        'guimba_agricorp'      => ['employer_id' => $e1, 'user_id' => $e1User, 'verified_status' => 'Verified', 'municipality_id' => $MUNI['guimba']],
        'cabanatuan_tech'      => ['employer_id' => $e2, 'user_id' => $e2User, 'verified_status' => 'Pending',  'municipality_id' => $MUNI['cabanatuan']],
        'suspended_staffing'   => ['employer_id' => $e3, 'user_id' => $e3User, 'verified_status' => 'Verified', 'account_status' => 'Suspended', 'municipality_id' => $MUNI['cabanatuan']],
    ],
    'jobs' => $jobs,
    'seekers' => [
        'maria'  => ['jobseeker_id' => $s1, 'user_id' => $s1User, 'email' => 'seeker.maria' . DEMO_DOMAIN,  'home_municipality_id' => $MUNI['guimba'], 'scored' => $withScores],
        'jayson' => ['jobseeker_id' => $s2, 'user_id' => $s2User, 'email' => 'seeker.jayson' . DEMO_DOMAIN, 'home_municipality_id' => null,            'scored' => $withScores],
        'andrea' => ['jobseeker_id' => $s3, 'user_id' => $s3User, 'email' => 'seeker.andrea' . DEMO_DOMAIN, 'home_municipality_id' => $MUNI['cabanatuan'], 'scored' => false],
    ],
    'pending_skill' => ['skill_id' => $graphqlId, 'skill_name' => PENDING_SKILL],
    'applications' => [
        'maria_bookkeeper' => ['seeker' => 'maria', 'job' => 'bookkeeper', 'job_id' => $jobs['bookkeeper'], 'application_id' => $appMariaBookkeeper, 'ai_match_score' => '0.6000'],
        'andrea_csr'       => ['seeker' => 'andrea', 'job' => 'csr', 'job_id' => $jobs['csr'], 'application_id' => $appAndreaCsr, 'ai_match_score' => '0.5000'],
        'andrea_suspended' => ['seeker' => 'andrea', 'job' => 'suspended_closed', 'job_id' => $jobs['suspended_closed'], 'application_id' => $appAndreaSuspended, 'ai_match_score' => '0.5000'],
    ],
    // Files on disk for scripts/verify_documents.php. 'owner' is a key into
    // employers/seekers above; the unreferenced entry is owned by no row.
    'documents' => [
        'guimba_permit'     => ['file' => $e1Permit,    'kind' => 'permit', 'owner' => 'guimba_agricorp'],
        'cabanatuan_permit' => ['file' => $e2Permit,    'kind' => 'permit', 'owner' => 'cabanatuan_tech'],
        'maria_resume'      => ['file' => $mariaResume,  'kind' => 'resume', 'owner' => 'maria'],
        'andrea_resume'     => ['file' => $andreaResume, 'kind' => 'resume', 'owner' => 'andrea'],
        'maria_photo'       => ['file' => $mariaPhoto,   'kind' => 'photo',  'owner' => 'maria'],
        'unreferenced'      => ['file' => ORPHAN_DOCUMENT, 'kind' => 'permit', 'owner' => null],
    ],
];

@mkdir(BASE_PATH . 'storage', 0775, true);
file_put_contents(
    BASE_PATH . 'storage/demo_manifest.json',
    json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
);

echo "\nemployers\n";
printf("  %-26s employer_id=%d  (Verified)\n", 'Guimba AgriCorp', $e1);
printf("  %-26s employer_id=%d  (Pending verification)\n", 'Cabanatuan Tech Solutions', $e2);
printf("  %-26s employer_id=%d  (Verified, users.account_status=Suspended)\n", 'Suspended Staffing Co', $e3);
echo "vacancies (6 Open + 1 Draft + 1 Closed)\n";
foreach ($jobs as $name => $id) {
    printf("  %-22s job_id=%d\n", $name, $id);
}
echo "seekers\n";
printf("  %-8s jobseeker_id=%d  home=Guimba       completeness=100\n", 'Maria', $s1);
printf("  %-8s jobseeker_id=%d  home=(none)       completeness=86\n", 'Jayson', $s2);
printf("  %-8s jobseeker_id=%d  home=Cabanatuan   completeness=100  (never scored)\n", 'Andrea', $s3);
echo "applications\n";
printf("  %-8s -> %-22s job_id=%d  (already applied)\n", 'Maria', 'bookkeeper', $jobs['bookkeeper']);
printf("  %-8s -> %-22s job_id=%d  (already applied)\n", 'Andrea', 'csr', $jobs['csr']);
printf("  %-8s -> %-22s job_id=%d  (applied; job now Closed)\n", 'Andrea', 'suspended_closed', $jobs['suspended_closed']);
echo "documents (storage/)\n";
printf("  %-18s %s\n", 'guimba permit', $e1Permit);
printf("  %-18s %s\n", 'cabanatuan permit', $e2Permit);
printf("  %-18s %s\n", 'maria resume', $mariaResume);
printf("  %-18s %s\n", 'andrea resume', $andreaResume);
printf("  %-18s %s\n", 'maria photo', $mariaPhoto);
printf("  %-18s %s  (referenced by no row)\n", 'unreferenced', ORPHAN_DOCUMENT);
echo "\nmanifest written to storage/demo_manifest.json\n";

// -------------------------------------------------------------------- optional T1
if ($withScores) {
    echo "\n--scores: running T1 (compute-batch) for Maria and Jayson\n";
    require BASE_PATH . 'app/services/AIEngineService.php';
    $engine = new AIEngineService();
    foreach (['Maria' => $s1, 'Jayson' => $s2] as $label => $jid) {
        try {
            $engine->recomputeForSeeker($jid);
            $n = (int) $db->query("SELECT COUNT(*) FROM job_match_scores WHERE jobseeker_id = $jid")->fetchColumn();
            echo "  {$label}: {$n} job_match_scores row(s) written\n";
        } catch (Throwable $e) {
            fwrite(STDERR, "  {$label}: T1 failed — " . $e->getMessage() . "\n");
            fwrite(STDERR, "  (is the matching engine running on " . ($_ENV['AI_ENGINE_BASE_URL'] ?? '?') . " ?)\n");
        }
    }
    echo "  Andrea: left unscored on purpose\n";
}

echo "\ndone.\n";
