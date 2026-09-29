<?php

class CSRF
{

    // 1. Generate and store the token in the active session
    public static function generateToken()
    {
        if (empty($_SESSION['csrf_token'])) {
            // Generate 32 bytes of cryptographic randomness and convert to hex
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    // 2. Output the hidden HTML input field for Views
    public static function csrfField()
    {
        $token = self::generateToken();
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }

    // 3. Global validation interceptor for POST requests
    public static function verifyRequest()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
                die("Security Violation: CSRF Token Missing. Request aborted.");
            }

            // Use constant-time comparison to defeat timing attacks
            if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
                die("Security Violation: CSRF Token Mismatch. Request aborted.");
            }
        }
    }

    /**
     * Safely validate a CSRF token string against active session token.
     */
    public static function validateToken($token)
    {
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], (string) $token);
    }
}