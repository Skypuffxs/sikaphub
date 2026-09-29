<?php

class Employer
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    // Fetch the Employer ID linked to the current User Session
    public function getEmployerId($userId)
    {
        $sql = "SELECT employer_id FROM employers WHERE user_id = :user_id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch();
        return $result ? $result['employer_id'] : false;
    }

    // Fetch the full employer row linked to a user_id (used for onboarding gate checks)
    public function findByUserId($userId)
    {
        $sql = "SELECT * FROM employers WHERE user_id = :user_id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetch();
    }

    // Fetch the employer's core profile row (includes verified_status)
    public function getEmployerDetails($employerId)
    {
        $sql = "SELECT * FROM employers WHERE employer_id = :employer_id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':employer_id' => $employerId]);
        return $stmt->fetch();
    }

    // Fetch all jobs posted by this employer
    public function getEmployerJobs($employerId)
    {
        $sql = "SELECT job_id, job_title, job_status, date_posted 
                FROM job_postings 
                WHERE employer_id = :employer_id 
                ORDER BY date_posted DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':employer_id' => $employerId]);
        return $stmt->fetchAll();
    }

    // Fetch applicants for a specific job, STRICTLY ordered by AI Match Score
    // SECURITY FIX: JOIN job_postings to enforce employer ownership; prevents IDOR where
    // an attacker passes an arbitrary job_id to view another company's applicants.
    public function getRankedApplicantsForJob($jobId, $employerId)
    {
        $sql = "SELECT 
                    a.application_id, 
                    a.ai_match_score, 
                    a.application_status, 
                    a.application_date,
                    js.first_name, 
                    js.last_name, 
                    js.contact_number
                FROM applications a
                JOIN job_postings jp ON a.job_id = jp.job_id
                JOIN job_seekers js ON a.jobseeker_id = js.jobseeker_id
                WHERE a.job_id = :job_id
                  AND jp.employer_id = :employer_id
                ORDER BY a.ai_match_score DESC, a.application_date ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':job_id'      => $jobId,
            ':employer_id' => $employerId
        ]);
        return $stmt->fetchAll();
    }

    // Fetch core application and seeker profile, strictly gated by Employer ID.
    //
    // a.ai_match_score is the score FROZEN at application time (UC-05 A1/A3).
    // It is what this employer evaluated and is never recalculated — do not
    // swap this for a live join to job_match_scores.
    //
    // The ownership JOIN through job_postings.employer_id is the reference
    // pattern (CLAUDE.md): an employer only ever sees an application for one of
    // their own vacancies. Never trust an app_id from the request alone.
    public function getApplicationDetails($applicationId, $employerId)
    {
        $sql = "SELECT
                    a.application_id, a.jobseeker_id, a.ai_match_score, a.application_status, a.application_date,
                    js.first_name, js.last_name, js.gender, js.contact_number, js.street_name,
                    js.profile_photo,
                    m.municipality_name,
                    jp.job_title
                FROM applications a
                JOIN job_postings jp ON a.job_id = jp.job_id
                JOIN job_seekers js ON a.jobseeker_id = js.jobseeker_id
                LEFT JOIN lib_municipalities m ON js.home_municipality_id = m.municipality_id
                WHERE a.application_id = :app_id AND jp.employer_id = :employer_id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':app_id' => $applicationId,
            ':employer_id' => $employerId
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Fetch 1:N Education History
    public function getSeekerEducation($jobseekerId)
    {
        $sql = "SELECT degree_level, school_name, year_graduated 
                FROM education 
                WHERE jobseeker_id = :jobseeker_id 
                ORDER BY year_graduated DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':jobseeker_id' => $jobseekerId]);
        return $stmt->fetchAll();
    }

    // Fetch 1:N Work Experience
    public function getSeekerExperience($jobseekerId)
    {
        $sql = "SELECT job_title, company_name, start_date, end_date, job_description 
                FROM work_experience 
                WHERE jobseeker_id = :jobseeker_id 
                ORDER BY start_date DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':jobseeker_id' => $jobseekerId]);
        return $stmt->fetchAll();
    }

    // Fetch the seeker's most recent uploaded resume, or null if they have none.
    //
    // Resumes live in resume_uploads (schema v2) — job_seekers has no resume
    // column. There is no resume-upload flow yet (UC-02 is deferred), so this
    // returns null for every real applicant today; the review view renders an
    // honest empty state in that case. Ownership is already established by the
    // caller: reviewCandidate() only reaches here after getApplicationDetails()
    // has proven the seeker applied to one of this employer's vacancies, which
    // is exactly the check the document gateway re-runs before serving the file.
    public function getSeekerResume($jobseekerId)
    {
        $sql = "SELECT stored_filename, original_filename, mime_type
                FROM resume_uploads
                WHERE jobseeker_id = :jobseeker_id
                   OR user_id = (SELECT user_id FROM job_seekers WHERE jobseeker_id = :jobseeker_id_sub)
                ORDER BY created_at DESC, upload_id DESC
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':jobseeker_id' => $jobseekerId,
            ':jobseeker_id_sub' => $jobseekerId
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // Securely update the application status
    public function updateApplicationStatus($applicationId, $employerId, $newStatus)
    {
        // We JOIN job_postings again in the UPDATE statement to enforce IDOR protection on writes!
        $sql = "UPDATE applications a
                JOIN job_postings jp ON a.job_id = jp.job_id
                SET a.application_status = :status
                WHERE a.application_id = :app_id AND jp.employer_id = :employer_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':status' => $newStatus,
            ':app_id' => $applicationId,
            ':employer_id' => $employerId
        ]);
    }

    /**
     * Fetch public employer profile details for jobseekers and guests.
     */
    public function getPublicCompanyProfile($employerId)
    {
        $sql = "SELECT e.*, m.municipality_name, p.province_name, b.barangay_name
                FROM employers e
                LEFT JOIN lib_municipalities m ON e.municipality_id = m.municipality_id
                LEFT JOIN lib_provinces p ON m.province_id = p.province_id
                LEFT JOIN lib_barangays b ON e.barangay_id = b.barangay_id
                WHERE e.employer_id = :employer_id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':employer_id' => $employerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch all active open job postings published by this employer.
     */
    public function getOpenJobsForEmployer($employerId)
    {
        $sql = "SELECT jp.*, m.municipality_name
                FROM job_postings jp
                LEFT JOIN lib_municipalities m ON jp.municipality_id = m.municipality_id
                WHERE jp.employer_id = :employer_id AND jp.job_status = 'Open'
                ORDER BY jp.date_posted DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':employer_id' => $employerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Save/update employer business permit document and AI verification result.
     */
    public function updatePermitVerification(int $employerId, string $filePath, string $verificationStatus, string $aiFeedback, ?array $extractedData): bool
    {
        $jsonExtracted = $extractedData !== null ? json_encode($extractedData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

        $sql = "UPDATE employers 
                SET business_permit_file = :permit_file,
                    permit_file_path = :permit_file_path,
                    verification_status = :verification_status,
                    ai_feedback = :ai_feedback,
                    extracted_permit_data = :extracted_permit_data,
                    admin_decision = 'pending',
                    verified_status = 'Pending'
                WHERE employer_id = :employer_id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':permit_file'           => $filePath,
            ':permit_file_path'      => $filePath,
            ':verification_status'   => $verificationStatus,
            ':ai_feedback'           => $aiFeedback,
            ':extracted_permit_data' => $jsonExtracted,
            ':employer_id'           => $employerId
        ]);
    }
}