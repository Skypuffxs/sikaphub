<?php

require_once BASE_PATH . 'app/core/Controller.php';
require_once BASE_PATH . 'app/helpers/AuthGuard.php';

class EmployerController extends Controller
{

    public function dashboard()
    {
        // 1. Strict Security Guardrails
        AuthGuard::requireActiveProfile();

        if ($_SESSION['role'] !== 'employer') {
            header("Location: /sikaphub/dashboard?error=unauthorized");
            exit();
        }

        $employerModel = $this->model('Employer');
        $userId = $_SESSION['user_id'];

        $employerId = $employerModel->getEmployerId($userId);
        if (!$employerId) {
            // Account is Active but no employers row — bounce to the builder,
            // matching AuthGuard::requireCompleteEntity(). Never die() (C-44).
            header('Location: /sikaphub/build-profile');
            exit();
        }

        // 2. Fetch the Employer's Jobs
        $jobs = $employerModel->getEmployerJobs($employerId);

        // 3. Fetch employer core details for the Verification Gate
        $employerDetails = $employerModel->getEmployerDetails($employerId);
        $verifiedStatus = $employerDetails['verified_status'] ?? 'Pending';

        // 4. Attach the ranked applicants to each job
        // SECURITY FIX: Pass $employerId as the second argument so the model query
        // enforces ownership; an employer can only receive applicants for their own jobs.
        if (!empty($jobs)) {
            foreach ($jobs as $key => $job) {
                $applicants = $employerModel->getRankedApplicantsForJob($job['job_id'], $employerId);

                // Convert decimal scores to clean percentages for the UI
                if (!empty($applicants)) {
                    foreach ($applicants as &$applicant) {
                        $applicant['match_percentage'] = round((float) $applicant['ai_match_score'] * 100);
                    }
                }

                $jobs[$key]['applicants'] = $applicants;
            }
        }

        // 5. Render the View
        $this->view('employer/dashboard', [
            'jobs'            => $jobs ?? [],
            'verified_status' => $verifiedStatus
        ]);
    }

    public function reviewCandidate()
    {
        AuthGuard::requireActiveProfile();

        if ($_SESSION['role'] !== 'employer') {
            header("Location: /sikaphub/dashboard?error=unauthorized");
            exit();
        }

        $employerModel = $this->model('Employer');
        $employerId = $employerModel->getEmployerId($_SESSION['user_id']);

        // 1. Capture ID from either GET or POST
        $appId = (int) ($_GET['app_id'] ?? $_POST['app_id'] ?? 0);

        if ($appId === 0) {
            header("Location: /sikaphub/employer/dashboard?error=invalid_application");
            exit();
        }

        // 2. IDOR Firewall: Fetch application FIRST to verify ownership.
        //    Every model call on this path is wrapped so a database failure
        //    renders errors/500 with a real status code — never a blank page,
        //    a die(), or a leaked stack trace (C-44). A visible error beats a
        //    plausible wrong screen.
        try {
            $application = $employerModel->getApplicationDetails($appId, $employerId);
            if (!$application) {
                header("Location: /sikaphub/employer/dashboard?error=access_denied_or_not_found");
                exit();
            }

            // 3. Handle Status Update (POST Request)
            //    CSRF is already verified for every POST in the base Controller
            //    constructor (CSRF::verifyRequest) — no second check here.
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $newStatus = $_POST['status'] ?? '';
                $currentStatus = $application['application_status'];
                $allowedStatuses = ['Pending', 'Reviewed', 'Accepted', 'Rejected'];

                if (!in_array($newStatus, $allowedStatuses, true)) {
                    header("Location: /sikaphub/employer/review-candidate?app_id=" . $appId . "&error=invalid_status");
                    exit();
                }

                // State Machine Guard: Prevent redundant updates
                if ($newStatus === $currentStatus) {
                    header("Location: /sikaphub/employer/review-candidate?app_id=" . $appId . "&status_updated=1");
                    exit();
                }

                // State Machine Guard: Prevent reverting backwards to Pending
                if ($newStatus === 'Pending' && $currentStatus !== 'Pending') {
                    header("Location: /sikaphub/employer/review-candidate?app_id=" . $appId . "&error=invalid_transition");
                    exit();
                }

                // updateApplicationStatus re-enforces ownership in the UPDATE's
                // own JOIN, so a cross-employer write cannot land even if this
                // point were reached without the firewall above.
                if ($employerModel->updateApplicationStatus($appId, $employerId, $newStatus)) {
                    header("Location: /sikaphub/employer/review-candidate?app_id=" . $appId . "&status_updated=1");
                    exit();
                } else {
                    header("Location: /sikaphub/employer/review-candidate?app_id=" . $appId . "&error=database_error");
                    exit();
                }
            }

            // 4. Handle View Rendering (GET Request)
            $jobseekerId = $application['jobseeker_id'];
            $data = [
                'app'        => $application,
                'resume'     => $employerModel->getSeekerResume($jobseekerId),
                'education'  => $employerModel->getSeekerEducation($jobseekerId),
                'experience' => $employerModel->getSeekerExperience($jobseekerId)
            ];
        } catch (Throwable $e) {
            error_log('[review-candidate] load failed for app_id ' . $appId . ': ' . $e->getMessage());
            http_response_code(500);
            $this->view('errors/500', [
                'code'    => 500,
                'message' => 'We could not load this application right now. Please try again.'
            ]);
            exit();
        }

        $this->view('employer/review_candidate', $data);
    }

    /**
     * GET /company/view?id=... — Renders public employer company profile
     */
    public function showPublicProfile()
    {
        AuthGuard::requireActiveProfile();

        $employerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($employerId === 0) {
            header('Location: /sikaphub/dashboard?error=invalid_employer');
            exit();
        }

        $employerModel = $this->model('Employer');
        $company = $employerModel->getPublicCompanyProfile($employerId);

        if (!$company) {
            header('Location: /sikaphub/dashboard?error=company_not_found');
            exit();
        }

        $openJobs = $employerModel->getOpenJobsForEmployer($employerId);

        $this->view('employer/public_profile', [
            'company' => $company,
            'open_jobs' => $openJobs
        ]);
    }

    /**
     * GET/POST /employer/upload-permit — Business Permit Upload & AI Verification
     */
    public function uploadPermit()
    {
        AuthGuard::requireActiveProfile();

        if (($_SESSION['role'] ?? '') !== 'employer') {
            header("Location: /sikaphub/dashboard?error=unauthorized");
            exit();
        }

        $employerModel = $this->model('Employer');
        $userId = $_SESSION['user_id'];
        $employerId = $employerModel->getEmployerId($userId);

        if (!$employerId) {
            header('Location: /sikaphub/build-profile');
            exit();
        }

        $employer = $employerModel->getEmployerDetails($employerId);
        $error = null;
        $success = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_FILES['permit_file']) || $_FILES['permit_file']['error'] !== UPLOAD_ERR_OK) {
                $error = 'Please select a valid business permit document to upload.';
            } else {
                $file = $_FILES['permit_file'];
                $maxSize = 10 * 1024 * 1024; // 10MB limit

                if ($file['size'] > $maxSize) {
                    $error = 'The uploaded document exceeds the maximum 10MB limit.';
                } else {
                    $tmpPath = $file['tmp_name'];
                    $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
                    $allowedExts  = ['pdf', 'jpg', 'jpeg', 'png'];

                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $detectedMime = finfo_file($finfo, $tmpPath);
                    finfo_close($finfo);

                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                    if (!in_array($detectedMime, $allowedMimes, true) || !in_array($ext, $allowedExts, true)) {
                        $error = 'Invalid file format. Only PDF, JPG, and PNG documents are allowed.';
                    } else {
                        $uploadDir = BASE_PATH . 'public/assets/uploads/permits/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }

                        $safeFileName = 'permit_emp_' . $employerId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                        $destination = $uploadDir . $safeFileName;
                        $webPath = '/sikaphub/public/assets/uploads/permits/' . $safeFileName;

                        if (move_uploaded_file($tmpPath, $destination)) {
                            $verificationStatus = 'pending';
                            $aiFeedback = 'Permit document uploaded successfully and queued for admin audit.';
                            $extractedData = null;

                            try {
                                $aiService = new AIEngineService();
                                $aiResult  = $aiService->verifyBusinessPermit($destination);

                                $verificationStatus = $aiResult['verification_status'] ?? 'pending';
                                $aiFeedback         = $aiResult['ai_feedback'] ?? ($aiResult['feedback'] ?? 'AI Engine audit complete.');
                                $extractedData      = $aiResult['extracted_permit_data'] ?? ($aiResult['extracted_data'] ?? null);
                            } catch (Exception $e) {
                                error_log('[EmployerController] AI permit verification failed: ' . $e->getMessage());
                                $aiFeedback = 'Permit uploaded successfully. AI Engine processing unavailable: queued for manual PESO Admin review.';
                            }

                            $employerModel->updatePermitVerification(
                                (int) $employerId,
                                $webPath,
                                $verificationStatus,
                                $aiFeedback,
                                $extractedData
                            );

                            $statusLabel = ($verificationStatus === 'green_flag') 
                                ? 'Green Flag (Valid)' 
                                : (($verificationStatus === 'red_flag') ? 'Red Flag (Audit Needed)' : 'Pending Review');

                            $success = "Business permit uploaded and analyzed successfully. AI Classification: {$statusLabel}.";
                            $employer = $employerModel->getEmployerDetails($employerId);
                        } else {
                            $error = 'Failed to save uploaded document. Please check directory permissions and try again.';
                        }
                    }
                }
            }
        }

        $this->view('employer/upload_permit', [
            'employer' => $employer,
            'error'    => $error,
            'success'  => $success
        ]);
    }

    /**
     * POST /employer/compare-candidates
     * JSON API Endpoint: Compare selected applicants for a job post using AI Tie-Breaker evaluation.
     */
    public function compareCandidates()
    {
        AuthGuard::requireActiveProfile();

        if ($_SESSION['role'] !== 'employer') {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
            exit();
        }

        $jobId = (int) ($_POST['job_id'] ?? 0);
        $candidateIdsRaw = $_POST['jobseeker_ids'] ?? [];

        if (is_string($candidateIdsRaw)) {
            $candidateIdsRaw = explode(',', $candidateIdsRaw);
        }

        $candidateIds = array_filter(array_map('intval', (array) $candidateIdsRaw));

        $employerModel = $this->model('Employer');
        $employerId = $employerModel->getEmployerId($_SESSION['user_id']);

        // Ownership Scope Check: Ensure the job belongs to this employer
        $jobs = $employerModel->getEmployerJobs($employerId);
        $ownedJobIds = array_map(fn($j) => (int) $j['job_id'], $jobs);

        if (!in_array($jobId, $ownedJobIds, true)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Forbidden: You do not own this job posting.']);
            exit();
        }

        // If no candidate IDs specified, automatically compare all applicants for this job
        if (empty($candidateIds)) {
            $applicants = $employerModel->getRankedApplicantsForJob($jobId, $employerId);
            $candidateIds = array_filter(array_map(fn($a) => (int) ($a['jobseeker_id'] ?? 0), $applicants));
        }

        if ($jobId <= 0 || empty($candidateIds)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'No applicants available to compare for this position.']);
            exit();
        }

        try {
            $aiService = new AIEngineService();
            $feedback = $aiService->getCandidateFeedback($jobId, $candidateIds);

            header('Content-Type: application/json');
            echo json_encode([
                'status'    => 'success',
                'job_id'    => $jobId,
                'feedback'  => $feedback
            ]);
            exit();
        } catch (Throwable $e) {
            error_log('[compareCandidates] Error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Failed to generate AI candidate comparison: ' . $e->getMessage()
            ]);
            exit();
        }
    }
}