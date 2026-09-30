<?php

require_once BASE_PATH . 'app/core/Controller.php';
require_once BASE_PATH . 'app/helpers/AuthGuard.php';
require_once BASE_PATH . 'app/helpers/Audit.php';

class AdminController extends Controller
{
    public function loginForm()
    {
        if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? null) === 'admin') {
            return $this->dashboard();
        }
        $error = $_SESSION['admin_auth_error'] ?? null;
        unset($_SESSION['admin_auth_error']);
        $this->view('admin/login', ['error' => $error]);
    }

    public function login()
    {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $_SESSION['admin_auth_error'] = 'Enter both username and password.';
            $this->redirect('/admin/login');
        }

        try {
            $db = Database::getInstance()->getConnection();

            // Auto-heal missing peso_admins table on production if migration was not executed
            $db->exec("CREATE TABLE IF NOT EXISTS peso_admins (
                admin_id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                username VARCHAR(50) NULL UNIQUE,
                password_hash VARCHAR(255) NULL,
                admin_name VARCHAR(100) DEFAULT 'PESO Admin',
                access_level VARCHAR(50) DEFAULT 'SuperAdmin',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            // Auto-add missing columns if peso_admins table existed prior to migration
            try {
                $db->exec("ALTER TABLE peso_admins ADD COLUMN username VARCHAR(50) NULL UNIQUE AFTER user_id");
            } catch (\Throwable $t) {}
            try {
                $db->exec("ALTER TABLE peso_admins ADD COLUMN password_hash VARCHAR(255) NULL AFTER username");
            } catch (\Throwable $t) {}

            $stmt = $db->prepare(
                "SELECT pa.admin_id, pa.user_id, pa.username, pa.password_hash, u.email, u.role, u.account_status
                 FROM users u
                 LEFT JOIN peso_admins pa ON pa.user_id = u.user_id
                 WHERE (pa.username = :username OR u.email = :username2) AND u.role = 'admin'
                 LIMIT 1"
            );
            $stmt->execute([':username' => $username, ':username2' => $username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            // Auto-provision initial admin account on first login if no admin account matches
            if (!$admin || empty($admin['password_hash'])) {
                // Check if an admin record exists in users table
                $stmtCheck = $db->prepare("SELECT user_id, email, account_status FROM users WHERE role = 'admin' LIMIT 1");
                $stmtCheck->execute();
                $existingUserAdmin = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                $hash = password_hash($password, PASSWORD_BCRYPT);

                if (!$existingUserAdmin) {
                    $adminEmail = (strpos($username, '@') !== false) ? $username : 'admin@guimba.gov.ph';
                    $stmtInsUser = $db->prepare("INSERT INTO users (email, email_verified_at, role, account_status) VALUES (:email, NOW(), 'admin', 'Active')");
                    $stmtInsUser->execute([':email' => $adminEmail]);
                    $userId = (int) $db->lastInsertId();

                    $stmtInsAdmin = $db->prepare("INSERT INTO peso_admins (user_id, username, password_hash, admin_name) VALUES (:user_id, :username, :hash, 'PESO Admin')");
                    $stmtInsAdmin->execute([
                        ':user_id' => $userId,
                        ':username' => $username,
                        ':hash' => $hash
                    ]);
                } else {
                    $userId = (int) $existingUserAdmin['user_id'];
                    $stmtPaCheck = $db->prepare("SELECT admin_id FROM peso_admins WHERE user_id = :user_id LIMIT 1");
                    $stmtPaCheck->execute([':user_id' => $userId]);
                    $paRow = $stmtPaCheck->fetch(PDO::FETCH_ASSOC);

                    if ($paRow) {
                        $stmtUpdPa = $db->prepare("UPDATE peso_admins SET username = :username, password_hash = :hash WHERE user_id = :user_id");
                        $stmtUpdPa->execute([':username' => $username, ':hash' => $hash, ':user_id' => $userId]);
                    } else {
                        $stmtInsPa = $db->prepare("INSERT INTO peso_admins (user_id, username, password_hash, admin_name) VALUES (:user_id, :username, :hash, 'PESO Admin')");
                        $stmtInsPa->execute([':user_id' => $userId, ':username' => $username, ':hash' => $hash]);
                    }
                }

                // Re-fetch created/updated admin
                $stmt->execute([':username' => $username, ':username2' => $username]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if (!$admin || empty($admin['password_hash']) || !password_verify($password, $admin['password_hash'])) {
                Audit::write(null, 'admin_login_failed', 'Failed admin login attempt for username: ' . $username);
                $_SESSION['admin_auth_error'] = 'Invalid username or password.';
                $this->redirect('/admin/login');
            }

            if (in_array($admin['account_status'], ['Suspended', 'Deactivated'], true)) {
                $_SESSION['admin_auth_error'] = 'This account has been ' . strtolower($admin['account_status']) . '.';
                $this->redirect('/admin/login');
            }

            session_regenerate_id(true);
            $_SESSION['user_id']        = (int) $admin['user_id'];
            $_SESSION['email']          = $admin['email'];
            $_SESSION['role']           = 'admin';
            $_SESSION['account_status'] = $admin['account_status'];
            $_SESSION['ua_hash']        = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
            $_SESSION['last_activity']  = time();

            Audit::write((int) $admin['user_id'], 'admin_login_success', 'Successful username/password login for ' . $username);

            // Directly render dashboard upon successful login without external redirect loop
            return $this->dashboard();

        } catch (\Throwable $e) {
            error_log('[AdminController::login] Exception: ' . $e->getMessage());
            $_SESSION['admin_auth_error'] = 'Authentication Database Notice: ' . $e->getMessage();
            $this->redirect('/admin/login');
        }
    }


    public function dashboard()
    {
        AuthGuard::requireLogin();
        if (($_SESSION['role'] ?? null) !== 'admin') {
            return $this->denyAccess();
        }

        try {
            $adminModel = $this->model('Admin');
            $data = [
                'kpis' => $adminModel->getKPIs(),
                'overview' => $adminModel->getSystemOverview(),
                'geography' => $adminModel->getSeekersByMunicipality(),
                'top_skills' => $adminModel->getTopDemandSkills(),
                'pending_employers' => $adminModel->getPendingEmployers(),
                'pending_skills' => $adminModel->getPendingSkills(),
                'admin_name' => $adminModel->getAdminName((int) ($_SESSION['user_id'] ?? 0)),
                'success' => isset($_GET['success']) ? true : false
            ];
            $this->view('admin/dashboard', $data);
        } catch (\Throwable $e) {
            error_log('[AdminController::dashboard] Exception: ' . $e->getMessage());
            $data = [
                'kpis' => ['total_seekers' => 0, 'total_employers' => 0, 'verified_employers' => 0, 'pending_employers' => 0, 'active_jobs' => 0, 'pending_skills' => 0],
                'overview' => [],
                'geography' => [],
                'top_skills' => [],
                'pending_employers' => [],
                'pending_skills' => [],
                'admin_name' => 'PESO Admin',
                'error_msg' => $e->getMessage()
            ];
            $this->view('admin/dashboard', $data);
        }
    }

    public function verifications()
    {
        AuthGuard::requireLogin();
        if (($_SESSION['role'] ?? null) !== 'admin') {
            return $this->denyAccess();
        }

        try {
            $adminModel = $this->model('Admin');
            $this->autoVerifyPendingPermits($adminModel);
            $verifications = $adminModel->getPendingEmployersWithPermits();

            $selectedEmployer = null;
            if (isset($_GET['employer_id'])) {
                $empId = (int) $_GET['employer_id'];
                $selectedEmployer = $adminModel->getEmployerVerificationDetail($empId);
            }

            $this->view('admin/verifications', [
                'verifications' => $verifications,
                'selectedEmployer' => $selectedEmployer,
                'success' => $_GET['success'] ?? null,
                'error' => $_GET['error'] ?? null
            ]);
        } catch (\Throwable $e) {
            error_log('[AdminController::verifications] Exception: ' . $e->getMessage());
            $this->view('admin/verifications', [
                'verifications' => [],
                'selectedEmployer' => null,
                'error' => 'Database Notice: ' . $e->getMessage()
            ]);
        }
    }

    public function employers()
    {
        AuthGuard::requireLogin();
        if (($_SESSION['role'] ?? null) !== 'admin') {
            return $this->denyAccess();
        }

        try {
            $search = trim($_GET['search'] ?? '');
            $status = trim($_GET['status'] ?? '');
            $adminModel = $this->model('Admin');
            $employers = $adminModel->getAllEmployers($search, $status);

            $this->view('admin/employers', [
                'employers' => $employers,
                'search' => $search,
                'status' => $status
            ]);
        } catch (\Throwable $e) {
            error_log('[AdminController::employers] Exception: ' . $e->getMessage());
            $this->view('admin/employers', [
                'employers' => [],
                'search' => '',
                'status' => '',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function seekers()
    {
        AuthGuard::requireLogin();
        if (($_SESSION['role'] ?? null) !== 'admin') {
            return $this->denyAccess();
        }

        try {
            $search = trim($_GET['search'] ?? '');
            $municipalityId = (int) ($_GET['municipality_id'] ?? 0);

            $adminModel = $this->model('Admin');
            $seekers = $adminModel->getAllSeekers($search, $municipalityId);
            $municipalities = $this->dbQuery("SELECT municipality_id, municipality_name FROM lib_municipalities ORDER BY municipality_name ASC");

            $this->view('admin/seekers', [
                'seekers' => $seekers,
                'municipalities' => $municipalities,
                'search' => $search,
                'municipality_id' => $municipalityId
            ]);
        } catch (\Throwable $e) {
            error_log('[AdminController::seekers] Exception: ' . $e->getMessage());
            $this->view('admin/seekers', [
                'seekers' => [],
                'municipalities' => [],
                'search' => '',
                'municipality_id' => 0
            ]);
        }
    }

    public function jobs()
    {
        AuthGuard::requireLogin();
        if (($_SESSION['role'] ?? null) !== 'admin') {
            return $this->denyAccess();
        }

        try {
            $search = trim($_GET['search'] ?? '');
            $status = trim($_GET['status'] ?? '');

            $adminModel = $this->model('Admin');
            $jobs = $adminModel->getAllJobPostings($search, $status);

            $this->view('admin/jobs', [
                'jobs' => $jobs,
                'search' => $search,
                'status' => $status
            ]);
        } catch (\Throwable $e) {
            error_log('[AdminController::jobs] Exception: ' . $e->getMessage());
            $this->view('admin/jobs', [
                'jobs' => [],
                'search' => '',
                'status' => ''
            ]);
        }
    }

    public function skills()
    {
        AuthGuard::requireLogin();
        if (($_SESSION['role'] ?? null) !== 'admin') {
            return $this->denyAccess();
        }

        try {
            $search = trim($_GET['search'] ?? '');
            $categoryId = (int) ($_GET['category_id'] ?? 0);

            $adminModel = $this->model('Admin');
            $skills = $adminModel->getAllMasterSkills($search, $categoryId);
            $categories = $adminModel->getSkillCategories();

            $this->view('admin/skills', [
                'skills' => $skills,
                'categories' => $categories,
                'search' => $search,
                'category_id' => $categoryId
            ]);
        } catch (\Throwable $e) {
            error_log('[AdminController::skills] Exception: ' . $e->getMessage());
            $this->view('admin/skills', [
                'skills' => [],
                'categories' => [],
                'search' => '',
                'category_id' => 0
            ]);
        }
    }

    public function auditLogs()
    {
        AuthGuard::requireLogin();
        if (($_SESSION['role'] ?? null) !== 'admin') {
            return $this->denyAccess();
        }

        try {
            $adminModel = $this->model('Admin');
            $logs = $adminModel->getAuditLogs(100);
            $this->view('admin/audit_logs', ['logs' => $logs]);
        } catch (\Throwable $e) {
            error_log('[AdminController::auditLogs] Exception: ' . $e->getMessage());
            $this->view('admin/audit_logs', ['logs' => []]);
        }
    }

    public function logout()
    {
        Audit::write($_SESSION['user_id'] ?? null, 'admin_logout', 'Admin logged out.');
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
        $this->redirect('/admin/login?success=logged_out');
    }

    private function dbQuery($sql, $params = [])
    {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function denyAccess()
    {
        http_response_code(403);
        $this->view('errors/500', [
            'code'    => 403,
            'message' => 'You do not have access to the PESO admin area.',
        ]);
        exit();
    }

    private function redirect($path)
    {
        $httpHost = strtolower($_SERVER['HTTP_HOST'] ?? '');
        $isAdminSubdomain = (strpos($httpHost, 'admin.') === 0);

        if ($isAdminSubdomain && strpos($path, '/admin/') === 0) {
            $path = substr($path, 6);
        }

        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePrefix = (strpos($scriptName, '/sikaphub/') === 0) ? '/sikaphub' : '';
        header('Location: ' . $basePrefix . $path);
        exit();
    }

    private function autoVerifyPendingPermits($adminModel)
    {
        try {
            $pending = $adminModel->getPendingEmployersWithPermits();
            if (empty($pending)) return;

            $aiEngine = new AIEngineService();
            foreach ($pending as $emp) {
                if (empty($emp['business_permit_file'])) continue;
                if ($emp['verified_status'] !== 'Pending') continue;
                if (!empty($emp['verification_status']) && $emp['verification_status'] !== 'pending_ai') continue;

                $fullPath = BASE_PATH . 'storage/uploads/permits/' . $emp['business_permit_file'];
                if (!file_exists($fullPath)) continue;

                $analysis = $aiEngine->verifyPermitFile($fullPath, $emp['company_name']);
                if (($analysis['status'] ?? '') === 'success') {
                    $verifStatus = ($analysis['permit_status'] ?? '') === 'VALID' ? 'green_flag' : 'red_flag';
                    $adminModel->updateEmployerPermitAnalysis(
                        (int) $emp['employer_id'],
                        $verifStatus,
                        $analysis['extracted_text'] ?? '',
                        (float) ($analysis['confidence_score'] ?? 0.0),
                        $analysis['rejection_reasons'] ?? []
                    );
                }
            }
        } catch (\Throwable $e) {
            error_log('[autoVerifyPendingPermits] error: ' . $e->getMessage());
        }
    }
}
