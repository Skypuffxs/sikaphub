<?php

/**
 * Data access for user_auth_identities.
 *
 * Schema v2 columns:
 *   identity_id, user_id, provider ENUM('google','email'),
 *   provider_uid, last_used_at, created_at
 *   UNIQUE(provider, provider_uid), UNIQUE(user_id, provider)
 *
 * For the email provider, provider_uid stores the email address itself
 * (Google's path stores the immutable `sub` claim instead — BR-2).
 */
class AuthIdentity
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function findEmailIdentity($email)
    {
        $stmt = $this->db->prepare(
            "SELECT identity_id, user_id, last_used_at
             FROM user_auth_identities
             WHERE provider = 'email' AND provider_uid = :uid
             LIMIT 1"
        );
        $stmt->execute([':uid' => $email]);
        return $stmt->fetch();
    }

    /**
     * Link (or re-touch) an email identity for a user. Idempotent: the seeded
     * admin already owns its row, so a repeat login just refreshes last_used_at.
     * Alt flow A2 (second provider, same address) is handled by Google's path
     * inserting its own row against the same user_id.
     */
    public function linkEmailIdentity($userId, $email)
    {
        $stmt = $this->db->prepare(
            "INSERT INTO user_auth_identities (user_id, provider, provider_uid, last_used_at)
             VALUES (:user_id, 'email', :uid, NOW())
             ON DUPLICATE KEY UPDATE last_used_at = NOW()"
        );
        $stmt->execute([':user_id' => $userId, ':uid' => $email]);
    }
}
