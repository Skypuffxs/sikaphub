<?php

require_once BASE_PATH . 'app/core/Controller.php';
require_once BASE_PATH . 'app/helpers/AuthGuard.php';
require_once BASE_PATH . 'app/services/AIEngineService.php'; 

class JobController extends Controller
{
    // Schema-v2 enum members, single source of truth for the whitelists below.
    private const EMPLOYMENT_TYPES  = ['Full-time', 'Part-time', 'Contract', 'Internship'];
    private const WORK_ARRANGEMENTS = ['On-site', 'Remote', 'Hybrid'];
    private const REQUIREMENT_TYPES = ['Mandatory', 'Preferred'];
    private const MAX_YEARS_EXPERIENCE = 50;   // Q-17

    public function create()
    {
        // 1. Guardrails
        AuthGuard::requireActiveProfile();

        if (($_SESSION['role'] ?? null) !== 'employer') {
            http_response_code(403);
            $this->view('errors/500', ['code' => 403, 'message' => 'Only employers can post jobs.']);
            exit();
        }

        $employerModel = $this->model('Employer');
        $employerId = $employerModel->getEmployerId($_SESSION['user_id']);
        if (!$employerId) {
            // Active account, no employers row — back to the builder (C-44,
            // matching AuthGuard::requireCompleteEntity()).
            header('Location: /sikaphub/build-profile');
            exit();
        }

        // 2. Publish gate — anything other than Verified cannot post (UC-04 pre).
        $employerDetails = $employerModel->getEmployerDetails($employerId);
        if (($employerDetails['verified_status'] ?? 'Pending') !== 'Verified') {
            header('Location: /sikaphub/employer/dashboard?error=pending_verification');
            exit();
        }

        $jobModel     = $this->model('Job');
        $profileModel = $this->model('Profile');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->view('employer/post_job', [
                'municipalities' => $profileModel->getMunicipalities(),
                'skills'         => $jobModel->getMasterSkills(),
                'error'          => null,
                'old'            => [],
                'old_skills'     => [],
            ]);
            return;
        }

        // 3. Collect + validate. A validation failure re-renders the form with
        //    a message and the submitted values (HTTP 200) — never a die(),
        //    never a blank page, never a silent NULL on a public-facing column.
        $old = [
            'job_title'        => trim($_POST['job_title'] ?? ''),
            'job_description'  => trim($_POST['job_description'] ?? ''),
            'min_years_experience' => trim($_POST['min_years_experience'] ?? ''),
            'salary_range'     => trim($_POST['salary_range'] ?? ''),
            'employment_type'  => $_POST['employment_type'] ?? '',
            'work_arrangement' => $_POST['work_arrangement'] ?? '',
            'municipality_id'  => (string) ($_POST['municipality_id'] ?? ''),
        ];

        $selectedSkills = [];
        $oldSkills = [];
        if (isset($_POST['skills']) && is_array($_POST['skills'])) {
            foreach ($_POST['skills'] as $skillId) {
                $sid = (int) $skillId;
                if ($sid <= 0) {
                    continue;
                }
                $reqType = trim($_POST['requirement_type'][$skillId] ?? '');
                $selectedSkills[$sid] = $reqType;
                $oldSkills[] = ['skill_id' => $sid, 'requirement_type' => $reqType];
            }
        }

        $fail = function ($message) use ($profileModel, $jobModel, $old, $oldSkills) {
            $this->view('employer/post_job', [
                'municipalities' => $profileModel->getMunicipalities(),
                'skills'         => $jobModel->getMasterSkills(),
                'error'          => $message,
                'old'            => $old,
                'old_skills'     => $oldSkills,
            ]);
            exit();
        };

        if ($old['job_title'] === '' || $old['job_description'] === '') {
            $fail('Job title and description are required.');
        }

        // min_years_experience: TINYINT UNSIGNED NOT NULL — there is no column
        // sentinel for "unspecified", so the employer must state an integer
        // 0-50. "0" (no experience required) and a blank field are different
        // claims to an applicant, so a blank field is an error, not a 0.
        $expRaw = $old['min_years_experience'];
        if ($expRaw === '' || !ctype_digit($expRaw) || (int) $expRaw > self::MAX_YEARS_EXPERIENCE) {
            $fail('Minimum years of experience must be a whole number between 0 and ' . self::MAX_YEARS_EXPERIENCE . '.');
        }
        $minYears = (int) $expRaw;

        // employment_type renders on a public job ad — an invalid value is a
        // form error, not a silent default.
        if (!in_array($old['employment_type'], self::EMPLOYMENT_TYPES, true)) {
            $fail('Choose a valid employment type.');
        }
        if (!in_array($old['work_arrangement'], self::WORK_ARRANGEMENTS, true)) {
            $fail('Choose a valid work arrangement.');
        }

        $municipalityId = (int) $old['municipality_id'];
        if ($municipalityId <= 0) {
            $fail('Select the job location.');
        }

        if (empty($selectedSkills)) {
            $fail('Add at least one required skill.');
        }
        foreach ($selectedSkills as $reqType) {
            if (!in_array($reqType, self::REQUIREMENT_TYPES, true)) {
                // 'Optional' and anything else — M2 BR-1, C-20.
                $fail('Each skill must be marked Mandatory or Preferred.');
            }
        }
        if (!in_array('Mandatory', $selectedSkills, true)) {
            // UC-04 E1 / BR-3 — a Preferred-only vacancy cannot rank candidates.
            $fail('At least one skill must be Mandatory.');
        }

        $jobData = [
            'job_title'            => htmlspecialchars($old['job_title']),
            'job_description'      => htmlspecialchars($old['job_description']),
            'min_years_experience' => $minYears,
            'salary_range'         => $old['salary_range'] !== '' ? htmlspecialchars($old['salary_range']) : null,
            'employment_type'      => $old['employment_type'],
            'work_arrangement'     => $old['work_arrangement'],
            'municipality_id'      => $municipalityId,
        ];

        // 4. Persist.
        $newJobId = $jobModel->createJobPosting($employerId, $jobData, $selectedSkills);
        if (!$newJobId) {
            http_response_code(500);
            $this->view('errors/500', ['code' => 500, 'message' => 'We could not publish the job. Please try again.']);
            exit();
        }

        // 5. T2 — recompute this vacancy against every active seeker in one
        //    batch request. Per UC-04 E3 the posting stays committed even if
        //    scoring fails; the feed shows it without a percentage until a
        //    retry succeeds. No fabricated score is ever written.
        try {
            (new AIEngineService())->recomputeForJob($newJobId);
        } catch (Throwable $e) {
            error_log('[job publish] T2 recompute failed for job ' . $newJobId . ': ' . $e->getMessage());
        }

        header('Location: /sikaphub/employer/dashboard?job_posted=1');
        exit();
    }

    public function show()
    {
        AuthGuard::requireActiveProfile();

        $jobId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        if ($jobId === 0) {
            header("Location: /sikaphub/dashboard?error=invalid_job");
            exit();
        }

        $jobModel = $this->model('Job');

        // Strict IDOR Defense: Only fetch if status is 'Open'
        $job = $jobModel->getOpenJobDetails($jobId);

        if (!$job) {
            header("Location: /sikaphub/dashboard?error=job_unavailable");
            exit();
        }

        $hasApplied = false;
        $isSaved = false;

        if (($_SESSION['role'] ?? '') === 'jobseeker' && !empty($_SESSION['user_id'])) {
            $seekerModel = $this->model('JobSeeker');
            $jobseekerId = $seekerModel->getJobseekerIdByUserId($_SESSION['user_id']);
            if ($jobseekerId) {
                $hasApplied = $seekerModel->hasAlreadyApplied($jobseekerId, $jobId);
                $isSaved = $seekerModel->isJobSaved($jobseekerId, $jobId);
            }
        }

        $this->view('job/show', [
            'job' => $job,
            'has_applied' => $hasApplied,
            'is_saved' => $isSaved
        ]);
    }
}