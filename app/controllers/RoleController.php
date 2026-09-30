<?php

require_once BASE_PATH . 'app/core/Controller.php';
require_once BASE_PATH . 'app/helpers/AuthGuard.php';
require_once BASE_PATH . 'app/helpers/Audit.php';

/**
 * UC-01a — Select Account Role.
 *
 * users.role is NULL between account creation and this step. A newly verified
 * visitor is trapped here (by AuthGuard::requireRoleSelected) until they pick
 * Job Seeker or Employer. Admins are seeded with role='admin' and never reach
 * this screen.
 *
 * Routes: GET /select-role, POST /select-role
 */
class RoleController extends Controller
{
    const CHOICES = ['jobseeker', 'employer'];

    public function select()
    {
        AuthGuard::requireLogin();

        // A3 — role already chosen: skip the picker entirely.
        if (!empty($_SESSION['role'])) {
            $this->redirectForRole($_SESSION['role']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->view('auth/select_role', ['error' => null]);
            return;
        }

        // CSRF already verified in the base constructor.
        $role = $_POST['role'] ?? '';
        if (!in_array($role, self::CHOICES, true)) {
            // 'admin' or anything else is rejected here — this is a separate
            // guard from the routing check above, and stops a NULL-role user
            // forging the POST body to self-promote.
            $this->view('auth/select_role', ['error' => 'Choose Job Seeker or Employer to continue.']);
            return;
        }

        $userId = (int) $_SESSION['user_id'];
        if (!$this->model('User')->setRole($userId, $role)) {
            // role was already set between the guard and here — re-route.
            $fresh = $this->model('User')->findById($userId);
            $_SESSION['role'] = $fresh['role'];
            $this->redirectForRole($fresh['role']);
        }

        session_regenerate_id(true);
        $_SESSION['role'] = $role;

        Audit::write($userId, 'role_selected', 'Role set to ' . $role, 'user', $userId);

        $this->redirectForRole($role);
    }

    private function redirectForRole($role)
    {
        if ($role === 'admin') {
            $this->go('/admin/dashboard');
        }

        $pending = ($_SESSION['account_status'] ?? 'Pending') === 'Pending';

        if ($role === 'employer') {
            // C-29: /build-profile is the sole builder for both roles.
            $this->go($pending ? '/build-profile' : '/employer/dashboard');
        }
        $this->go($pending ? '/build-profile' : '/dashboard');
    }

    private function go($path)
    {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePrefix = (strpos($scriptName, '/sikaphub/') === 0) ? '/sikaphub' : '';
        header('Location: ' . $basePrefix . $path);
        exit();
    }
}
