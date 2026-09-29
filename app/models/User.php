<?php

/**
 * Passwordless user model (schema v2).
 *
 * The v2 users table is (user_id, email, email_verified_at, role,
 * account_status, last_login_at, created_at, updated_at). There is no
 * username and no password_hash. Authentication happens through
 * user_auth_identities + email_otp_codes; there is no separate registration
 * step — the first successful email verification creates the account.
 */
class User
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /** Single user row by email, or false. */
    public function findByEmail($email)
    {
        $stmt = $this->db->prepare(
            "SELECT user_id, email, role, account_status, email_verified_at, last_login_at
             FROM users
             WHERE email = :email
             LIMIT 1"
        );
        $stmt->execute([':email' => $email]);
        return $stmt->fetch();
    }

    public function findById($userId)
    {
        $stmt = $this->db->prepare(
            "SELECT user_id, email, role, account_status, email_verified_at, last_login_at
             FROM users
             WHERE user_id = :id
             LIMIT 1"
        );
        $stmt->execute([':id' => $userId]);
        return $stmt->fetch();
    }

    /**
     * Create an account on first successful email verification.
     * role stays NULL (chosen later at /select-role); account_status defaults
     * to 'Pending'; email_verified_at is stamped now.
     *
     * @return int new user_id
     */
    public function createPendingUser($email)
    {
        $stmt = $this->db->prepare(
            "INSERT INTO users (email, email_verified_at) VALUES (:email, NOW())"
        );
        $stmt->execute([':email' => $email]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Stamp email_verified_at exactly once. Every later login matches zero rows
     * and is a no-op — the timestamp records the first verification only.
     */
    public function markEmailVerified($userId)
    {
        $stmt = $this->db->prepare(
            "UPDATE users SET email_verified_at = NOW()
             WHERE user_id = :id AND email_verified_at IS NULL"
        );
        $stmt->execute([':id' => $userId]);
    }

    public function recordLogin($userId)
    {
        $stmt = $this->db->prepare(
            "UPDATE users SET last_login_at = NOW() WHERE user_id = :id"
        );
        $stmt->execute([':id' => $userId]);
    }

    /**
     * Set the account role at UC-01a. Guarded — only writes while role IS NULL,
     * so a second POST cannot flip an existing role.
     *
     * @return bool true when a row was updated
     */
    public function setRole($userId, $role)
    {
        $stmt = $this->db->prepare(
            "UPDATE users SET role = :role WHERE user_id = :id AND role IS NULL"
        );
        $stmt->execute([':role' => $role, ':id' => $userId]);
        return $stmt->rowCount() === 1;
    }
}
