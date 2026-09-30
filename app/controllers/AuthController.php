<?php

require_once BASE_PATH . 'app/core/Controller.php';
require_once BASE_PATH . 'app/helpers/AuthGuard.php';
require_once BASE_PATH . 'app/helpers/Audit.php';
require_once BASE_PATH . 'app/services/Mailer.php';

/**
 * Passwordless authentication — UC-01 Path B (email OTP).
 *
 * There is no registration. The first successful verification of an email
 * address creates the account (users + user_auth_identities); every later
 * verification authenticates it. Google OAuth (Path A) is a separate task.
 *
 * Routes:
 *   GET  /login               -> login()          form: enter email
 *   POST /auth/otp/request    -> otpRequest()     issue + mail a code
 *   GET  /auth/otp/verify     -> otpVerifyForm()  form: enter code
 *   POST /auth/otp/verify     -> otpVerify()      check code, create/auth, route
 *   GET  /logout              -> logout()
 */
class AuthController extends Controller
{
    const OTP_TTL_MIN        = 10;   // code lifetime
    const MAX_ATTEMPTS       = 5;    // wrong guesses before the code is killed
    const RATE_WINDOW_MIN    = 15;   // rate-limit sliding window
    const RATE_EMAIL_MAX     = 3;    // requests per address per window
    const RATE_IP_MAX        = 15;   // requests per IP per window

    // ---------------------------------------------------------------- screens

    public function login()
    {
        if (isset($_SESSION['user_id'])) {
            $this->routeAfterAuth();
        }
        $error = $_SESSION['auth_error'] ?? null;
        unset($_SESSION['auth_error']);
        $this->view('auth/login', ['error' => $error]);
    }

    /** GET /auth/otp/verify — renders the code form only. */
    public function otpVerifyForm()
    {
        if (empty($_SESSION['otp_email'])) {
            $this->redirect('/login');
        }
        $this->view('auth/otp_verify', ['error' => null]);
    }

    // ---------------------------------------------------------------- actions

    /** POST /auth/otp/request — CSRF already verified in the base constructor. */
    public function otpRequest()
    {
        $email = $this->normalizeEmail($_POST['email'] ?? '');
        if ($email === null) {
            $_SESSION['auth_error'] = 'Enter a valid email address.';
            $this->redirect('/login');
        }

        $ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $otp = $this->model('EmailOtp');

        $throttled =
            $otp->countRecentByEmail($email, self::RATE_WINDOW_MIN) >= self::RATE_EMAIL_MAX ||
            $otp->countRecentByIp($ip, self::RATE_WINDOW_MIN)       >= self::RATE_IP_MAX;

        $isDev = true; // Always allow OTP generation during local XAMPP / dev testing
        if (!$throttled || $isDev) {
            // Registered and unregistered addresses run the identical path here:
            // one lookup, one hash, one email_otp_codes insert, one email_log
            // row. Only stored values differ (purpose, email_log.user_id).
            $existing = $this->model('User')->findByEmail($email);
            $userId   = $existing ? (int) $existing['user_id'] : null;

            $code    = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $hash    = password_hash($code, PASSWORD_DEFAULT);
            $purpose = $existing ? 'login' : 'signup';

            $otp->create($email, $hash, $purpose, $ip, self::OTP_TTL_MIN);
            (new Mailer())->sendOtp($email, $code, $userId);

            Audit::write($userId, 'otp_requested', 'OTP requested for ' . $email);
        }

        // Response is identical whether the address is registered or not, and
        // whether or not it was throttled (BR-3 — no enumeration).
        $_SESSION['otp_email'] = $email;
        $this->redirect('/auth/otp/verify');
    }

    /** POST /admin/auth/otp/request — PESO Admin OTP request with authorization check */
    public function adminOtpRequest()
    {
        $email = $this->normalizeEmail($_POST['email'] ?? '');
        if ($email === null) {
            $_SESSION['admin_auth_error'] = 'Enter a valid email address.';
            $this->redirect('/admin/login');
        }

        $userModel = $this->model('User');
        $existing  = $userModel->findByEmail($email);

        if (!$existing || ($existing['role'] ?? null) !== 'admin') {
            $_SESSION['admin_auth_error'] = 'Access denied: That email address is not registered as a PESO Admin.';
            $this->redirect('/admin/login');
        }

        $ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $otp = $this->model('EmailOtp');

        $throttled =
            $otp->countRecentByEmail($email, self::RATE_WINDOW_MIN) >= self::RATE_EMAIL_MAX ||
            $otp->countRecentByIp($ip, self::RATE_WINDOW_MIN)       >= self::RATE_IP_MAX;

        $isDev = true;
        if (!$throttled || $isDev) {
            $userId  = (int) $existing['user_id'];
            $code    = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $hash    = password_hash($code, PASSWORD_DEFAULT);
            $purpose = 'login';

            $otp->create($email, $hash, $purpose, $ip, self::OTP_TTL_MIN);
            (new Mailer())->sendOtp($email, $code, $userId);

            Audit::write($userId, 'admin_otp_requested', 'Admin OTP requested for ' . $email);
        }

        $_SESSION['otp_email'] = $email;
        $this->redirect('/auth/otp/verify');
    }

    /** POST /auth/otp/verify */
    public function otpVerify()
    {
        $email = $_SESSION['otp_email'] ?? null;
        if ($email === null) {
            $this->redirect('/login');
        }

        $code = trim($_POST['code'] ?? '');
        $otp  = $this->model('EmailOtp');
        $row  = $otp->newestActiveForEmail($email);

        if (!$row) {
            // E4 — no live code: expired, already spent, or never issued.
            $this->view('auth/otp_verify', ['error' => 'That code has expired. Request a new one.']);
            return;
        }

        if ((int) $row['attempt_count'] >= self::MAX_ATTEMPTS) {
            $otp->consume($row['otp_id']);
            Audit::write(null, 'otp_failed', 'OTP already at attempt cap for ' . $email);
            $this->view('auth/otp_verify', ['error' => 'Too many attempts. Request a new code.']);
            return;
        }

        $ok = preg_match('/^\d{6}$/', $code) && password_verify($code, $row['code_hash']);
        if (!$ok) {
            $otp->incrementAttempts($row['otp_id']);
            if ((int) $row['attempt_count'] + 1 >= self::MAX_ATTEMPTS) {
                // E5 — invalidate the code and force a fresh request.
                $otp->consume($row['otp_id']);
                Audit::write(null, 'otp_failed',
                    'OTP invalidated after ' . self::MAX_ATTEMPTS . ' failed attempts for ' . $email);
            }
            $this->view('auth/otp_verify', ['error' => 'That code is not correct. Try again.']);
            return;
        }

        // Correct code — spend it, then create-or-authenticate atomically.
        $otp->consume($row['otp_id']);

        $userModel = $this->model('User');
        $identity  = $this->model('AuthIdentity');
        $db        = Database::getInstance()->getConnection();

        $db->beginTransaction();
        try {
            $user = $userModel->findByEmail($email);
            if (!$user) {
                $userId  = $userModel->createPendingUser($email);
                $created = true;
            } else {
                $userId  = (int) $user['user_id'];
                $userModel->markEmailVerified($userId);
                $created = false;
            }
            $identity->linkEmailIdentity($userId, $email);
            $userModel->recordLogin($userId);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('[auth] verify transaction failed: ' . $e->getMessage());
            http_response_code(500);
            $this->view('auth/otp_verify', ['error' => 'Something went wrong on our side. Please try again.']);
            return;
        }

        $fresh = $userModel->findById($userId);

        // E6 — suspended / deactivated: authentication succeeded, authorization did not.
        if (in_array($fresh['account_status'], ['Suspended', 'Deactivated'], true)) {
            Audit::write($userId, 'login_failed',
                'Authentication on ' . $fresh['account_status'] . ' account: ' . $email);
            $this->destroySession();
            $this->view('auth/suspended');
            return;
        }

        unset($_SESSION['otp_email']);
        $this->establishSession($fresh);

        if ($created) {
            Audit::write($userId, 'account_created', 'Account created via email OTP', 'user', $userId);
        }
        Audit::write($userId, 'login_success', 'Login via email OTP', 'user', $userId);

        $this->routeAfterAuth();
    }

    public function logout()
    {
        $this->destroySession();
        $this->redirect('/login?success=logged_out');
    }

    // ---------------------------------------------------------------- helpers

    private function normalizeEmail($raw)
    {
        $email = strtolower(trim((string) $raw));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
            return null;
        }
        return $email;
    }

    /**
     * Regenerate the session id and populate it. Called after OTP verification;
     * RoleController regenerates again after the role is chosen.
     */
    private function establishSession($user)
    {
        session_regenerate_id(true);
        $_SESSION['user_id']        = (int) $user['user_id'];
        $_SESSION['email']          = $user['email'];
        $_SESSION['role']           = $user['role']; // may be null until UC-01a
        $_SESSION['account_status'] = $user['account_status'];
        $_SESSION['ua_hash']        = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
        $_SESSION['last_activity']  = time();
    }

    private function routeAfterAuth()
    {
        $role   = $_SESSION['role'] ?? null;
        $status = $_SESSION['account_status'] ?? null;

        if ($role === 'admin') {
            $this->redirect('/admin/dashboard');           // admins never see the role picker
        }
        if (empty($role)) {
            $this->redirect('/select-role');               // UC-01a
        }
        if ($status === 'Pending') {
            $this->redirect('/build-profile');            // C-29 — one builder, both roles
        }
        $this->redirect($role === 'employer' ? '/employer/dashboard' : '/dashboard');
    }

    private function destroySession()
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

    private function redirect($path)
    {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePrefix = (strpos($scriptName, '/sikaphub/') === 0) ? '/sikaphub' : '';
        header('Location: ' . $basePrefix . $path);
        exit();
    }
}
