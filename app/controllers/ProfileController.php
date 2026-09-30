<?php

require_once BASE_PATH . 'app/core/Controller.php';
require_once BASE_PATH . 'app/helpers/AuthGuard.php';
require_once BASE_PATH . 'app/helpers/FileUpload.php';
require_once BASE_PATH . 'app/services/AIEngineService.php';

class ProfileController extends Controller
{
    public function buildProfile()
    {
        AuthGuard::requireRoleSelected();

        $role = $_SESSION['role'];
        $profileModel = $this->model('Profile');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($role === 'jobseeker') {
                $this->processJobSeekerUpdate($profileModel);
            } elseif ($role === 'employer') {
                $this->processEmployerUpdate($profileModel);
            } else {
                http_response_code(403);
                $this->view('errors/500', ['code' => 403, 'message' => 'Your account role cannot access this page.']);
                exit();
            }
        } else {
            if ($role === 'jobseeker') {
                $data = $this->loadJobSeekerData($profileModel);
                $this->view('profile/seeker_builder', $data);
            } else {
                $data = $this->loadEmployerData($profileModel);
                $this->view('profile/employer_builder', $data);
            }
        }
    }

    /**
     * GET /profile/barangays?municipality_id=N — JSON, drives the
     * municipality -> barangay cascade on the seeker builder.
     */
    public function barangays()
    {
        AuthGuard::requireRoleSelected();
        header('Content-Type: application/json');

        $municipalityId = (int) ($_GET['municipality_id'] ?? 0);
        if ($municipalityId <= 0) {
            echo '[]';
            return;
        }
        echo json_encode($this->model('Profile')->getBarangays($municipalityId));
    }

    /**
     * POST /profile/parse-resume — AJAX endpoint to upload candidate CV/resume,
     * parse structured profile attributes using AI Engine, and record upload in DB.
     */
    public function parseResume()
    {
        AuthGuard::requireRoleSelected();
        header('Content-Type: application/json');

        if ($_SESSION['role'] !== 'jobseeker') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only job seekers can parse resumes.']);
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

        if (!isset($_FILES['resume_file']) || $_FILES['resume_file']['error'] === UPLOAD_ERR_NO_FILE) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Please select a resume file to upload.']);
            return;
        }

        $userId = (int) $_SESSION['user_id'];
        $uploadDir = $this->uploadDir('storage/uploads/resumes/');

        try {
            $allowedTypes = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/msword'];
            $storedFilename = FileUpload::secureUpload($_FILES['resume_file'], $uploadDir, $allowedTypes, 5);
            $fullPath = BASE_PATH . 'storage/uploads/resumes/' . $storedFilename;
            $originalFilename = $_FILES['resume_file']['name'] ?? 'resume.pdf';
            $fileSizeBytes = (int) ($_FILES['resume_file']['size'] ?? 0);
            $fileHash = hash_file('sha256', $fullPath) ?: '';

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $fullPath) ?: 'application/octet-stream';
            finfo_close($finfo);

            // Call Python AI Engine via AIEngineService (takes 10-30s)
            $aiEngine = new AIEngineService();
            $parseResult = $aiEngine->parseResumeFile($fullPath);

            // Re-verify database connection and instantiate fresh models after long AI HTTP request
            Database::getInstance()->getConnection();
            $profileModel = $this->model('Profile');
            $jobseekerId = $this->model('JobSeeker')->getJobseekerIdByUserId($userId);

            $parsedPayload = $parseResult['profile'] ?? null;
            $status = ($parseResult['status'] ?? '') === 'success' ? 'parsed' : 'failed';
            $error = $parseResult['message'] ?? null;

            $uploadId = $profileModel->saveResumeUpload(
                $userId,
                $storedFilename,
                $originalFilename,
                $fileHash,
                $mimeType,
                $fileSizeBytes,
                $status,
                $parsedPayload,
                $error,
                '1.0.0',
                $jobseekerId
            );

            echo json_encode([
                'success' => true,
                'upload_id' => $uploadId,
                'stored_filename' => $storedFilename,
                'profile' => $parsedPayload
            ]);
        } catch (Throwable $e) {
            error_log('[profile] Resume parsing failed for user ' . $userId . ': ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Resume parsing failed: ' . $e->getMessage()
            ]);
        }
    }

    // =========================================================================
    // JOB SEEKER LOGIC ISOLATION
    // =========================================================================

    private function processJobSeekerUpdate($profileModel)
    {
        $userId = $_SESSION['user_id'];
        $existingProfile = $profileModel->getSeekerProfile($userId) ?? [];

        // 1. Profile photo — magic-byte validated, randomized filename, routed
        //    through FileUpload::secureUpload() like every other upload. This is
        //    the write-side fix for what Commit A mitigated on the read side:
        //    a non-image can no longer reach storage/uploads/profile_photos/.
        $photoPath = $existingProfile['profile_photo'] ?? null;
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            try {
                $photoPath = FileUpload::secureUpload(
                    $_FILES['profile_photo'],
                    $this->uploadDir('storage/uploads/profile_photos/'),
                    ['image/jpeg', 'image/png', 'image/webp'],
                    2
                );
            } catch (RuntimeException $e) {
                error_log('[profile] seeker photo rejected for user ' . $userId . ': ' . $e->getMessage());
                http_response_code(400);
                $this->view('errors/500', ['code' => 400, 'message' => 'That photo must be a JPG, PNG, or WebP under 2 MB.']);
                exit();
            }
        }

        // 1b. Resume / CV file upload — magic-byte validated, saved to storage/uploads/resumes/
        //     and registered in resume_uploads table.
        if (isset($_FILES['resume_file']) && $_FILES['resume_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            try {
                $allowedTypes = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/msword'];
                $storedFilename = FileUpload::secureUpload($_FILES['resume_file'], $this->uploadDir('storage/uploads/resumes/'), $allowedTypes, 5);
                $fullPath = BASE_PATH . 'storage/uploads/resumes/' . $storedFilename;
                $originalFilename = $_FILES['resume_file']['name'] ?? 'resume.pdf';
                $fileSizeBytes = (int) ($_FILES['resume_file']['size'] ?? 0);
                $fileHash = hash_file('sha256', $fullPath) ?: '';

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $fullPath) ?: 'application/octet-stream';
                finfo_close($finfo);

                $parsedPayload = null;
                $status = 'pending';
                $error = null;

                try {
                    $aiEngine = new AIEngineService();
                    $parseResult = $aiEngine->parseResumeFile($fullPath);
                    $parsedPayload = $parseResult['profile'] ?? null;
                    $status = ($parseResult['status'] ?? '') === 'success' ? 'parsed' : 'failed';
                    $error = $parseResult['message'] ?? null;
                } catch (Throwable $pe) {
                    error_log('[profile] AI Engine parse error on main form submit: ' . $pe->getMessage());
                }

                $jobseekerId = $this->model('JobSeeker')->getJobseekerIdByUserId($userId);
                $profileModel->saveResumeUpload(
                    $userId,
                    $storedFilename,
                    $originalFilename,
                    $fileHash,
                    $mimeType,
                    $fileSizeBytes,
                    $status,
                    $parsedPayload,
                    $error,
                    '1.0.0',
                    $jobseekerId
                );
            } catch (RuntimeException $e) {
                error_log('[profile] seeker resume rejected for user ' . $userId . ': ' . $e->getMessage());
                http_response_code(400);
                $this->view('errors/500', ['code' => 400, 'message' => 'The uploaded resume must be a PDF or DOCX file under 5 MB.']);
                exit();
            }
        }

        // 2. Identity, 3NF location, privacy
        $firstName = htmlspecialchars(trim($_POST['first_name'] ?? ''));
        $lastName = htmlspecialchars(trim($_POST['last_name'] ?? ''));
        $visibility = in_array($_POST['profile_visibility'] ?? '', ['Public', 'Private'], true)
            ? $_POST['profile_visibility'] : 'Public';
        $homeMunicipalityId = isset($_POST['home_municipality_id']) && $_POST['home_municipality_id'] !== ''
            ? (int) $_POST['home_municipality_id'] : null;
        $barangayId = isset($_POST['barangay_id']) && $_POST['barangay_id'] !== ''
            ? (int) $_POST['barangay_id'] : null;

        // 3. Preferred work locations
        $preferredMunicipalityIds = [];
        if (!empty($_POST['preferred_municipality_ids']) && is_array($_POST['preferred_municipality_ids'])) {
            $preferredMunicipalityIds = array_map('intval', $_POST['preferred_municipality_ids']);
        }

        $preferences = [
            'desired_job_type' => trim($_POST['desired_job_type'] ?? ''),
            'preferred_work_setup' => trim($_POST['preferred_work_setup'] ?? ''),
            'expected_salary' => isset($_POST['expected_salary']) && $_POST['expected_salary'] !== '' ? (float) $_POST['expected_salary'] : null,
            'preferred_municipality_ids' => $preferredMunicipalityIds,
        ];

        // 4. Education rows
        $educationData = [];
        if (!empty($_POST['education']) && is_array($_POST['education'])) {
            foreach ($_POST['education'] as $edu) {
                if (empty(trim($edu['institution'] ?? ''))) continue;
                $educationData[] = [
                    'degree_level' => htmlspecialchars(trim($edu['degree_level'] ?? '')),
                    'school_name' => htmlspecialchars(trim($edu['institution'] ?? '')),
                    'year_graduated' => trim($edu['year_graduated'] ?? ''),
                ];
            }
        }

        // 5. Work experience rows. The "I have work experience" toggle submits
        //    has_work_experience=1 when on; its absence means "no experience".
        $noExperienceDeclared = !isset($_POST['has_work_experience']);
        $experienceData = [];
        if (!$noExperienceDeclared && !empty($_POST['experience']) && is_array($_POST['experience'])) {
            foreach ($_POST['experience'] as $exp) {
                if (empty(trim($exp['job_title'] ?? ''))) continue;
                $experienceData[] = [
                    'job_title' => htmlspecialchars(trim($exp['job_title'] ?? '')),
                    'company_name' => htmlspecialchars(trim($exp['company'] ?? '')),
                    'start_date' => trim($exp['start_date'] ?? ''),
                    'end_date' => trim($exp['end_date'] ?? ''),
                    'job_description' => '',
                ];
            }
        }

        // 6. Skills — the TomSelect submits a comma list of approved skill_ids
        //    (numeric) mixed with newly typed skill names (non-numeric).
        $standardSkillIds = [];
        $customSkills = [];
        if (!empty($_POST['skills'])) {
            foreach (explode(',', $_POST['skills']) as $token) {
                $token = trim($token);
                if ($token === '') continue;
                if (ctype_digit($token)) {
                    $standardSkillIds[] = (int) $token;
                } else {
                    $customSkills[] = htmlspecialchars($token);
                }
            }
        }

        // 6b. If a new resume file was uploaded and parsed, fallback to newly parsed CV attributes
        //     for missing/empty form fields so the profile is updated with ONLY the new CV info.
        if (!empty($parsedPayload)) {
            if (empty($firstName) && empty($lastName) && !empty($parsedPayload['name']) && $parsedPayload['name'] !== 'Candidate Name Not Found') {
                $cleanName = trim(str_replace(',', '', $parsedPayload['name']));
                $nameParts = preg_split('/\s+/', $cleanName);
                if (count($nameParts) > 1) {
                    $lastName = htmlspecialchars(array_pop($nameParts));
                    $firstName = htmlspecialchars(implode(' ', $nameParts));
                } elseif (count($nameParts) === 1) {
                    $firstName = htmlspecialchars($nameParts[0]);
                }
            }

            if (empty($educationData) && !empty($parsedPayload['education']) && is_array($parsedPayload['education'])) {
                foreach ($parsedPayload['education'] as $edu) {
                    $school = trim($edu['institution'] ?? ($edu['school_name'] ?? ''));
                    if (str_starts_with($school, '•') || str_starts_with($school, '-')) {
                        $school = '';
                    }
                    if (empty($school)) continue;
                    $deg = trim(($edu['degree_level'] ?? '') . ' ' . ($edu['degree_or_level'] ?? ''));
                    $yrMatch = [];
                    preg_match('/\b(19\d{2}|20\d{2})\b/', (string)($edu['year'] ?? ($edu['year_graduated'] ?? '')), $yrMatch);
                    $educationData[] = [
                        'degree_level' => htmlspecialchars($deg),
                        'school_name' => htmlspecialchars($school),
                        'year_graduated' => $yrMatch[0] ?? '',
                    ];
                }
            }

            if (empty($experienceData) && !empty($parsedPayload['experience']) && is_array($parsedPayload['experience'])) {
                foreach ($parsedPayload['experience'] as $exp) {
                    $title = trim($exp['title'] ?? '');
                    $comp = trim($exp['company_or_details'] ?? ($exp['company'] ?? ''));
                    if (empty($title) && empty($comp)) continue;
                    $experienceData[] = [
                        'job_title' => htmlspecialchars($title),
                        'company_name' => htmlspecialchars($comp),
                        'start_date' => '',
                        'end_date' => '',
                        'job_description' => '',
                    ];
                }
                if (!empty($experienceData)) {
                    $noExperienceDeclared = false;
                }
            }

            if (empty($standardSkillIds) && empty($customSkills) && !empty($parsedPayload['skills']['all_skills']) && is_array($parsedPayload['skills']['all_skills'])) {
                $approvedSkills = $profileModel->getAllApprovedSkills();
                $approvedMap = [];
                foreach ($approvedSkills as $as) {
                    $approvedMap[strtolower(trim($as['skill_name']))] = (int) $as['skill_id'];
                }
                foreach ($parsedPayload['skills']['all_skills'] as $skName) {
                    $skNameTrim = trim($skName);
                    if ($skNameTrim === '') continue;
                    $lowerSk = strtolower($skNameTrim);
                    if (isset($approvedMap[$lowerSk])) {
                        $standardSkillIds[] = $approvedMap[$lowerSk];
                    } else {
                        $customSkills[] = htmlspecialchars($skNameTrim);
                    }
                }
                $standardSkillIds = array_values(array_unique($standardSkillIds));
                $customSkills = array_values(array_unique($customSkills));
            }
        }

        // 7. Payload for the model
        $payload = [
            'user_id' => $userId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'home_municipality_id' => $homeMunicipalityId,
            'barangay_id' => $barangayId,
            'visibility' => $visibility,
            'profile_photo' => $photoPath,
            'preferences' => $preferences,
            'education' => $educationData,
            'experience' => $experienceData,
            'no_experience_declared' => $noExperienceDeclared,
            'standard_skills' => $standardSkillIds,
            'custom_skills' => $customSkills,
        ];

        // 8. Persist the profile in one transaction, then fire T1.
        if ($profileModel->saveCompleteSeekerProfile($payload)) {
            $_SESSION['account_status'] = 'Active';

            // T1 (M2 §7.1, UC-05r) — recompute this seeker against every open
            // job in one batch request, AFTER the profile transaction has
            // committed. Never inside the transaction: a scoring call must not
            // hold a DB write lock, and a scoring failure must not roll back a
            // saved profile. On failure we log the error text and move on —
            // no fabricated score is written (CLAUDE.md, UC-05 BR-7); the pair
            // simply has no job_match_scores row until a later trigger
            // succeeds, and the dashboard shows that job as "match pending".
            // Same shape as the T2 call in JobController::create().
            try {
                $jobseekerId = $this->model('JobSeeker')->getJobseekerIdByUserId($userId);
                if ($jobseekerId) {
                    (new AIEngineService())->recomputeForSeeker($jobseekerId);
                }
            } catch (Throwable $e) {
                error_log('[profile] T1 recompute failed for user ' . $userId . ': ' . $e->getMessage());
            }

            header("Location: /dashboard?profile_updated=1");
            exit();
        }

        http_response_code(500);
        $this->view('errors/500', ['code' => 500, 'message' => 'We could not save your profile. Please try again.']);
        exit();
    }

    private function loadJobSeekerData($profileModel)
    {
        return [
            'master_skills' => $profileModel->getAllApprovedSkills(),
            'municipalities' => $profileModel->getMunicipalities(),
            'existing_profile' => $profileModel->getSeekerProfile($_SESSION['user_id']) ?? [],
        ];
    }

    // =========================================================================
    // EMPLOYER LOGIC ISOLATION
    // =========================================================================

    /**
     * GET renders the builder; POST creates (no employers row yet) or edits
     * (row present, scoped by session user_id — never a form field). One route,
     * M2 §5.1 / §8. The Pending-employer Draft-jobs path of §8 is deferred
     * (Q-14); a new employer lands on the dashboard with the under-review
     * banner and cannot publish until a PESO admin verifies.
     */
    private function processEmployerUpdate($profileModel)
    {
        $userId   = (int) $_SESSION['user_id'];
        $existing = $profileModel->getEmployerProfile($userId);
        $isCreate = empty($existing);

        // Files written by THIS request — unlink them if the save rolls back so
        // storage/ never accumulates orphans.
        $uploadedPermit = null;
        $uploadedLogo   = null;
        $aiVerificationStatus = 'pending';
        $aiFeedback           = null;
        $extractedPermitData  = null;

        try {
            // Business permit: magic-byte validated, stored under a randomized
            // name in storage/documents/. Accepts business_permit_file or business_permit.
            $permitFilename = $existing['business_permit_file'] ?? null;
            $permitFile = !empty($_FILES['business_permit_file']['name']) 
                ? $_FILES['business_permit_file'] 
                : (!empty($_FILES['business_permit']['name']) ? $_FILES['business_permit'] : null);

            if ($permitFile && isset($permitFile['error']) && $permitFile['error'] !== UPLOAD_ERR_NO_FILE) {
                $permitFilename = FileUpload::secureUpload(
                    $permitFile,
                    $this->uploadDir('storage/documents/')
                );
                $uploadedPermit = $permitFilename;
            }

            if ($isCreate && !$permitFilename) {
                return $this->renderEmployerBuilderError(
                    $profileModel,
                    'A valid business permit (PDF, JPG or PNG, max 5 MB) is required to create your company profile.'
                );
            }

            // Perform non-blocking AI Engine audit review on the permit document
            if ($permitFilename) {
                try {
                    $fullPermitPath = $this->uploadDir('storage/documents/') . $permitFilename;
                    $aiService = new AIEngineService();
                    $aiResult  = $aiService->verifyBusinessPermit($fullPermitPath);

                    $aiVerificationStatus = $aiResult['verification_status'] ?? 'pending';
                    $aiFeedback           = $aiResult['ai_feedback'] ?? ($aiResult['feedback'] ?? 'AI Engine document analysis completed.');
                    $rawExtracted         = $aiResult['extracted_permit_data'] ?? ($aiResult['extracted_data'] ?? null);
                    $extractedPermitData  = is_array($rawExtracted) ? json_encode($rawExtracted) : $rawExtracted;
                } catch (Throwable $aiEx) {
                    error_log('[profile] AI permit review notice: ' . $aiEx->getMessage());
                    $aiVerificationStatus = 'pending';
                    $aiFeedback           = 'Business permit uploaded successfully. Document queued for manual PESO Admin audit.';
                }
            }

            // Company logo: optional, same secure pipeline.
            $logoFilename = $existing['company_logo'] ?? null;
            if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
                $logoFilename = FileUpload::secureUpload(
                    $_FILES['company_logo'],
                    $this->uploadDir('storage/uploads/logos/'),
                    ['image/jpeg', 'image/png', 'image/webp'],
                    2
                );
                $uploadedLogo = $logoFilename;
            }
        } catch (RuntimeException $e) {
            $this->cleanupUploads($uploadedPermit, $uploadedLogo);
            error_log('[profile] employer upload rejected for user ' . $userId . ': ' . $e->getMessage());
            return $this->renderEmployerBuilderError(
                $profileModel,
                'That file could not be accepted. Upload a PDF, JPG or PNG within the size limit.'
            );
        }

        // Friendly "Company Size" label -> schema-v2 enum member (Q-16).
        // Anything unrecognised maps to NULL — never a raw passthrough.
        $sizeMap = [
            '1-10 Employees'    => '1-10',
            '11-50 Employees'   => '11-50',
            '51-200 Employees'  => '51-200',
            '201-500 Employees' => '201-500',
            '500+ Employees'    => '500+',
        ];
        $companySize = $sizeMap[trim($_POST['company_size'] ?? '')] ?? null;
        $website     = trim($_POST['company_website'] ?? '');
        if ($website !== '' && !preg_match('~^https?://~i', $website)) {
            $website = 'https://' . $website;
        }

        $payload = [
            'user_id'               => $userId,
            'company_name'          => htmlspecialchars(trim($_POST['company_name'] ?? '')),
            'contact_person'        => htmlspecialchars(trim($_POST['contact_person'] ?? '')),
            'company_email'         => filter_var(trim($_POST['company_email'] ?? ''), FILTER_SANITIZE_EMAIL),
            'company_phone'         => htmlspecialchars(trim($_POST['company_phone'] ?? '')),
            'street_name'           => htmlspecialchars(trim($_POST['street_name'] ?? '')),
            'municipality_id'       => ($_POST['municipality_id'] ?? '') !== '' ? (int) $_POST['municipality_id'] : 0,
            'barangay_id'           => ($_POST['barangay_id'] ?? '') !== '' ? (int) $_POST['barangay_id'] : null,
            'industry'              => htmlspecialchars(trim($_POST['industry'] ?? '')),
            'company_size'          => $companySize,
            'company_description'   => htmlspecialchars(trim($_POST['company_description'] ?? '')),
            'company_logo'          => $logoFilename,
            'company_website'       => $website !== '' ? filter_var($website, FILTER_SANITIZE_URL) : '',
            'permit_file_path'      => $permitFilename ? '/storage/documents/' . $permitFilename : null,
            'verification_status'   => $aiVerificationStatus,
            'ai_feedback'           => $aiFeedback,
            'extracted_permit_data' => $extractedPermitData,
        ];

        if ($isCreate) {
            $payload['business_permit_file'] = $permitFilename;

            if ($payload['company_name'] === '' || $payload['contact_person'] === ''
                || $payload['company_email'] === '' || $payload['company_phone'] === ''
                || $payload['municipality_id'] <= 0) {
                $this->cleanupUploads($uploadedPermit, $uploadedLogo);
                return $this->renderEmployerBuilderError(
                    $profileModel,
                    'Company name, contact person, email, phone and municipality are all required.'
                );
            }

            $ok = $profileModel->createEmployerProfile($payload);
        } else {
            if ($payload['company_name'] === '' || $payload['contact_person'] === ''
                || $payload['company_email'] === '' || $payload['company_phone'] === '') {
                $this->cleanupUploads($uploadedPermit, $uploadedLogo);
                return $this->renderEmployerBuilderError(
                    $profileModel,
                    'Company name, contact person, email and phone cannot be blank.'
                );
            }
            $ok = $profileModel->saveEmployerProfile($payload);
        }

        if (!$ok) {
            $this->cleanupUploads($uploadedPermit, $uploadedLogo);
            http_response_code(500);
            $this->view('errors/500', ['code' => 500, 'message' => 'We could not save your company profile. Please try again.']);
            exit();
        }

        if ($isCreate) {
            $_SESSION['account_status'] = 'Active';
        }
        header('Location: /employer/dashboard?profile_updated=1');
        exit();
    }

    private function loadEmployerData($profileModel)
    {
        return [
            'existing_profile' => $profileModel->getEmployerProfile($_SESSION['user_id']) ?: [],
            'municipalities'   => $profileModel->getMunicipalities(),
        ];
    }

    /** Re-render the employer builder with a validation message. HTTP 200. */
    private function renderEmployerBuilderError($profileModel, $message)
    {
        $data = $this->loadEmployerData($profileModel);
        $data['error'] = $message;
        $this->view('profile/employer_builder', $data);
        exit();
    }

    private function cleanupUploads($permitName, $logoName)
    {
        if ($permitName) {
            $p = BASE_PATH . 'storage/documents/' . basename((string) $permitName);
            if (is_file($p)) {
                @unlink($p);
            }
        }
        if ($logoName) {
            $p = BASE_PATH . 'storage/uploads/logos/' . basename((string) $logoName);
            if (is_file($p)) {
                @unlink($p);
            }
        }
    }

    /** Absolute upload directory, created on first use. */
    private function uploadDir($subdir)
    {
        $dir = BASE_PATH . $subdir;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }
}
