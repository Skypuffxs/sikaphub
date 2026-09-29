<?php

require_once BASE_PATH . 'app/core/Controller.php';
require_once BASE_PATH . 'app/helpers/AuthGuard.php';
require_once BASE_PATH . 'app/services/AIEngineService.php';

class JobSeekerController extends Controller
{

    public function dashboard()
    {
        // 1. Strict Security Guardrails
        AuthGuard::requireActiveProfile();

        if ($_SESSION['role'] !== 'jobseeker') {
            http_response_code(403);
            $this->view('errors/500', ['code' => 403, 'message' => 'Only job seekers can view this dashboard.']);
            exit();
        }

        $jobSeekerModel = $this->model('JobSeeker');

        // 2. Assemble the feed. Every read is precomputed or relational — zero
        //    calls to the matching service (UC-05 BR-5, C-35). The query budget
        //    is a flat four regardless of card count:
        //      1. the ranked feed (job_match_scores LEFT JOIN, §6.6 dual key)
        //      2. the seeker's approved skill-id set
        //      3. one batched job_required_skills lookup for the whole feed
        //      4. the seeker context (home municipality + profile_completeness)
        //    A model failure is a visible 500, never a die() or a blank page.
        try {
            $jobseekerId = $jobSeekerModel->getJobseekerIdByUserId($_SESSION['user_id']);
            if (!$jobseekerId) {
                http_response_code(500);
                $this->view('errors/500', ['code' => 500, 'message' => 'Your job seeker profile is missing. Please rebuild it.']);
                exit();
            }

            $jobs = $jobSeekerModel->getRecommendationFeed($jobseekerId);
            $approvedSkillIds = $jobSeekerModel->getApprovedSkillIds($jobseekerId);
            $jobIds = array_column($jobs, 'job_id');
            $requiredSkillRows = $jobSeekerModel->getRequiredSkillsForJobs($jobIds);
            $context = $jobSeekerModel->getSeekerContext($jobseekerId);
        } catch (Throwable $e) {
            error_log('[dashboard] feed assembly failed: ' . $e->getMessage());
            http_response_code(500);
            $this->view('errors/500', ['code' => 500, 'message' => 'We could not load your recommendations right now. Please try again.']);
            exit();
        }

        // 3. Partition each job's requirements into matched / unmatched /
        //    awaiting-approval (§6.4 / C-42). Pending skills are shown with a
        //    distinct chip and excluded from met/total.
        $skillBreakdown = self::partitionRequiredSkills($requiredSkillRows, $approvedSkillIds);

        // 4. Unscored open jobs still render (D-15). Surface the count so the
        //    seeker knows scoring is queued, not broken.
        $awaitingScoringCount = 0;
        foreach ($jobs as $job) {
            if ($job['final_score'] === null) {
                $awaitingScoringCount++;
            }
        }

        $this->view('jobseeker/dashboard', [
            'jobs' => $jobs,
            'skillBreakdown' => $skillBreakdown,
            'awaitingScoringCount' => $awaitingScoringCount,
            'profileCompleteness' => (int) ($context['profile_completeness'] ?? 0),
            'hasHomeMunicipality' => !empty($context['home_municipality_id']),
            'context' => $context ?: [],
        ]);
    }

    /**
     * Group required-skill rows by job_id and split each job's skills into
     * matched (seeker has it, approved), unmatched (approved, seeker lacks it)
     * and pending (master_skills.status = 'pending' — stored but excluded from
     * matching, §6.4 / C-42). Rejected skills are dropped entirely. Pure
     * function — no I/O.
     */
    private static function partitionRequiredSkills(array $rows, array $approvedSkillIds): array
    {
        $seekerSkills = array_flip(array_map('intval', $approvedSkillIds));
        $out = [];
        foreach ($rows as $row) {
            $jobId = (int) $row['job_id'];
            if (!isset($out[$jobId])) {
                $out[$jobId] = ['matched' => [], 'unmatched' => [], 'pending' => []];
            }
            $name = $row['skill_name'];
            if ($row['status'] === 'pending') {
                $out[$jobId]['pending'][] = $name;
            } elseif ($row['status'] === 'approved') {
                $bucket = isset($seekerSkills[(int) $row['skill_id']]) ? 'matched' : 'unmatched';
                $out[$jobId][$bucket][] = $name;
            }
        }
        return $out;
    }

    public function apply()
    {
        // 1. Strict Security Guardrails
        AuthGuard::requireActiveProfile();

        if ($_SESSION['role'] !== 'jobseeker') {
            header("Location: /sikaphub/dashboard?error=unauthorized");
            exit();
        }

        // 2. Enforce POST request to prevent CSRF via URL manipulation
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: /sikaphub/dashboard");
            exit();
        }

        // 2a. Strict CSRF Token Verification
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            die("Security Violation: Invalid CSRF token.");
        }

        $jobId = isset($_POST['job_id']) ? (int) $_POST['job_id'] : 0;
        $userId = $_SESSION['user_id'];

        if ($jobId === 0) {
            header("Location: /sikaphub/dashboard?error=invalid_job");
            exit();
        }

        $jobSeekerModel = $this->model('JobSeeker');

        // V2 FIX: Map the User ID to the Relational JobSeeker ID
        $jobseekerId = $jobSeekerModel->getJobseekerIdByUserId($userId);

        // 3. Graceful Duplicate Check (No more dead-end echoes)
        if ($jobSeekerModel->hasAlreadyApplied($jobseekerId, $jobId)) {
            header("Location: /sikaphub/dashboard?error=already_applied");
            exit();
        }

        // 4. Authoritative point-in-time score (T4). Fail loud: if the engine
        //    cannot produce a real score, the application is not submitted.
        //    A fabricated 0 here is what the employer would later evaluate.
        $aiService = new AIEngineService();
        try {
            $result = $aiService->computeMatch($jobId, $jobseekerId);
            $authoritativeScore = $result['final_score'];
        } catch (Throwable $e) {
            error_log('[apply] authoritative match computation failed: ' . $e->getMessage());
            header("Location: /sikaphub/dashboard?error=score_unavailable");
            exit();
        }

        // 5. Execute Point-in-Time Capture
        if ($jobSeekerModel->applyForJob($jobseekerId, $jobId, $authoritativeScore)) {
            header("Location: /sikaphub/my-applications?success=applied");
            exit();
        }
        header("Location: /sikaphub/dashboard?error=system_error");
        exit();
    }

    public function tracker()
    {
        // 1. Strict Security Guardrails
        AuthGuard::requireActiveProfile();

        if ($_SESSION['role'] !== 'jobseeker') {
            die("Access Denied: Only job seekers can track applications.");
        }

        $jobSeekerModel = $this->model('JobSeeker');
        $userId = $_SESSION['user_id'];

        // V2 FIX: Fetch actual JobSeeker PK
        $jobseekerId = $jobSeekerModel->getJobseekerIdByUserId($userId);

        // 2. Fetch historical applications using the relational ID & seeker context
        $applications = $jobSeekerModel->getMyApplications($jobseekerId);
        $context = $jobSeekerModel->getSeekerContext($jobseekerId);

        // 3. Format the scores for the UI
        if (!empty($applications)) {
            foreach ($applications as &$app) {
                $app['match_percentage'] = round((float) $app['ai_match_score'] * 100);
            }
        }

        // 4. Render the View
        $this->view('jobseeker/tracker', [
            'applications' => $applications ?? [],
            'context' => $context ?: [],
        ]);
    }

    /**
     * POST /jobseeker/toggle-save-job — AJAX endpoint to bookmark/unbookmark a job
     */
    public function toggleSaveJob()
    {
        AuthGuard::requireActiveProfile();
        header('Content-Type: application/json');

        if (($_SESSION['role'] ?? '') !== 'jobseeker') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only jobseekers can save jobs.']);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
            return;
        }

        $csrfToken = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!CSRF::validateToken($csrfToken)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid or expired CSRF token.']);
            return;
        }

        $jobId = (int) ($_POST['job_id'] ?? 0);
        if ($jobId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid job ID.']);
            return;
        }

        $userId = $_SESSION['user_id'];
        $jobSeekerModel = $this->model('JobSeeker');
        $jobseekerId = $jobSeekerModel->getJobseekerIdByUserId($userId);

        if (!$jobseekerId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Candidate profile not found.']);
            return;
        }

        $isSaved = $jobSeekerModel->toggleSaveJob($jobseekerId, $jobId);

        echo json_encode([
            'success' => true,
            'job_id' => $jobId,
            'is_saved' => $isSaved,
            'message' => $isSaved ? 'Job saved to your bookmarks!' : 'Job removed from your saved list.'
        ]);
    }

    /**
     * GET /saved-jobs — Renders the seeker's bookmarked jobs page
     */
    public function savedJobs()
    {
        AuthGuard::requireActiveProfile();

        if (($_SESSION['role'] ?? '') !== 'jobseeker') {
            header('Location: /sikaphub/dashboard');
            exit();
        }

        $jobSeekerModel = $this->model('JobSeeker');
        $userId = $_SESSION['user_id'];
        $jobseekerId = $jobSeekerModel->getJobseekerIdByUserId($userId);

        $savedJobs = $jobSeekerModel->getSavedJobs($jobseekerId);
        $context = $jobSeekerModel->getSeekerContext($jobseekerId);

        $this->view('jobseeker/saved_jobs', [
            'saved_jobs' => $savedJobs ?? [],
            'context' => $context ?: [],
        ]);
    }
}