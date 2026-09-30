<?php

require_once BASE_PATH . 'app/core/Controller.php';
require_once BASE_PATH . 'app/helpers/AuthGuard.php';
require_once BASE_PATH . 'app/helpers/Audit.php';

class AdminController extends Controller
{
    public function loginForm()
    {
        if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? null) === 'admin') {
            $this->redirect('/admin/dashboard');
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

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            "SELECT pa.admin_id, pa.user_id, pa.username, pa.password_hash, u.email, u.role, u.account_status
             FROM peso_admins pa
             JOIN users u ON pa.user_id = u.user_id
             WHERE (pa.username = :username OR u.email = :email) AND u.role = 'admin'
             LIMIT 1"
        );
        $stmt->execute([':username' => $username, ':email' => $username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin || !password_verify($password, $admin['password_hash'])) {
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

        $this->redirect('/admin/dashboard');
    }

    public function dashboard()
    {
        AuthGuard::requireLogin();
        if (($_SESSION['role'] ?? null) !== 'admin') {
            return $this->denyAccess();
        }

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
    }

    public function verifications()
    {
        AuthGuard::requireLogin();
        if (($_SESSION['role'] ?? null) !== 'admin') {
            return $this->denyAccess();
        }

        $adminModel = $this->model('Admin');
        $this->autoVerifyPendingPermits($adminModel);
        $verifications = $adminModel->getPendingEmployersWithPermits();

        $selectedEmployer = null;
        if (isset($_GET['employer_id'])) {
            $empId = (int) $_GET['employer_id'];
            $selectedEmployer = $adminModel->getEmployerVerificationDetail($empId);
        }

        $this->view('admin/verifications', [
            'verifications'    => $verifications,
            'selectedEmployer' => $selectedEmployer,
            'admin_name'       => $adminModel->getAdminName((int) ($_SESSION['user_id'] ?? 0)),
            'success'          => $_GET['success'] ?? null,
            'error'            => $_GET['error'] ?? null
        ]);
    }

    public function reanalyzePermit()
    {
        AuthGuard::requireLogin();
        if (($_SESSION['role'] ?? null) !== 'admin') {
            return $this->denyAccess();
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->redirect('/admin/employers');
        }

        $employerId = (int) ($_POST['employer_id'] ?? 0);
        if ($employerId <= 0) {
            return $this->redirect('/admin/employers?error=invalid_id');
        }

        $adminModel = $this->model('Admin');
        $emp = $adminModel->getEmployerVerificationDetail($employerId);
        if (!$emp) {
            return $this->redirect('/admin/employers?error=not_found');
        }

        $filePath = $this->resolvePermitPathOnDisk($emp);
        if (!$filePath || !is_file($filePath)) {
            return $this->redirect('/admin/employers?error=no_permit_file');
        }

        try {
            $aiService = new AIEngineService();
            $res = $aiService->verifyBusinessPermit($filePath);

            $verStatus = $res['verification_status'] ?? 'green_flag';
            $feedback = $res['ai_feedback'] ?? 'AI Audit completed.';
            $extracted = isset($res['extracted_permit_data']) ? json_encode($res['extracted_permit_data'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("UPDATE employers SET verification_status = :st, ai_feedback = :fb, extracted_permit_data = :ex WHERE employer_id = :id");
            $stmt->execute([
                ':st' => $verStatus,
                ':fb' => $feedback,
                ':ex' => $extracted,
                ':id' => $employerId
            ]);

            return $this->redirect('/admin/employers?success=ai_reanalyzed');
        } catch (Throwable $e) {
            error_log('[reanalyzePermit] error for employer #' . $employerId . ': ' . $e->getMessage());
            return $this->redirect('/admin/employers?error=ai_failed');
        }
    }

    private function autoVerifyPendingPermits($adminModel)
    {
        try {
            $employers = $adminModel->getAllEmployers('', '');
            $aiService = new AIEngineService();
            $db = Database::getInstance()->getConnection();

            foreach ($employers as $emp) {
                $status = $emp['verification_status'] ?? '';
                if (empty($status) || $status === 'pending') {
                    $filePath = $this->resolvePermitPathOnDisk($emp);
                    if ($filePath && is_file($filePath)) {
                        try {
                            $res = $aiService->verifyBusinessPermit($filePath);
                            $verStatus = $res['verification_status'] ?? 'green_flag';
                            $feedback = $res['ai_feedback'] ?? 'AI Audit completed.';
                            $extracted = isset($res['extracted_permit_data']) ? json_encode($res['extracted_permit_data'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

                            $stmt = $db->prepare("UPDATE employers SET verification_status = :st, ai_feedback = :fb, extracted_permit_data = :ex WHERE employer_id = :id");
                            $stmt->execute([
                                ':st' => $verStatus,
                                ':fb' => $feedback,
                                ':ex' => $extracted,
                                ':id' => (int) $emp['employer_id']
                            ]);
                        } catch (Throwable $e) {
                            error_log('[autoVerifyPendingPermits] AI Engine error for employer #' . $emp['employer_id'] . ': ' . $e->getMessage());
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('[autoVerifyPendingPermits] error: ' . $e->getMessage());
        }
    }

    private function resolvePermitPathOnDisk($emp): ?string
    {
        $candidates = [];
        if (!empty($emp['permit_file_path'])) {
            $clean = ltrim(str_replace('/sikaphub/', '', $emp['permit_file_path']), '/\\');
            $candidates[] = BASE_PATH . $clean;
            $candidates[] = $emp['permit_file_path'];
        }
        if (!empty($emp['business_permit_file'])) {
            $fn = basename($emp['business_permit_file']);
            $candidates[] = BASE_PATH . 'storage/documents/' . $fn;
            $candidates[] = BASE_PATH . 'public/assets/uploads/permits/' . $fn;
        }
        foreach ($candidates as $cand) {
            if (is_file($cand)) {
                return $cand;
            }
        }
        return null;
    }

    public function employers()
    {
        AuthGuard::requireLogin();
        if (($_SESSION['role'] ?? null) !== 'admin') {
            return $this->denyAccess();
        }

        $adminModel = $this->model('Admin');
        $this->autoVerifyPendingPermits($adminModel);

        $search = trim($_GET['q'] ?? '');
        $status = trim($_GET['status'] ?? '');

        $data = [
            'admin_name' => $adminModel->getAdminName((int) ($_SESSION['user_id'] ?? 0)),
            'employers' => $adminModel->getAllEmployers($search, $status),
            'search' => $search,
            'status' => $status,
        ];
        $this->view('admin/employers', $data);
    }

    public function seekers()
    {
        AuthGuard::requireLogin();
        if (($_SESSION['role'] ?? null) !== 'admin') {
            return $this->denyAccess();
        }

        $adminModel = $this->model('Admin');
        $profileModel = $this->model('Profile');

        $search = trim($_GET['q'] ?? '');
        $municipalityId = (int) ($_GET['municipality_id'] ?? 0);

        $data = [
            'admin_name' => $adminModel->getAdminName((int) ($_SESSION['user_id'] ?? 0)),
            'seekers' => $adminModel->getAllSeekers($search, $municipalityId),
            'municipalities' => $profileModel->getMunicipalities(),
            'search' => $search,
            'municipality_id' => $municipalityId,
        ];
        $this->view('admin/seekers', $data);
    }

    public function jobs()
    {
        AuthGuard::requireLogin();
        if (($_SESSION['role'] ?? null) !== 'admin') {
            return $this->denyAccess();
        }

        $adminModel = $this->model('Admin');
        $search = trim($_GET['q'] ?? '');
        $status = trim($_GET['status'] ?? '');

        $data = [
            'admin_name' => $adminModel->getAdminName((int) ($_SESSION['user_id'] ?? 0)),
            'jobs' => $adminModel->getAllJobPostings($search, $status),
            'search' => $search,
            'status' => $status,
        ];
        $this->view('admin/jobs', $data);
    }

    public function toggleJobStatus()
    {
        AuthGuard::requireLogin();
        if (($_SESSION['role'] ?? null) !== 'admin') {
            return $this->denyAccess();
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->redirect('/admin/jobs');
        }

        $jobId  = (int) ($_POST['job_id'] ?? 0);
        $status = $_POST['status'] ?? '';

        if ($jobId > 0 && in_array($status, ['Open', 'Closed', 'Suspended'], true)) {
            $this->model('Admin')->toggleJobStatus($jobId, $status);
            Audit::write((int)$_SESSION['user_id'], 'job_status_toggled', "Admin toggled job #{$jobId} status to {$status}", 'job_posting', $jobId);
            return $this->redirect('/admin/jobs?success=status_updated');
        }
        return $this->redirect('/admin/jobs?error=invalid_request');
    }

    public function skills()
    {
        AuthGuard::requireLogin();
        if (($_SESSION['role'] ?? null) !== 'admin') {
            return $this->denyAccess();
        }

        $adminModel = $this->model('Admin');
        $search = trim($_GET['q'] ?? '');
        $categoryId = (int) ($_GET['category_id'] ?? 0);

        $data = [
            'admin_name' => $adminModel->getAdminName((int) ($_SESSION['user_id'] ?? 0)),
            'skills' => $adminModel->getAllMasterSkills($search, $categoryId),
            'categories' => $adminModel->getSkillCategories(),
            'search' => $search,
            'category_id' => $categoryId,
        ];
        $this->view('admin/skills', $data);
    }

    public function addSkill()
    {
        AuthGuard::requireLogin();
        if (($_SESSION['role'] ?? null) !== 'admin') {
            return $this->denyAccess();
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->redirect('/admin/skills');
        }

        $skillName  = trim($_POST['skill_name'] ?? '');
        $categoryId = (int) ($_POST['category_id'] ?? 0);

        if ($skillName !== '' && $categoryId > 0) {
            $this->model('Admin')->addSkill($skillName, $categoryId);
            Audit::write((int)$_SESSION['user_id'], 'skill_created', "Admin created master skill '{$skillName}'", 'master_skill', 0);
            return $this->redirect('/admin/skills?success=skill_created');
        }
        return $this->redirect('/admin/skills?error=invalid_data');
    }

    public function auditLogs()
    {
        AuthGuard::requireLogin();
        if (($_SESSION['role'] ?? null) !== 'admin') {
            return $this->denyAccess();
        }

        return $this->redirect('/admin/dashboard');
    }

    public function viewDocument()
    {
        AuthGuard::requireLogin();

        $rawFile = trim($_GET['file'] ?? '');
        if ($rawFile === '') {
            http_response_code(400);
            echo "Error: No file specified.";
            exit();
        }

        $filename = basename($rawFile);
        $filePath = null;

        try {
            $docModel = $this->model('Document');
            $docInfo  = $docModel->findByStoredFilename($filename);
            if ($docInfo && !empty($docInfo['path']) && is_file($docInfo['path'])) {
                $filePath = $docInfo['path'];
            }
        } catch (Throwable $e) {
            // Fallback candidate search
        }

        if (!$filePath) {
            $candidates = [
                BASE_PATH . 'storage/documents/' . $filename,
                BASE_PATH . 'public/assets/uploads/permits/' . $filename,
                BASE_PATH . 'storage/uploads/resumes/' . $filename,
                BASE_PATH . 'storage/uploads/profile_photos/' . $filename,
            ];
            foreach ($candidates as $cand) {
                if (is_file($cand)) {
                    $filePath = $cand;
                    break;
                }
            }
        }

        if (!$filePath || !is_file($filePath)) {
            http_response_code(404);
            header("Content-Type: text/plain");
            echo "Document file not found on server.";
            exit();
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeTypes = [
            'pdf'  => 'application/pdf',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif'  => 'image/gif',
        ];

        $contentType = $mimeTypes[$ext] ?? (mime_content_type($filePath) ?: 'application/octet-stream');

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: ' . $contentType);
        header('Content-Length: ' . filesize($filePath));
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=86400');
        readfile($filePath);
        exit();
    }

    // -------------------------------------------------------------------------
    // LOGOUT
    // -------------------------------------------------------------------------
    public function logout()
    {
        // Destroy the session completely before redirecting.
        session_unset();
        session_destroy();

        header("Location: /sikaphub/login?success=logged_out");
        exit();
    }

    // -------------------------------------------------------------------------
    // Shared helpers
    // -------------------------------------------------------------------------

    // Non-admin hitting an admin route: real 403 + the errors view, never a
    // die() with a bare string (C-44).
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
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePrefix = (strpos($scriptName, '/sikaphub/') === 0) ? '/sikaphub' : '';
        header('Location: ' . $basePrefix . $path);
        exit();
    }

    /**
     * Enqueue an in-app notification for the employer (UC-03 step 11, UC-00d).
     *
     * Q-18: a direct INSERT. The NotificationService (C-36) is still to be
     * built, and nothing renders the notifications table yet — this row is the
     * durable record, not a delivered message.
     *
     * Same rule as SMTP (CLAUDE.md): a failed enqueue is logged and swallowed.
     * It must never abort or roll back the verification decision that triggered
     * it — the decision is already committed by the time we get here.
     */
    private function enqueueNotification($userId, $eventType, $employerId, $title, $body)
    {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare(
                "INSERT INTO notifications
                    (user_id, event_type, entity_type, entity_id, title, body)
                 VALUES
                    (:user_id, :event_type, 'employer', :entity_id, :title, :body)"
            );
            $stmt->execute([
                ':user_id'    => $userId,
                ':event_type' => $eventType,
                ':entity_id'  => $employerId,
                ':title'      => mb_substr($title, 0, 150),
                ':body'       => mb_substr($body, 0, 500),
            ]);
        } catch (Throwable $e) {
            error_log('[notify] employer verification notification failed: ' . $e->getMessage());
        }
    }
}
