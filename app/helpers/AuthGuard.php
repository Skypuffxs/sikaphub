<?php

class AuthGuard
{
    // Idle timeout: session dies after this many seconds without a guarded
    // request. The timer starts at OTP verification — it cannot run during OTP
    // entry, because /login and /auth/otp/* are public and never call a guard.
    const IDLE_TIMEOUT = 1800; // 30 minutes (UC-01 non-functional)

    // 1. Forces the user to be logged in, with a valid fingerprint and a
    //    non-idle session.
    public static function requireLogin()
    {
        if (!isset($_SESSION['user_id'])) {
            self::bounce('/login');
        }

        // Session fingerprint — User-Agent hash only. IP is deliberately not
        // matched: Philippine mobile carriers rotate client addresses.
        $ua = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
        if (!isset($_SESSION['ua_hash']) || !hash_equals($_SESSION['ua_hash'], $ua)) {
            self::destroy();
            self::bounce('/login');
        }

        // Idle timeout, refreshed on every guarded request.
        if (isset($_SESSION['last_activity'])
            && (time() - $_SESSION['last_activity']) > self::IDLE_TIMEOUT) {
            self::destroy();
            self::bounce('/login?timeout=1');
        }
        $_SESSION['last_activity'] = time();
    }

    // 2. Requires that the account role has been chosen (UC-01a). A freshly
    //    verified user has role = NULL and is trapped at /select-role.
    public static function requireRoleSelected()
    {
        self::requireLogin();

        if (empty($_SESSION['role'])) {
            self::bounce('/select-role');
        }
    }

    // 3. Traps 'Pending' users in the profile builder.
    public static function requireActiveProfile()
    {
        self::requireRoleSelected();

        if ($_SESSION['account_status'] === 'Pending') {
            self::bounce('/build-profile');   // C-29 — one builder, both roles
        }
    }

    // 4. Verifies that a concrete employer/jobseeker entity row exists for the
    //    active session user.
    public static function requireCompleteEntity()
    {
        self::requireRoleSelected();

        $db = Database::getInstance()->getConnection();
        $userId = $_SESSION['user_id'];
        $role = $_SESSION['role'];

        if ($role === 'employer') {
            $stmt = $db->prepare("SELECT employer_id FROM employers WHERE user_id = :user_id LIMIT 1");
        } elseif ($role === 'jobseeker') {
            $stmt = $db->prepare("SELECT jobseeker_id FROM job_seekers WHERE user_id = :user_id LIMIT 1");
        } else {
            http_response_code(403);
            exit('Forbidden: invalid role.');
        }

        $stmt->execute([':user_id' => $userId]);

        if (!$stmt->fetch()) {
            self::bounce('/build-profile');
        }
    }

    private static function bounce($path)
    {
        header('Location: /sikaphub' . $path);
        exit();
    }

    private static function destroy()
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'] ?? '/',
                'domain'   => $p['domain'] ?? '',
                'secure'   => $p['secure'] ?? false,
                'httponly' => $p['httponly'] ?? true,
                'samesite' => $p['samesite'] ?? 'Lax',
            ]);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
