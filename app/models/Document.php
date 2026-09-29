<?php

/**
 * Ownership resolver for files served through GET /admin/view-document.
 *
 * The endpoint is DB-first: a filename is only ever served if a database row
 * owns it. This model answers two questions —
 *   1. which record owns this stored filename (permit / resume / photo), and
 *   2. has a given seeker applied to one of a given employer's jobs.
 * The controller applies the authorization policy on top of these answers.
 *
 * Thin PDO, prepared statements, lowercase table names (Linux production).
 */
class Document
{
    private $db;

    /**
     * Where each kind of file lives on disk. The stored filename from the
     * matched row is appended to one of these constants — the value from the
     * request is never part of the path.
     */
    private const DIRS = [
        'permit' => 'storage/documents/',
        'resume' => 'storage/uploads/resumes/',
        'photo'  => 'storage/uploads/profile_photos/',
    ];

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Find the record that owns $filename. Checked in order permit, resume,
     * photo; the first match wins (stored names are 16 random bytes, so a
     * cross-table collision is not a practical concern).
     *
     * @return array|null  null when no row references the file. Shape:
     *   [
     *     'kind'               => 'permit'|'resume'|'photo',
     *     'path'               => absolute path built from a hardcoded dir,
     *     'owner_user_id'      => int,
     *     'owner_jobseeker_id' => int|null,
     *   ]
     */
    public function findByStoredFilename(string $filename): ?array
    {
        $filename = basename($filename);
        if ($filename === '' || $filename === '.' || $filename === '..') {
            return null;
        }

        // 1. Employer business permit
        $stmt = $this->db->prepare(
            "SELECT user_id FROM employers WHERE business_permit_file = :f LIMIT 1"
        );
        $stmt->execute([':f' => $filename]);
        if ($row = $stmt->fetch()) {
            return $this->row('permit', $filename, (int) $row['user_id'], null);
        }

        // 2. Uploaded resume
        $stmt = $this->db->prepare(
            "SELECT user_id, jobseeker_id FROM resume_uploads WHERE stored_filename = :f LIMIT 1"
        );
        $stmt->execute([':f' => $filename]);
        if ($row = $stmt->fetch()) {
            return $this->row(
                'resume',
                $filename,
                (int) $row['user_id'],
                $row['jobseeker_id'] !== null ? (int) $row['jobseeker_id'] : null
            );
        }

        // 3. Seeker profile photo
        $stmt = $this->db->prepare(
            "SELECT user_id, jobseeker_id FROM job_seekers WHERE profile_photo = :f LIMIT 1"
        );
        $stmt->execute([':f' => $filename]);
        if ($row = $stmt->fetch()) {
            return $this->row('photo', $filename, (int) $row['user_id'], (int) $row['jobseeker_id']);
        }

        return null;
    }

    /**
     * True when $jobseekerId has an application to any job owned by the
     * employer whose account is $employerUserId. This is the ownership JOIN
     * that lets an employer view an applicant's documents — and only an
     * applicant's, never another employer's permit.
     */
    public function seekerAppliedToEmployerUser(int $jobseekerId, int $employerUserId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1
               FROM applications a
               JOIN job_postings jp ON jp.job_id = a.job_id
               JOIN employers e     ON e.employer_id = jp.employer_id
              WHERE a.jobseeker_id = :jsid
                AND e.user_id = :euid
              LIMIT 1"
        );
        $stmt->execute([':jsid' => $jobseekerId, ':euid' => $employerUserId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * users.account_status for one account, or null if the row is gone.
     * The controller requires 'Active' for every non-admin request — a
     * suspended employer must not be able to pull applicant documents.
     */
    public function accountStatus(int $userId): ?string
    {
        $stmt = $this->db->prepare("SELECT account_status FROM users WHERE user_id = :uid LIMIT 1");
        $stmt->execute([':uid' => $userId]);
        $status = $stmt->fetchColumn();
        return $status === false ? null : (string) $status;
    }

    private function row(string $kind, string $filename, int $ownerUserId, ?int $ownerJobseekerId): array
    {
        return [
            'kind'               => $kind,
            'path'               => BASE_PATH . self::DIRS[$kind] . $filename,
            'owner_user_id'      => $ownerUserId,
            'owner_jobseeker_id' => $ownerJobseekerId,
        ];
    }

    /** Absolute path of the storage directory for a kind (for the realpath fence). */
    public static function storageDir(string $kind): string
    {
        return BASE_PATH . self::DIRS[$kind];
    }
}
