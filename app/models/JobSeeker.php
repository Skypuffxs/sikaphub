<?php

class JobSeeker
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    // =========================================================================
    // V2 RELATIONAL BRIDGE
    // =========================================================================

    public function getJobseekerIdByUserId($userId)
    {
        $sql = "SELECT jobseeker_id FROM job_seekers WHERE user_id = :user_id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch();
        return $result ? $result['jobseeker_id'] : false;
    }

    public function getJobseekerId($userId)
    {
        return $this->getJobseekerIdByUserId($userId);
    }

    // =========================================================================
    // CORE DASHBOARD QUERIES (3NF UPGRADED)
    // =========================================================================

    /**
     * The seeker's recommendation feed (UC-05 steps 11-14 / M2 §5.2, §6.6).
     *
     * This method reads precomputed rows from job_match_scores and makes ZERO
     * calls to the matching service — the per-job HTTP loop that C-35 flagged
     * is gone (UC-05 BR-5). Scores are populated out of band by triggers T1
     * (profile save), T2 (employer publish), T4 (apply) and T7 (skill
     * approval); the dashboard only ever reads the cache.
     *
     * One round trip, constant in card count: the score,
     * applied-state and proficiency-ordinal are all folded into this single
     * statement; the caller adds three more flat queries (approved skill set,
     * batched job requirements, seeker context) and nothing per card.
     *
     * job_match_scores is a LEFT JOIN so an open job with no score row still
     * renders — sorted last, labelled "Match pending", never 0% (D-15, A4).
     * lib_municipalities is a LEFT JOIN so a job with a NULL municipality_id
     * is not silently dropped.
     *
     * ORDER BY is strictly by match score descending with safety tiebreakers:
     *   1. final_score present before absent (unscored jobs sort last)
     *   2. final_score descending (highest % match first to lowest %)
     *   3. employers.verified_status = 'Verified' (candidate safety tiebreaker)
     *   4. summed proficiency ordinal over matched *Mandatory* skills, desc
     *   5. date_posted descending, then job_id ascending (stable terminator)
     * §6.6 keys 4 and 5 (profile_completeness, jobseeker_id) are CONSTANT for
     * a single seeker's feed — every row is the same seeker — so they cannot
     * reorder anything and are omitted here. They are inert, not forgotten
     * (D-14).
     *
     * The proficiency-ordinal subquery correlates on jp.job_id and binds the
     * seeker id from its own placeholder (:seeker_prof). It must NOT read
     * s.job_id / s.jobseeker_id: under the LEFT JOIN those are NULL for
     * unscored jobs, which would zero the tiebreaker for exactly the rows
     * that still need it. The terminal key is jp.job_id, not s.job_id, for
     * the same reason. ATTR_EMULATE_PREPARES is false (config/Database.php),
     * so the seeker id is bound under a distinct name in each clause it
     * appears in.
     *
     * match_percentage is ROUND(final_score * 100) and stays NULL when the
     * score is absent — never coerced to 0.
     */
    public function getRecommendationFeed($jobseekerId)
    {
        $sql = "SELECT jp.job_id, jp.job_title, jp.salary_range, jp.employment_type,
                       jp.work_arrangement, jp.min_years_experience, jp.date_posted,
                       e.company_name, e.verified_status,
                       m.municipality_name,
                       s.final_score,
                       s.mandatory_met, s.mandatory_total,
                       s.preferred_met, s.preferred_total,
                       s.engine_version, s.computed_at,
                       ROUND(s.final_score * 100) AS match_percentage,
                       a.application_id, a.application_status,
                       sj.saved_job_id,
                       (
                           SELECT COALESCE(SUM(
                               CASE jss.proficiency_level
                                   WHEN 'Expert'       THEN 3
                                   WHEN 'Intermediate' THEN 2
                                   WHEN 'Beginner'     THEN 1
                                   ELSE 0
                               END), 0)
                           FROM job_required_skills jrs
                           JOIN jobseeker_skills jss
                                ON jss.skill_id = jrs.skill_id
                               AND jss.jobseeker_id = :seeker_prof
                           JOIN master_skills ms
                                ON ms.skill_id = jrs.skill_id
                               AND ms.status = 'approved'
                           WHERE jrs.job_id = jp.job_id
                             AND jrs.requirement_type = 'Mandatory'
                       ) AS matched_mandatory_prof_ordinal
                FROM job_postings jp
                JOIN employers e ON e.employer_id = jp.employer_id
                JOIN users u ON u.user_id = e.user_id
                LEFT JOIN lib_municipalities m ON m.municipality_id = jp.municipality_id
                LEFT JOIN job_match_scores s ON s.job_id = jp.job_id
                                            AND s.jobseeker_id = :seeker_join
                LEFT JOIN applications a ON a.job_id = jp.job_id
                                        AND a.jobseeker_id = :seeker_applied
                LEFT JOIN saved_jobs sj ON sj.job_id = jp.job_id
                                       AND sj.jobseeker_id = :seeker_saved
                WHERE jp.job_status = 'Open'
                  AND u.account_status NOT IN ('Suspended', 'Deactivated')
                ORDER BY (s.final_score IS NULL) ASC,
                         s.final_score DESC,
                         (e.verified_status = 'Verified') DESC,
                         matched_mandatory_prof_ordinal DESC,
                         jp.date_posted DESC,
                         jp.job_id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':seeker_prof' => $jobseekerId,
            ':seeker_join' => $jobseekerId,
            ':seeker_applied' => $jobseekerId,
            ':seeker_saved' => $jobseekerId,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * The seeker's skill ids, filtered to master_skills.status = 'approved'
     * (C-42). Pending vocabulary is stored but never counts toward a match.
     */
    public function getApprovedSkillIds($jobseekerId)
    {
        $sql = "SELECT jss.skill_id
                FROM jobseeker_skills jss
                JOIN master_skills ms ON ms.skill_id = jss.skill_id
                WHERE jss.jobseeker_id = :skills_seeker
                  AND ms.status = 'approved'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':skills_seeker' => $jobseekerId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Every required skill for the given job ids in one query — the card-level
     * matched / unmatched / awaiting-approval breakdown (§6.4 / C-42). The IN
     * list is built from individually bound placeholders; job ids are never
     * concatenated into the SQL. Returns one row per (job_id, skill_id) with
     * the master_skills name and status; the caller partitions in PHP.
     */
    public function getRequiredSkillsForJobs(array $jobIds)
    {
        if (empty($jobIds)) {
            return [];
        }
        $placeholders = [];
        $params = [];
        foreach (array_values($jobIds) as $i => $jobId) {
            $key = ':job_' . $i;
            $placeholders[] = $key;
            $params[$key] = (int) $jobId;
        }
        $sql = "SELECT jrs.job_id, jrs.skill_id, jrs.requirement_type,
                       ms.skill_name, ms.status
                FROM job_required_skills jrs
                JOIN master_skills ms ON ms.skill_id = jrs.skill_id
                WHERE jrs.job_id IN (" . implode(', ', $placeholders) . ")";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * The seeker's feed context in one query: home municipality (NULL drives
     * the "complete your location" nudge — never a score penalty, §6.3 / C-21)
     * and profile_completeness (the real value for the profile-strength meter,
     * replacing the hardcoded 90% — a hardcoded strength shown to every seeker
     * is a fabricated success in the UI).
     */
    public function getSeekerContext($jobseekerId)
    {
        $sql = "SELECT home_municipality_id, profile_completeness, profile_photo, first_name, last_name
                FROM job_seekers
                WHERE jobseeker_id = :ctx_seeker
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':ctx_seeker' => $jobseekerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // =========================================================================
    // APPLICATION TRANSACTIONS
    // =========================================================================

    public function hasAlreadyApplied($jobseekerId, $jobId)
    {
        $sql = "SELECT application_id FROM applications WHERE jobseeker_id = :jobseeker_id AND job_id = :job_id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':jobseeker_id' => $jobseekerId,
            ':job_id' => $jobId
        ]);
        return $stmt->fetch() ? true : false;
    }

    public function applyForJob($jobseekerId, $jobId, $matchScore)
    {
        try {
            $this->db->beginTransaction();

            $sql = "INSERT INTO applications (jobseeker_id, job_id, ai_match_score, application_status) 
                    VALUES (:jobseeker_id, :job_id, :ai_match_score, 'Pending')";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':jobseeker_id' => $jobseekerId,
                ':job_id' => $jobId,
                ':ai_match_score' => $matchScore
            ]);

            $this->db->commit();
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Application Transaction Failed: " . $e->getMessage());
            return false;
        }
    }

    public function getMyApplications($jobseekerId)
    {
        $sql = "SELECT 
                    a.application_id, 
                    a.application_status, 
                    a.application_date, 
                    a.ai_match_score,
                    jp.job_title, 
                    e.company_name
                FROM applications a
                JOIN job_postings jp ON a.job_id = jp.job_id
                JOIN employers e ON jp.employer_id = e.employer_id
                WHERE a.jobseeker_id = :jobseeker_id
                ORDER BY a.application_date DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':jobseeker_id' => $jobseekerId]);
        return $stmt->fetchAll();
    }

    // =========================================================================
    // SAVED JOBS (BOOKMARKS)
    // =========================================================================

    public function isJobSaved($jobseekerId, $jobId)
    {
        $stmt = $this->db->prepare("SELECT saved_job_id FROM saved_jobs WHERE jobseeker_id = :jid AND job_id = :job_id LIMIT 1");
        $stmt->execute([':jid' => $jobseekerId, ':job_id' => $jobId]);
        return $stmt->fetch() ? true : false;
    }

    public function toggleSaveJob($jobseekerId, $jobId)
    {
        $stmt = $this->db->prepare("SELECT saved_job_id FROM saved_jobs WHERE jobseeker_id = :jid AND job_id = :job_id LIMIT 1");
        $stmt->execute([':jid' => $jobseekerId, ':job_id' => $jobId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $del = $this->db->prepare("DELETE FROM saved_jobs WHERE saved_job_id = :sjid");
            $del->execute([':sjid' => $row['saved_job_id']]);
            return false;
        } else {
            $ins = $this->db->prepare("INSERT INTO saved_jobs (jobseeker_id, job_id) VALUES (:jid, :job_id)");
            $ins->execute([':jid' => $jobseekerId, ':job_id' => $jobId]);
            return true;
        }
    }

    public function getSavedJobs($jobseekerId)
    {
        $sql = "SELECT jp.job_id, jp.employer_id, jp.job_title, jp.job_description, jp.salary_range, jp.employment_type,
                       jp.work_arrangement, jp.min_years_experience, jp.date_posted,
                       e.company_name, e.company_logo, e.verified_status,
                       m.municipality_name,
                       s.final_score,
                       ROUND(s.final_score * 100) AS match_percentage,
                       a.application_id, a.application_status,
                       sj.created_at AS saved_at
                FROM saved_jobs sj
                JOIN job_postings jp ON jp.job_id = sj.job_id
                JOIN employers e ON e.employer_id = jp.employer_id
                JOIN users u ON u.user_id = e.user_id
                LEFT JOIN lib_municipalities m ON m.municipality_id = jp.municipality_id
                LEFT JOIN job_match_scores s ON s.job_id = jp.job_id AND s.jobseeker_id = :seeker_join
                LEFT JOIN applications a ON a.job_id = jp.job_id AND a.jobseeker_id = :seeker_app
                WHERE sj.jobseeker_id = :seeker_saved AND jp.job_status = 'Open'
                  AND u.account_status NOT IN ('Suspended', 'Deactivated')
                ORDER BY sj.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':seeker_saved' => $jobseekerId,
            ':seeker_join' => $jobseekerId,
            ':seeker_app' => $jobseekerId,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}