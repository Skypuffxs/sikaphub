<?php

// Inherit from the Base Controller
require_once BASE_PATH . 'app/core/Controller.php';

class HomeController extends Controller
{
    /**
     * Renders the public-facing landing page with real database jobs.
     */
    public function index()
    {
        // Redirect authenticated users to their active role dashboard
        if (!empty($_SESSION['user_id']) && !empty($_SESSION['role'])) {
            if ($_SESSION['role'] === 'jobseeker') {
                header("Location: /sikaphub/dashboard");
                exit();
            } elseif ($_SESSION['role'] === 'employer') {
                header("Location: /sikaphub/employer/dashboard");
                exit();
            } elseif ($_SESSION['role'] === 'admin') {
                header("Location: /sikaphub/admin/dashboard");
                exit();
            }
        }

        $featuredJobs = [];
        try {
            $jobModel = $this->model('Job');
            $featuredJobs = $jobModel->getFeaturedJobs(3);
        } catch (Throwable $e) {
            error_log("Failed to load featured jobs for landing page: " . $e->getMessage());
        }

        $this->view('home/index', [
            'featuredJobs' => $featuredJobs
        ]);
    }

    /**
     * Renders the Terms of Service page.
     */
    public function terms()
    {
        $this->view('home/terms');
    }

    /**
     * Renders the Privacy Policy page.
     */
    public function privacy()
    {
        $this->view('home/privacy');
    }

    /**
     * Renders the branded Offline page when no internet connection is available.
     */
    public function offline()
    {
        $this->view('home/offline');
    }
}

