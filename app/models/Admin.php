<?php

class Admin
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getSystemOverview()
    {
        $sql = "SELECT role, COUNT(user_id) as total FROM users GROUP BY role";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // 🚨 SURGICAL PATCH: 3NF Location Upgrade
    public function getSeekersByMunicipality()
    {
        // Strictly lowercase table names for Linux case-sensitivity constraints
        $sql = "SELECT m.municipality_name, COUNT(js.jobseeker_id) as seeker_count
                FROM job_seekers js
                JOIN lib_municipalities m ON js.home_municipality_id = m.municipality_id
                GROUP BY js.home_municipality_id, m.municipality_name
                ORDER BY seeker_count DESC LIMIT 10";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // 🚨 SURGICAL PATCH: Added ms.skill_name to GROUP BY for STRICT_MODE compliance
    public function getTopDemandSkills()
    {
        $sql = "SELECT ms.skill_name, COUNT(jrs.skill_id) as demand_count
                FROM job_required_skills jrs
                JOIN master_skills ms ON jrs.skill_id = ms.skill_id
                GROUP BY jrs.skill_id, ms.skill_name
                ORDER BY demand_count DESC LIMIT 5";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getPendingEmployers()
    {
        $sql = "SELECT * FROM employers WHERE verified_status = 'Pending' ORDER BY employer_id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getPendingSkills()
    {
        // Join skill_categories so the approval queue shows the real category
        // (seeker-proposed skills arrive as 'Uncategorised' and an admin
        // recategorises them here rather than them being silently filed as IT).
        // LEFT JOIN users so the queue shows who proposed the skill — the view
        // reads $ps['suggested_by']. submitted_by_user_id is nullable (FK SET
        // NULL), so the join must not drop rows whose proposer is gone.
        $sql = "SELECT ms.skill_id, ms.skill_name, ms.status, ms.submitted_by_user_id,
                       sc.category_name,
                       u.email AS suggested_by
                FROM master_skills ms
                JOIN skill_categories sc ON sc.category_id = ms.category_id
                LEFT JOIN users u ON u.user_id = ms.submitted_by_user_id
                WHERE ms.status = 'pending'
                ORDER BY ms.skill_name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Display name for the logged-in admin (nav bar). Admin identity is a real
    // peso_admins row (C-37); fall back to a neutral label if the row is absent.
    public function getAdminName($userId)
    {
        $stmt = $this->db->prepare(
            "SELECT admin_name FROM peso_admins WHERE user_id = :user_id LIMIT 1"
        );
        $stmt->execute([':user_id' => $userId]);
        $name = $stmt->fetchColumn();
        return ($name !== false && $name !== null) ? $name : 'PESO Admin';
    }

    /**
     * Records a verification decision (UC-03 steps 9, 12).
     *
     * One transaction:
     *   - employers.verified_status + verified_at
     *   - on 'Rejected' only: every Open posting by that employer ->
     *     job_status 'Suspended' (D-18 — a rejected employer must not keep
     *     live vacancies in seeker feeds wearing the pending amber badge;
     *     suspension is reversible, deletion is not). Approval never touches
     *     job_status (BR-3 — verification status is read by join, not copied).
     *
     * @return array{ok:bool,employer_user_id:int,suspended:int}|false
     *   false if the status is not a decision value, the employer row does not
     *   exist, or the write fails. The caller turns false into a real error —
     *   never a fabricated success.
     */
    public function updateEmployerVerification($employerId, $status)
    {
        if (!in_array($status, ['Verified', 'Rejected'], true)) {
            return false;
        }

        try {
            $this->db->beginTransaction();

            // The employer's user_id is needed for the downstream notification;
            // FOR UPDATE serialises concurrent decisions on the same row (E4).
            $stmt = $this->db->prepare(
                "SELECT user_id FROM employers WHERE employer_id = :id FOR UPDATE"
            );
            $stmt->execute([':id' => $employerId]);
            $employerUserId = $stmt->fetchColumn();
            if ($employerUserId === false) {
                $this->db->rollBack();
                return false;
            }

            $upd = $this->db->prepare(
                "UPDATE employers
                    SET verified_status = :status, verified_at = NOW()
                  WHERE employer_id = :id"
            );
            $upd->execute([':status' => $status, ':id' => $employerId]);

            $suspended = 0;
            if ($status === 'Rejected') {
                $sus = $this->db->prepare(
                    "UPDATE job_postings
                        SET job_status = 'Suspended'
                      WHERE employer_id = :id AND job_status = 'Open'"
                );
                $sus->execute([':id' => $employerId]);
                $suspended = $sus->rowCount();
            }

            $this->db->commit();

            return [
                'ok'               => true,
                'employer_user_id' => (int) $employerUserId,
                'suspended'        => $suspended,
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[admin] updateEmployerVerification failed: ' . $e->getMessage());
            return false;
        }
    }

    public function updateSkillStatus($skillId, $status)
    {
        $sql = "UPDATE master_skills SET status = :status WHERE skill_id = :skill_id";
        return $this->db->prepare($sql)->execute([':status' => $status, ':skill_id' => $skillId]);
    }

    public function deleteSkill($skillId)
    {
        return $this->db->prepare("DELETE FROM master_skills WHERE skill_id = ?")->execute([$skillId]);
    }

    public function getKPIs()
    {
        $seekers = (int) $this->db->query("SELECT COUNT(*) FROM job_seekers")->fetchColumn();
        $employers = (int) $this->db->query("SELECT COUNT(*) FROM employers")->fetchColumn();
        $verifiedEmployers = (int) $this->db->query("SELECT COUNT(*) FROM employers WHERE verified_status = 'Verified'")->fetchColumn();
        $pendingEmployers = (int) $this->db->query("SELECT COUNT(*) FROM employers WHERE verified_status = 'Pending'")->fetchColumn();
        $activeJobs = (int) $this->db->query("SELECT COUNT(*) FROM job_postings WHERE job_status = 'Open'")->fetchColumn();
        $pendingSkills = (int) $this->db->query("SELECT COUNT(*) FROM master_skills WHERE status = 'pending'")->fetchColumn();

        return [
            'total_seekers' => $seekers,
            'total_employers' => $employers,
            'verified_employers' => $verifiedEmployers,
            'pending_employers' => $pendingEmployers,
            'active_jobs' => $activeJobs,
            'pending_skills' => $pendingSkills,
        ];
    }

    public function getAllEmployers($search = '', $status = '')
    {
        $sql = "SELECT e.*, u.email
                FROM employers e
                LEFT JOIN users u ON u.user_id = e.user_id
                WHERE 1=1";
        $params = [];

        if ($search !== '') {
            $sql .= " AND (e.company_name LIKE :search OR e.contact_person LIKE :search OR u.email LIKE :search)";
            $params[':search'] = "%{$search}%";
        }
        if ($status !== '') {
            $sql .= " AND e.verified_status = :status";
            $params[':status'] = $status;
        }

        $sql .= " ORDER BY e.employer_id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllSeekers($search = '', $municipalityId = 0)
    {
        $sql = "SELECT js.*, u.email, m.municipality_name
                FROM job_seekers js
                JOIN users u ON u.user_id = js.user_id
                LEFT JOIN lib_municipalities m ON m.municipality_id = js.home_municipality_id
                WHERE 1=1";
        $params = [];

        if ($search !== '') {
            $sql .= " AND (js.first_name LIKE :search OR js.last_name LIKE :search OR u.email LIKE :search)";
            $params[':search'] = "%{$search}%";
        }
        if ($municipalityId > 0) {
            $sql .= " AND js.home_municipality_id = :mid";
            $params[':mid'] = (int) $municipalityId;
        }

        $sql .= " ORDER BY js.jobseeker_id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllJobPostings($search = '', $status = '')
    {
        $sql = "SELECT jp.*, e.company_name, m.municipality_name,
                       (SELECT COUNT(*) FROM applications a WHERE a.job_id = jp.job_id) as applicant_count
                FROM job_postings jp
                JOIN employers e ON e.employer_id = jp.employer_id
                LEFT JOIN lib_municipalities m ON m.municipality_id = jp.municipality_id
                WHERE 1=1";
        $params = [];

        if ($search !== '') {
            $sql .= " AND (jp.job_title LIKE :search OR e.company_name LIKE :search)";
            $params[':search'] = "%{$search}%";
        }
        if ($status !== '') {
            $sql .= " AND jp.job_status = :status";
            $params[':status'] = $status;
        }

        $sql .= " ORDER BY jp.job_id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function toggleJobStatus($jobId, $status)
    {
        if (!in_array($status, ['Open', 'Closed', 'Suspended'], true)) {
            return false;
        }
        $stmt = $this->db->prepare("UPDATE job_postings SET job_status = :status WHERE job_id = :jid");
        return $stmt->execute([':status' => $status, ':jid' => (int) $jobId]);
    }

    public function getAllMasterSkills($search = '', $categoryId = 0)
    {
        $sql = "SELECT ms.*, sc.category_name
                FROM master_skills ms
                LEFT JOIN skill_categories sc ON sc.category_id = ms.category_id
                WHERE 1=1";
        $params = [];

        if ($search !== '') {
            $sql .= " AND ms.skill_name LIKE :search";
            $params[':search'] = "%{$search}%";
        }
        if ($categoryId > 0) {
            $sql .= " AND ms.category_id = :cid";
            $params[':cid'] = (int) $categoryId;
        }

        $sql .= " ORDER BY ms.status ASC, ms.skill_name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSkillCategories()
    {
        return $this->db->query("SELECT category_id, category_name FROM skill_categories ORDER BY category_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addSkill($skillName, $categoryId)
    {
        $stmt = $this->db->prepare(
            "INSERT INTO master_skills (skill_name, category_id, status) VALUES (:name, :cid, 'approved')"
        );
        return $stmt->execute([':name' => trim($skillName), ':cid' => (int) $categoryId]);
    }

    public function getAuditLogs($limit = 50)
    {
        $sql = "SELECT a.*, a.action_type as action, u.email
                FROM audit_logs a
                LEFT JOIN users u ON u.user_id = a.user_id
                ORDER BY a.created_at DESC LIMIT :lim";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':lim', (int) $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPendingEmployersWithPermits()
    {
        $sql = "SELECT e.*, u.email as user_email
                FROM employers e
                JOIN users u ON u.user_id = e.user_id
                ORDER BY CASE 
                    WHEN e.verified_status = 'Pending' THEN 1
                    WHEN e.verification_status = 'red_flag' THEN 2
                    WHEN e.verification_status = 'green_flag' THEN 3
                    ELSE 4
                END, e.employer_id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEmployerVerificationDetail(int $employerId)
    {
        $sql = "SELECT e.*, u.email as user_email, m.municipality_name
                FROM employers e
                JOIN users u ON u.user_id = e.user_id
                LEFT JOIN lib_municipalities m ON m.municipality_id = e.municipality_id
                WHERE e.employer_id = :employer_id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':employer_id' => $employerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}