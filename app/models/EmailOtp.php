<?php

/**
 * Data access for email_otp_codes.
 *
 * Schema v2 columns:
 *   otp_id, email, code_hash, purpose ENUM('signup','login'),
 *   attempt_count TINYINT UNSIGNED DEFAULT 0, expires_at, consumed_at,
 *   ip_address, created_at
 *
 * There is no counter column for rate limiting — the limiter counts rows in a
 * time window instead (see countRecentByEmail / countRecentByIp).
 *
 * All time arithmetic is done with the database clock (NOW() +/- INTERVAL).
 * The PHP process and MySQL run in different timezones on this host, so a
 * PHP-computed timestamp compared against NOW() would be wrong by hours.
 */
class EmailOtp
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * @param string $email
     * @param string $codeHash    password_hash() of the six-digit code
     * @param string $purpose     'signup' or 'login'
     * @param string $ip
     * @param int    $ttlMinutes  lifetime from now
     */
    public function create($email, $codeHash, $purpose, $ip, $ttlMinutes)
    {
        $stmt = $this->db->prepare(
            "INSERT INTO email_otp_codes (email, code_hash, purpose, expires_at, ip_address)
             VALUES (:email, :code_hash, :purpose, NOW() + INTERVAL :ttl MINUTE, :ip_address)"
        );
        $stmt->execute([
            ':email'      => $email,
            ':code_hash'  => $codeHash,
            ':purpose'    => $purpose,
            ':ttl'        => $ttlMinutes,
            ':ip_address' => $ip,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** Newest code for an address that is neither consumed nor expired, or false. */
    public function newestActiveForEmail($email)
    {
        $stmt = $this->db->prepare(
            "SELECT otp_id, code_hash, purpose, attempt_count, expires_at
             FROM email_otp_codes
             WHERE email = :email
               AND consumed_at IS NULL
               AND expires_at > NOW()
             ORDER BY otp_id DESC
             LIMIT 1"
        );
        $stmt->execute([':email' => $email]);
        return $stmt->fetch();
    }

    public function incrementAttempts($otpId)
    {
        $stmt = $this->db->prepare(
            "UPDATE email_otp_codes SET attempt_count = attempt_count + 1 WHERE otp_id = :id"
        );
        $stmt->execute([':id' => $otpId]);
    }

    /** Mark a code spent. Used on success and on the 5-attempt lockout (E5). */
    public function consume($otpId)
    {
        $stmt = $this->db->prepare(
            "UPDATE email_otp_codes SET consumed_at = NOW()
             WHERE otp_id = :id AND consumed_at IS NULL"
        );
        $stmt->execute([':id' => $otpId]);
    }

    public function countRecentByEmail($email, $windowMinutes)
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM email_otp_codes
             WHERE email = :email AND created_at >= NOW() - INTERVAL :w MINUTE"
        );
        $stmt->execute([':email' => $email, ':w' => $windowMinutes]);
        return (int) $stmt->fetchColumn();
    }

    public function countRecentByIp($ip, $windowMinutes)
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM email_otp_codes
             WHERE ip_address = :ip AND created_at >= NOW() - INTERVAL :w MINUTE"
        );
        $stmt->execute([':ip' => $ip, ':w' => $windowMinutes]);
        return (int) $stmt->fetchColumn();
    }
}
