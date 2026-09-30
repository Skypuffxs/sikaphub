<?php
/**
 * Dedicated Entry Point for Admin Subdomain (admin.sikaphub.com)
 */

// Security Headers for A+ Grade (SecurityHeaders.com)
header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(self), camera=(), microphone=(), payment=(), usb=(), display-capture=()');
header("Content-Security-Policy: default-src 'self' https: data: blob:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https:; style-src 'self' 'unsafe-inline' https:; font-src 'self' https: data:; img-src 'self' data: blob: https:; connect-src 'self' https: wss:; frame-ancestors 'self';");
header('X-XSS-Protection: 1; mode=block');
header_remove('X-Powered-By');

define('IS_ADMIN_ENTRY', true);

// Enforce subdomain execution: return 404 if accessed via main domain (sikaphub.com/admin/login)
$httpHost = strtolower($_SERVER['HTTP_HOST'] ?? '');
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$isViewDocument = (strpos($requestUri, 'view-document') !== false);

if (strpos($httpHost, 'admin.') !== 0 && !$isViewDocument) {
    http_response_code(404);
    echo "404 - Page Not Found";
    exit();
}

// 1. Initialize Security & Session
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

// Error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// 2. Define Base Path (1 directory up to project root)
define('BASE_PATH', dirname(__DIR__) . '/');

// Load Composer Dependencies
require_once BASE_PATH . 'vendor/autoload.php';

// Load Core Helpers
require_once BASE_PATH . 'app/helpers/CSRF.php';
require_once BASE_PATH . 'app/helpers/AuthGuard.php';
require_once BASE_PATH . 'app/helpers/ErrorHelper.php';

CSRF::generateToken();

// Load Environment Variables
function loadEnvAdmin($path)
{
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}
loadEnvAdmin(BASE_PATH . '.env');

// Autoload Core Classes & Controllers
require_once BASE_PATH . 'config/Database.php';
require_once BASE_PATH . 'app/core/Router.php';
require_once BASE_PATH . 'app/controllers/AdminController.php';

// Initialize Router
$router = new Router();

// Calculate Request URI
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestUri = strtok($requestUri, '?');
$requestUri = str_replace(['/admin/index.php', '/index.php'], '', $requestUri);

// Strip /admin prefix if present for clean subdomain paths (admin.sikaphub.com/dashboard)
if (strpos($requestUri, '/admin/') === 0) {
    $requestUri = substr($requestUri, 6);
} elseif ($requestUri === '/admin') {
    $requestUri = '/';
}

if (empty($requestUri) || $requestUri[0] !== '/') {
    $requestUri = '/' . $requestUri;
}


// Subdomain Clean Routes (admin.sikaphub.com/*)
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

// Legacy /admin/* prefixed routes for compatibility
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

// Dispatch Request
$method = $_SERVER['REQUEST_METHOD'];
$router->dispatch($requestUri, $method);

