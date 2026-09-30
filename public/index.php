<?php
// Security Headers for A+ Grade (SecurityHeaders.com)
header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(self), camera=(), microphone=(), payment=(), usb=(), display-capture=()');
header("Content-Security-Policy: default-src 'self' https: data: blob:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https:; style-src 'self' 'unsafe-inline' https:; font-src 'self' https: data:; img-src 'self' data: blob: https:; connect-src 'self' https: wss:; frame-ancestors 'self';");
header('X-XSS-Protection: 1; mode=block');
header_remove('X-Powered-By');

// 1. Initialize Security & Session
//    Cookie: HttpOnly, SameSite=Lax (never Strict — it withholds the cookie on
//    the OAuth callback navigation), Secure only when the request is HTTPS so
//    sign-in works on http://localhost.
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
]);
session_start();

// Error display stays OFF: auth failure paths must never leak a stack trace.
// Errors are logged, not rendered.
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// 2. Define Absolute Paths
define('BASE_PATH', dirname(__DIR__) . '/');

// Load Composer Dependencies
require_once BASE_PATH . 'vendor/autoload.php';

// Load Core Helpers (CSRF MUST load early)
require_once BASE_PATH . 'app/helpers/CSRF.php';
require_once BASE_PATH . 'app/helpers/AuthGuard.php';
require_once BASE_PATH . 'app/helpers/ErrorHelper.php';

// Initialize Global CSRF Token
CSRF::generateToken();

// 3. Simple .env Loader
function loadEnv($path)
{
    if (!file_exists($path))
        return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0)
            continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}
loadEnv(BASE_PATH . '.env');

// 4. Autoload Core Classes
require_once BASE_PATH . 'config/Database.php';
require_once BASE_PATH . 'app/core/Router.php';
require_once BASE_PATH . 'app/controllers/AuthController.php';
require_once BASE_PATH . 'app/controllers/RoleController.php';
require_once BASE_PATH . 'app/controllers/ProfileController.php';
require_once BASE_PATH . 'app/controllers/AdminController.php';
require_once BASE_PATH . 'app/controllers/JobController.php';
require_once BASE_PATH . 'app/controllers/JobSeekerController.php';
require_once BASE_PATH . 'app/controllers/EmployerController.php';
require_once BASE_PATH . 'app/controllers/HomeController.php';
require_once BASE_PATH . 'app/services/AIEngineService.php';

// 5. Initialize Router
$router = new Router();

// 6. Define Base URI Path dynamically to handle both local subdirectory and root deployment (Hostinger)
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$baseUri = str_replace('/public/index.php', '', $scriptName);
$baseUri = str_replace('/index.php', '', $baseUri);

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';

if (!empty($baseUri) && strpos($requestUri, $baseUri) === 0) {
    $requestUri = substr($requestUri, strlen($baseUri));
}

// Strip legacy /sikaphub prefix if present (ensures seamless deployment on root domains like sikaphub.com)
if (strpos($requestUri, '/sikaphub/') === 0) {
    $requestUri = substr($requestUri, 9);
} elseif ($requestUri === '/sikaphub') {
    $requestUri = '/';
}

if (empty($requestUri) || $requestUri[0] !== '/') {
    $requestUri = '/' . $requestUri;
}

// 7. Define Application Routes
if ($isAdminSubdomain) {
    // Admin Subdomain Clean & Legacy Routes (admin.sikaphub.com/*)
    $router->get('/',                  ['AdminController', 'loginForm']);
    $router->get('/login',             ['AdminController', 'loginForm']);
    $router->post('/login',            ['AdminController', 'login']);
    $router->get('/dashboard',         ['AdminController', 'dashboard']);
    $router->get('/verifications',     ['AdminController', 'verifications']);
    $router->get('/export',            ['AdminController', 'exportPdf']);
    $router->get('/employers',         ['AdminController', 'employers']);
    $router->get('/seekers',           ['AdminController', 'seekers']);
    $router->get('/jobs',              ['AdminController', 'jobs']);
    $router->get('/skills',            ['AdminController', 'skills']);
    $router->get('/audit-logs',        ['AdminController', 'auditLogs']);
    $router->get('/logout',            ['AdminController', 'logout']);
    $router->post('/verify-employer',  ['AdminController', 'verifyEmployer']);
    $router->post('/approve-skill',    ['AdminController', 'approveSkill']);
    $router->get('/view-document',     ['AdminController', 'viewDocument']);
    $router->post('/toggle-job-status',['AdminController', 'toggleJobStatus']);
    $router->post('/add-skill',        ['AdminController', 'addSkill']);
    $router->post('/reanalyze-permit', ['AdminController', 'reanalyzePermit']);

    $router->get('/admin/login',             ['AdminController', 'loginForm']);
    $router->post('/admin/login',            ['AdminController', 'login']);
    $router->get('/admin/dashboard',         ['AdminController', 'dashboard']);
    $router->get('/admin/verifications',     ['AdminController', 'verifications']);
    $router->get('/admin/export',            ['AdminController', 'exportPdf']);
    $router->get('/admin/employers',         ['AdminController', 'employers']);
    $router->get('/admin/seekers',           ['AdminController', 'seekers']);
    $router->get('/admin/jobs',              ['AdminController', 'jobs']);
    $router->get('/admin/skills',            ['AdminController', 'skills']);
    $router->get('/admin/audit-logs',        ['AdminController', 'auditLogs']);
    $router->get('/admin/logout',            ['AdminController', 'logout']);
    $router->post('/admin/verify-employer',  ['AdminController', 'verifyEmployer']);
    $router->post('/admin/approve-skill',    ['AdminController', 'approveSkill']);
    $router->get('/admin/view-document',     ['AdminController', 'viewDocument']);
    $router->post('/admin/toggle-job-status',['AdminController', 'toggleJobStatus']);
    $router->post('/admin/add-skill',        ['AdminController', 'addSkill']);
    $router->post('/admin/reanalyze-permit', ['AdminController', 'reanalyzePermit']);
} else {
    // Landing Page Route & Legal Pages
    $router->get('/', ['HomeController', 'index']);
    $router->get('/terms', ['HomeController', 'terms']);
    $router->get('/privacy', ['HomeController', 'privacy']);
    $router->get('/offline', ['HomeController', 'offline']);

    // Authentication
    $router->get('/login', ['AuthController', 'login']);
    $router->get('/register', ['AuthController', 'login']);
    $router->post('/auth/otp/request', ['AuthController', 'otpRequest']);
    $router->get('/auth/otp/verify', ['AuthController', 'otpVerifyForm']);
    $router->post('/auth/otp/verify', ['AuthController', 'otpVerify']);
    $router->get('/logout', ['AuthController', 'logout']);

    // Role picker
    $router->get('/select-role', ['RoleController', 'select']);
    $router->post('/select-role', ['RoleController', 'select']);

    // Profile Routes
    $router->get('/build-profile', ['ProfileController', 'buildProfile']);
    $router->post('/build-profile', ['ProfileController', 'buildProfile']);
    $router->get('/profile/barangays', ['ProfileController', 'barangays']);
    $router->post('/profile/parse-resume', ['ProfileController', 'parseResume']);

    // Job Seeker Dashboard & Application Routes
    $router->get('/dashboard', ['JobSeekerController', 'dashboard']);
    $router->get('/my-applications', ['JobSeekerController', 'tracker']);
    $router->get('/saved-jobs', ['JobSeekerController', 'savedJobs']);
    $router->post('/apply', ['JobSeekerController', 'apply']);
    $router->post('/jobseeker/toggle-save-job', ['JobSeekerController', 'toggleSaveJob']);

    // Job Routes
    $router->get('/job/view', ['JobController', 'show']);
    $router->get('/post-job', ['JobController', 'create']);
    $router->post('/post-job', ['JobController', 'create']);

    // Employer ATS Dashboard & Review Routes
    $router->get('/company/view', ['EmployerController', 'showPublicProfile']);
    $router->get('/employer/dashboard', ['EmployerController', 'dashboard']);
    $router->get('/employer/review-candidate', ['EmployerController', 'reviewCandidate']);
    $router->post('/employer/review-candidate', ['EmployerController', 'reviewCandidate']);
    $router->get('/employer/upload-permit',  ['EmployerController', 'uploadPermit']);
    $router->post('/employer/upload-permit', ['EmployerController', 'uploadPermit']);
    $router->post('/employer/compare-candidates', ['EmployerController', 'compareCandidates']);

    // Admin Routes on Main Domain
    $router->get('/admin',                   ['AdminController', 'loginForm']);
    $router->get('/admin/',                  ['AdminController', 'loginForm']);
    $router->get('/admin/login',             ['AdminController', 'loginForm']);
    $router->post('/admin/login',            ['AdminController', 'login']);
    $router->get('/admin/dashboard',         ['AdminController', 'dashboard']);
    $router->get('/admin/verifications',     ['AdminController', 'verifications']);
    $router->get('/admin/export',            ['AdminController', 'exportPdf']);
    $router->get('/admin/employers',         ['AdminController', 'employers']);
    $router->get('/admin/seekers',           ['AdminController', 'seekers']);
    $router->get('/admin/jobs',              ['AdminController', 'jobs']);
    $router->get('/admin/skills',            ['AdminController', 'skills']);
    $router->get('/admin/audit-logs',        ['AdminController', 'auditLogs']);
    $router->get('/admin/logout',            ['AdminController', 'logout']);
    $router->post('/admin/verify-employer',  ['AdminController', 'verifyEmployer']);
    $router->post('/admin/approve-skill',    ['AdminController', 'approveSkill']);
    $router->get('/admin/view-document',     ['AdminController', 'viewDocument']);
    $router->post('/admin/toggle-job-status',['AdminController', 'toggleJobStatus']);
    $router->post('/admin/add-skill',        ['AdminController', 'addSkill']);
    $router->post('/admin/reanalyze-permit', ['AdminController', 'reanalyzePermit']);
}

// 8. Dispatch the Request
$method = $_SERVER['REQUEST_METHOD'];
$router->dispatch($requestUri, $method);