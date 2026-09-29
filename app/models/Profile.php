<?php

require_once BASE_PATH . 'app/helpers/Audit.php';

class Profile
{
    private $db;

    /**
     * Profile completeness is scored over this many equally weighted signals.
     * See computeCompleteness() and M2 spec §6.6.
     */
    private const COMPLETENESS_SIGNALS = 7;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    // =========================================================================
    // LOOKUPS
    // =========================================================================

    public function getAllApprovedSkills()
    {
        $stmt = $this->db->prepare(
            "SELECT skill_id, skill_name FROM master_skills
             WHERE status = 'approved' ORDER BY skill_name ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Home / preferred location source for the seeker builder.
     * province_name lives on lib_provinces in schema v2, not on
     * lib_municipalities — the join is mandatory.
     */
    public function getMunicipalities()
    {
        $stmt = $this->db->prepare(
            "SELECT m.municipality_id, m.municipality_name, p.province_name
             FROM lib_municipalities m
             JOIN lib_provinces p ON m.province_id = p.province_id
             ORDER BY p.province_name ASC, m.municipality_name ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Barangays for one municipality — feeds the municipality -> barangay
     * cascade on the builder (served as JSON by ProfileController::barangays).
     * lib_barangays is the schema-v2 PSGC table; this is not the dropped
     * flat `barangays` table (C-32).
     */
    public function getBarangays($municipalityId)
    {
        $stmt = $this->db->prepare(
            "SELECT barangay_id, barangay_name FROM lib_barangays
             WHERE municipality_id = :mid ORDER BY barangay_name ASC"
        );
        $stmt->execute([':mid' => (int) $municipalityId]);
        return $stmt->fetchAll();
    }

    // =========================================================================
    // SEEKER PROFILE READ (full graph, for builder pre-fill)
    // =========================================================================

    /**
     * The job_seekers row plus every child collection the builder needs to
     * re-populate. Returns null when the seeker has no profile row yet.
     */
    public function getSeekerProfile($userId)
    {
        $stmt = $this->db->prepare(
            "SELECT js.*, u.email, jp.desired_job_type, jp.preferred_work_setup, jp.expected_salary
             FROM job_seekers js
             JOIN users u ON u.user_id = js.user_id
             LEFT JOIN job_preferences jp ON jp.jobseeker_id = js.jobseeker_id
             WHERE js.user_id = :uid LIMIT 1"
        );
        $stmt->execute([':uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            // If job_seekers row doesn't exist yet, return basic signup user details
            $uStmt = $this->db->prepare("SELECT user_id, email FROM users WHERE user_id = :uid LIMIT 1");
            $uStmt->execute([':uid' => $userId]);
            $userRow = $uStmt->fetch(PDO::FETCH_ASSOC);
            return $userRow ? ['user_id' => $userRow['user_id'], 'email' => $userRow['email']] : null;
        }

        $jid = (int) $row['jobseeker_id'];

        $edu = $this->db->prepare(
            "SELECT degree_level, school_name, year_graduated
             FROM education WHERE jobseeker_id = :jid ORDER BY education_id"
        );
        $edu->execute([':jid' => $jid]);
        $row['education'] = $edu->fetchAll(PDO::FETCH_ASSOC);

        // Dates rendered back to YYYY-MM for the builder's <input type="month">.
        $exp = $this->db->prepare(
            "SELECT job_title, company_name,
                    DATE_FORMAT(start_date, '%Y-%m') AS start_date,
                    DATE_FORMAT(end_date,   '%Y-%m') AS end_date,
                    job_description
             FROM work_experience WHERE jobseeker_id = :jid ORDER BY experience_id"
        );
        $exp->execute([':jid' => $jid]);
        $row['experience'] = $exp->fetchAll(PDO::FETCH_ASSOC);

        $sk = $this->db->prepare(
            "SELECT ms.skill_id, ms.skill_name, ms.status
             FROM jobseeker_skills jss
             JOIN master_skills ms ON ms.skill_id = jss.skill_id
             WHERE jss.jobseeker_id = :jid ORDER BY ms.skill_name"
        );
        $sk->execute([':jid' => $jid]);
        $row['skills'] = $sk->fetchAll(PDO::FETCH_ASSOC);

        $loc = $this->db->prepare(
            "SELECT municipality_id FROM preferred_work_locations WHERE jobseeker_id = :jid"
        );
        $loc->execute([':jid' => $jid]);
        $row['preferred_municipality_ids'] =
            array_map('intval', array_column($loc->fetchAll(PDO::FETCH_ASSOC), 'municipality_id'));

        $res = $this->db->prepare(
            "SELECT stored_filename, original_filename, created_at
             FROM resume_uploads WHERE user_id = :uid ORDER BY created_at DESC LIMIT 1"
        );
        $res->execute([':uid' => $userId]);
        $row['resume'] = $res->fetch(PDO::FETCH_ASSOC) ?: null;

        return $row;
    }

    public function getEmployerProfile($userId)
    {
        $sql = "SELECT * FROM employers WHERE user_id = :user_id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getJobseekerId($userId)
    {
        $sql = "SELECT jobseeker_id FROM job_seekers WHERE user_id = :user_id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch();
        return $result ? $result['jobseeker_id'] : false;
    }

    // =========================================================================
    // UNIFIED SEEKER TRANSACTION (M2 §5.1)
    // =========================================================================

    public function saveCompleteSeekerProfile($payload)
    {
        try {
            $this->db->beginTransaction();

            $jobseekerId = $this->getJobseekerId($payload['user_id']);

            $barangayId = $this->resolveBarangay(
                $payload['barangay_id'] ?? null,
                $payload['home_municipality_id'] ?? null
            );
            $visibility = in_array($payload['visibility'] ?? '', ['Public', 'Private'], true)
                ? $payload['visibility'] : 'Public';

            // 0. Upsert identity + home location
            if (!$jobseekerId) {
                $stmt = $this->db->prepare(
                    "INSERT INTO job_seekers
                        (user_id, first_name, last_name, home_municipality_id, barangay_id,
                         profile_visibility, profile_photo)
                     VALUES
                        (:user_id, :first_name, :last_name, :home_mun_id, :barangay_id,
                         :visibility, :photo)"
                );
                $stmt->execute([
                    ':user_id'     => $payload['user_id'],
                    ':first_name'  => $payload['first_name'],
                    ':last_name'   => $payload['last_name'],
                    ':home_mun_id' => $payload['home_municipality_id'],
                    ':barangay_id' => $barangayId,
                    ':visibility'  => $visibility,
                    ':photo'       => $payload['profile_photo'],
                ]);
                $jobseekerId = (int) $this->db->lastInsertId();
            } else {
                $stmt = $this->db->prepare(
                    "UPDATE job_seekers SET
                        first_name = COALESCE(NULLIF(:first_name, ''), first_name),
                        last_name = COALESCE(NULLIF(:last_name, ''), last_name),
                        home_municipality_id = COALESCE(:home_mun_id, home_municipality_id),
                        barangay_id = COALESCE(:barangay_id, barangay_id),
                        profile_visibility = COALESCE(:visibility, profile_visibility),
                        profile_photo = COALESCE(:photo, profile_photo)
                     WHERE jobseeker_id = :jobseeker_id"
                );
                $stmt->execute([
                    ':first_name'   => $payload['first_name'],
                    ':last_name'    => $payload['last_name'],
                    ':home_mun_id'  => $payload['home_municipality_id'],
                    ':barangay_id'  => $barangayId,
                    ':visibility'   => $visibility,
                    ':photo'        => $payload['profile_photo'],
                    ':jobseeker_id' => $jobseekerId,
                ]);
            }

            // 1. Job preferences (primary key is jobseeker_id)
            $pref = $payload['preferences'];
            $jobType = in_array($pref['desired_job_type'] ?? '', ['Full-time', 'Part-time', 'Contract', 'Internship'], true)
                ? $pref['desired_job_type'] : null;
            $setup = in_array($pref['preferred_work_setup'] ?? '', ['On-site', 'Remote', 'Hybrid'], true)
                ? $pref['preferred_work_setup'] : null;

            $exists = $this->db->prepare("SELECT jobseeker_id FROM job_preferences WHERE jobseeker_id = :jid LIMIT 1");
            $exists->execute([':jid' => $jobseekerId]);
            $sqlPref = $exists->fetch()
                ? "UPDATE job_preferences SET desired_job_type = :jt, expected_salary = :salary, preferred_work_setup = :setup WHERE jobseeker_id = :jid"
                : "INSERT INTO job_preferences (jobseeker_id, desired_job_type, expected_salary, preferred_work_setup) VALUES (:jid, :jt, :salary, :setup)";
            $this->db->prepare($sqlPref)->execute([
                ':jid'    => $jobseekerId,
                ':jt'     => $jobType,
                ':salary' => $pref['expected_salary'],
                ':setup'  => $setup,
            ]);

            // 1.5 Preferred work locations — wipe and replace
            $this->db->prepare("DELETE FROM preferred_work_locations WHERE jobseeker_id = ?")->execute([$jobseekerId]);
            $prefLocIds = [];
            if (!empty($pref['preferred_municipality_ids']) && is_array($pref['preferred_municipality_ids'])) {
                $prefLocIds = array_values(array_unique(array_map('intval', $pref['preferred_municipality_ids'])));
                $ins = $this->db->prepare("INSERT INTO preferred_work_locations (jobseeker_id, municipality_id) VALUES (:jid, :mid)");
                foreach ($prefLocIds as $mid) {
                    if ($mid > 0) {
                        $ins->execute([':jid' => $jobseekerId, ':mid' => $mid]);
                    }
                }
            }

            // 2. Education — wipe and replace
            $this->db->prepare("DELETE FROM education WHERE jobseeker_id = ?")->execute([$jobseekerId]);
            $eduCount = 0;
            if (!empty($payload['education'])) {
                $ins = $this->db->prepare("INSERT INTO education (jobseeker_id, degree_level, school_name, year_graduated) VALUES (:jid, :degree, :school, :year)");
                foreach ($payload['education'] as $e) {
                    $year = (isset($e['year_graduated']) && $e['year_graduated'] !== '' && (int) $e['year_graduated'] > 0)
                        ? (int) $e['year_graduated'] : null;
                    $ins->execute([
                        ':jid'    => $jobseekerId,
                        ':degree' => $e['degree_level'],
                        ':school' => $e['school_name'],
                        ':year'   => $year,
                    ]);
                    $eduCount++;
                }
            }

            // 3. Work experience — wipe and replace
            $this->db->prepare("DELETE FROM work_experience WHERE jobseeker_id = ?")->execute([$jobseekerId]);
            $expCount = 0;
            if (!empty($payload['experience'])) {
                $ins = $this->db->prepare("INSERT INTO work_experience (jobseeker_id, job_title, company_name, start_date, end_date, job_description) VALUES (:jid, :title, :company, :start, :end, :desc)");
                foreach ($payload['experience'] as $x) {
                    $ins->execute([
                        ':jid'     => $jobseekerId,
                        ':title'   => $x['job_title'],
                        ':company' => $x['company_name'],
                        ':start'   => $this->normalizeMonth($x['start_date'] ?? null),
                        ':end'     => $this->normalizeMonth($x['end_date'] ?? null),
                        ':desc'    => $x['job_description'] ?? '',
                    ]);
                    $expCount++;
                }
            }

            // 4. Skills — standard and custom skills are automatically approved for AI matching.
            $finalSkillIds = [];
            $checkSkill = $this->db->prepare("SELECT 1 FROM master_skills WHERE skill_id = :id LIMIT 1");
            foreach (($payload['standard_skills'] ?? []) as $sid) {
                $sid = (int) $sid;
                if ($sid <= 0) {
                    continue;
                }
                $checkSkill->execute([':id' => $sid]);
                if ($checkSkill->fetchColumn()) {
                    $finalSkillIds[] = $sid;
                }
            }

            if (!empty($payload['custom_skills'])) {
                $uncategorised = $this->uncategorisedCategoryId();
                $insSkill = $this->db->prepare(
                    "INSERT INTO master_skills (category_id, skill_name, status, submitted_by_user_id)
                     VALUES (:cat, :name, 'approved', :uid)
                     ON DUPLICATE KEY UPDATE status = 'approved'"
                );
                $findSkill = $this->db->prepare("SELECT skill_id FROM master_skills WHERE skill_name = :name LIMIT 1");
                foreach ($payload['custom_skills'] as $name) {
                    $name = trim($name);
                    if ($name === '') {
                        continue;
                    }
                    $insSkill->execute([':cat' => $uncategorised, ':name' => $name, ':uid' => $payload['user_id']]);
                    $findSkill->execute([':name' => $name]);
                    $r = $findSkill->fetch();
                    if ($r) {
                        $finalSkillIds[] = (int) $r['skill_id'];
                    }
                }
            }
            $finalSkillIds = array_values(array_unique($finalSkillIds));

            $this->db->prepare("DELETE FROM jobseeker_skills WHERE jobseeker_id = ?")->execute([$jobseekerId]);
            if ($finalSkillIds) {
                $link = $this->db->prepare("INSERT INTO jobseeker_skills (jobseeker_id, skill_id, proficiency_level) VALUES (:jid, :sid, 'Intermediate')");
                foreach ($finalSkillIds as $sid) {
                    $link->execute([':jid' => $jobseekerId, ':sid' => $sid]);
                }
            }

            // 5. Profile completeness — the fourth feed-ordering tiebreaker (M2 §6.6)
            $completeness = $this->computeCompleteness([
                'first_name'             => $payload['first_name'],
                'last_name'              => $payload['last_name'],
                'home_municipality_id'   => $payload['home_municipality_id'],
                'pref_loc_count'         => count($prefLocIds),
                'edu_count'              => $eduCount,
                'exp_count'              => $expCount,
                'no_experience_declared' => !empty($payload['no_experience_declared']),
                'skill_count'            => count($finalSkillIds),
                'desired_job_type'       => $jobType,
            ]);
            $this->db->prepare("UPDATE job_seekers SET profile_completeness = :pc WHERE jobseeker_id = :jid")
                     ->execute([':pc' => $completeness, ':jid' => $jobseekerId]);

            // 6. Activate the account (M2 §5.1 — releases AuthGuard::requireActiveProfile)
            $this->db->prepare("UPDATE users SET account_status = 'Active' WHERE user_id = :uid")
                     ->execute([':uid' => $payload['user_id']]);

            // 7. Link any pending resume_uploads rows for this user to jobseeker_id
            $this->db->prepare("UPDATE resume_uploads SET jobseeker_id = :jid WHERE user_id = :uid AND jobseeker_id IS NULL")
                     ->execute([':jid' => $jobseekerId, ':uid' => $payload['user_id']]);

            $this->db->commit();
            return true;

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[profile] saveCompleteSeekerProfile failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Record a resume upload row in resume_uploads table (schema v2).
     */
    public function saveResumeUpload(
        int $userId,
        string $storedFilename,
        string $originalFilename,
        string $fileHash,
        string $mimeType,
        int $fileSizeBytes,
        string $parseStatus = 'parsed',
        ?array $parsedPayload = null,
        ?string $parseError = null,
        string $parserVersion = '1.0.0',
        ?int $jobseekerId = null
    ): int {
        $stmt = $this->db->prepare(
            "INSERT INTO resume_uploads
                (user_id, jobseeker_id, stored_filename, original_filename, file_hash,
                 mime_type, file_size_bytes, parse_status, parsed_payload, parse_error, parser_version)
             VALUES
                (:uid, :jid, :sf, :of, :hash, :mime, :size, :status, :payload, :err, :ver)"
        );
        $stmt->execute([
            ':uid'     => $userId,
            ':jid'     => $jobseekerId,
            ':sf'      => $storedFilename,
            ':of'      => $originalFilename,
            ':hash'    => $fileHash,
            ':mime'    => $mimeType,
            ':size'    => $fileSizeBytes,
            ':status'  => $parseStatus,
            ':payload' => $parsedPayload !== null ? json_encode($parsedPayload) : null,
            ':err'     => $parseError,
            ':ver'     => $parserVersion,
        ]);
        return (int) $this->db->lastInsertId();
    }

    // =========================================================================
    // SEEKER TRANSACTION HELPERS
    // =========================================================================

    /** '2020-03' -> '2020-03-01'; '' or malformed -> null; full date passes through. */
    private function normalizeMonth($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            return $value . '-01';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        return null;
    }

    /** Keep a barangay only if it genuinely belongs to the chosen municipality. */
    private function resolveBarangay($barangayId, $municipalityId)
    {
        $barangayId = (int) $barangayId;
        $municipalityId = (int) $municipalityId;
        if ($barangayId <= 0 || $municipalityId <= 0) {
            return null;
        }
        $stmt = $this->db->prepare(
            "SELECT 1 FROM lib_barangays WHERE barangay_id = :b AND municipality_id = :m LIMIT 1"
        );
        $stmt->execute([':b' => $barangayId, ':m' => $municipalityId]);
        return $stmt->fetchColumn() ? $barangayId : null;
    }

    private function uncategorisedCategoryId()
    {
        $stmt = $this->db->prepare("SELECT category_id FROM skill_categories WHERE category_name = 'Uncategorised' LIMIT 1");
        $stmt->execute();
        $id = $stmt->fetchColumn();
        if ($id === false) {
            throw new RuntimeException("skill_categories is missing the 'Uncategorised' row — run the schema v2 seed");
        }
        return (int) $id;
    }

    /**
     * Profile completeness, 0-100 (M2 §6.6). Seven equally weighted signals,
     * none cosmetic or fairness-sensitive:
     *   1. first and last name present
     *   2. home municipality set
     *   3. at least one preferred work location  (feeds the geo multiplier)
     *   4. at least one education entry
     *   5. work experience resolved: >=1 entry OR "no experience" declared
     *   6. at least one skill
     *   7. desired job type set
     */
    private function computeCompleteness(array $s): int
    {
        $met = 0;
        $met += (trim((string) $s['first_name']) !== '' && trim((string) $s['last_name']) !== '') ? 1 : 0;
        $met += !empty($s['home_municipality_id']) ? 1 : 0;
        $met += ($s['pref_loc_count'] > 0) ? 1 : 0;
        $met += ($s['edu_count'] > 0) ? 1 : 0;
        $met += ($s['exp_count'] > 0 || $s['no_experience_declared']) ? 1 : 0;
        $met += ($s['skill_count'] > 0) ? 1 : 0;
        $met += !empty($s['desired_job_type']) ? 1 : 0;
        return (int) round($met / self::COMPLETENESS_SIGNALS * 100);
    }

    // =========================================================================
    // EMPLOYER TRANSACTION (M2 §5.1 / §8) — /build-profile create + edit
    // =========================================================================

    /** company_size ENUM members in schema v2. '' / unknown -> NULL, never ''. */
    private const COMPANY_SIZES = ['1-10', '11-50', '51-200', '201-500', '500+'];

    private function whitelistCompanySize($value)
    {
        return in_array($value, self::COMPANY_SIZES, true) ? $value : null;
    }

    /**
     * First-time employer profile: one transaction that writes the employers
     * identity row (verified_status defaults to 'Pending') and flips
     * users.account_status to 'Active' so AuthGuard::requireActiveProfile
     * releases. Mirrors saveCompleteSeekerProfile()'s shape. Returns true on
     * commit, false on any failure (the caller unlinks the uploaded permit).
     *
     * business_permit_file is NOT NULL and is validated by the controller
     * before we get here — a missing permit is a form error, never a DB error.
     */
    public function createEmployerProfile($payload)
    {
        try {
            $this->db->beginTransaction();

            $municipalityId = (int) $payload['municipality_id'];
            $barangayId = $this->resolveBarangay($payload['barangay_id'] ?? null, $municipalityId);

            $stmt = $this->db->prepare(
                "INSERT INTO employers
                    (user_id, company_name, contact_person, company_email, company_phone,
                     street_name, barangay_id, municipality_id, industry, company_size,
                     company_description, company_logo, company_website, business_permit_file,
                     permit_file_path, verification_status, ai_feedback, extracted_permit_data, admin_decision,
                     verified_status)
                 VALUES
                    (:user_id, :company_name, :contact_person, :company_email, :company_phone,
                     :street_name, :barangay_id, :municipality_id, :industry, :company_size,
                     :company_description, :company_logo, :company_website, :business_permit_file,
                     :permit_file_path, :verification_status, :ai_feedback, :extracted_permit_data, 'pending',
                     'Pending')"
            );
            $stmt->execute([
                ':user_id'               => (int) $payload['user_id'],
                ':company_name'          => $payload['company_name'],
                ':contact_person'        => $payload['contact_person'],
                ':company_email'         => $payload['company_email'],
                ':company_phone'         => $payload['company_phone'],
                ':street_name'           => $payload['street_name'] !== '' ? $payload['street_name'] : null,
                ':barangay_id'           => $barangayId,
                ':municipality_id'       => $municipalityId,
                ':industry'              => $payload['industry'] !== '' ? $payload['industry'] : null,
                ':company_size'          => $this->whitelistCompanySize($payload['company_size'] ?? null),
                ':company_description'   => $payload['company_description'] !== '' ? $payload['company_description'] : null,
                ':company_logo'          => $payload['company_logo'] ?? null,
                ':company_website'       => $payload['company_website'] !== '' ? $payload['company_website'] : null,
                ':business_permit_file'  => $payload['business_permit_file'],
                ':permit_file_path'      => $payload['permit_file_path'] ?? ($payload['business_permit_file'] ?? null),
                ':verification_status'   => $payload['verification_status'] ?? 'pending',
                ':ai_feedback'           => $payload['ai_feedback'] ?? null,
                ':extracted_permit_data' => $payload['extracted_permit_data'] ?? null,
            ]);

            // Activate the account (M2 §5.1 — releases AuthGuard::requireActiveProfile).
            $this->db->prepare("UPDATE users SET account_status = 'Active' WHERE user_id = :uid")
                     ->execute([':uid' => (int) $payload['user_id']]);

            $this->db->commit();
            return true;

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[profile] createEmployerProfile failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Edit an existing employer profile. The permit is not touched here (it is
     * replaced only by a PESO admin), but every other field — including the
     * company name, street address and municipality — is editable. barangay_id
     * is re-validated against the municipality being saved.
     *
     * If the company name changes on an employer that is already 'Verified',
     * an audit_logs row is written. Whether that should also drop the employer
     * back to 'Pending' for re-verification is an open policy question for the
     * admin task (Q-22) — this method does not change verified_status.
     *
     * Returns true on success.
     */
    public function saveEmployerProfile($payload)
    {
        try {
            $current = $this->getEmployerProfile($payload['user_id']);
            if (!$current) {
                return false;
            }

            $companyName = trim((string) ($payload['company_name'] ?? ''));
            if ($companyName === '') {
                $companyName = $current['company_name'];   // never blank the NOT NULL column
            }
            $municipalityId = (int) ($payload['municipality_id'] ?? 0);
            if ($municipalityId <= 0) {
                $municipalityId = (int) $current['municipality_id'];
            }
            $barangayId = $this->resolveBarangay($payload['barangay_id'] ?? null, $municipalityId);

            $sql = "UPDATE employers SET
                        company_name = :company_name,
                        contact_person = :contact_person,
                        company_email = :company_email,
                        company_phone = :company_phone,
                        street_name = :street_name,
                        barangay_id = :barangay_id,
                        municipality_id = :municipality_id,
                        industry = :industry,
                        company_size = :company_size,
                        company_description = :company_description,
                        company_logo = :company_logo,
                        company_website = :company_website
                    WHERE user_id = :user_id";

            $stmt = $this->db->prepare($sql);
            $ok = $stmt->execute([
                ':company_name'        => $companyName,
                ':contact_person'      => $payload['contact_person'],
                ':company_email'       => $payload['company_email'],
                ':company_phone'       => $payload['company_phone'],
                ':street_name'         => ($payload['street_name'] ?? '') !== '' ? $payload['street_name'] : null,
                ':barangay_id'         => $barangayId,
                ':municipality_id'     => $municipalityId,
                ':industry'            => ($payload['industry'] ?? '') !== '' ? $payload['industry'] : null,
                ':company_size'        => $this->whitelistCompanySize($payload['company_size'] ?? null),
                ':company_description' => ($payload['company_description'] ?? '') !== '' ? $payload['company_description'] : null,
                ':company_logo'        => $payload['company_logo'] ?? null,
                ':company_website'     => ($payload['company_website'] ?? '') !== '' ? $payload['company_website'] : null,
                ':user_id'             => (int) $payload['user_id'],
            ]);

            if ($ok && $companyName !== $current['company_name'] && $current['verified_status'] === 'Verified') {
                Audit::write(
                    (int) $payload['user_id'],
                    'profile_updated',
                    "Verified employer changed company name from '{$current['company_name']}' to '{$companyName}' "
                        . '(re-verification policy pending — Q-22)',
                    'employer',
                    (int) $current['employer_id']
                );
            }

            return $ok;
        } catch (Throwable $e) {
            error_log('[profile] saveEmployerProfile failed: ' . $e->getMessage());
            return false;
        }
    }
}
