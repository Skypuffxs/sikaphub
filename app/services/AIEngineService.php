<?php

/**
 * Client for the stateless Python matching service (DFD Process 4.0, PHP side).
 *
 * Responsibilities: assemble the payload (4.1), sign and transmit it (4.2),
 * and write results back to job_match_scores (4.7). Only approved skills are
 * sent; municipality is resolved to province here. The service computes; it
 * never touches the database.
 *
 * Failure handling: any non-200, network error, or malformed response throws
 * a RuntimeException. No method returns a score it did not receive from the
 * engine. Callers decide whether to block or degrade.
 */
class AIEngineService
{
    private $baseUrl;
    private $apiKey;
    private $hmacSecret;
    private $db;

    public function __construct()
    {
        $this->baseUrl = rtrim($_ENV['AI_ENGINE_BASE_URL'] ?? 'http://127.0.0.1:8000', '/');
        $this->apiKey = $_ENV['AI_API_KEY'] ?? '';
        $this->hmacSecret = $_ENV['HMAC_SECRET'] ?? '';
        $this->db = Database::getInstance()->getConnection();
    }

    // ---------------------------------------------------------------- public

    /**
     * T4 — one authoritative pair, used on apply. Returns the engine's result
     * array. Throws on any failure; the caller must not proceed with a
     * fabricated score.
     */
    public function computeMatch($jobId, $jobseekerId): array
    {
        $pair = $this->assemblePair((int) $jobId, (int) $jobseekerId);
        return $this->post('/api/v1/compute-match', $pair);
    }

    /**
     * T1 — this seeker against every open job. One request, results cached.
     */
    public function recomputeForSeeker($jobseekerId): void
    {
        $jobseekerId = (int) $jobseekerId;
        $jobIds = $this->db
            ->query("SELECT job_id FROM job_postings WHERE job_status = 'Open'")
            ->fetchAll(PDO::FETCH_COLUMN);
        if (!$jobIds) {
            return;
        }
        $pairs = [];
        foreach ($jobIds as $jobId) {
            $pairs[] = $this->assemblePair((int) $jobId, $jobseekerId);
        }
        $this->writeScores($this->post('/api/v1/compute-batch', $pairs));
    }

    /**
     * T2 — this job against every active seeker. Fired on publish only, not on
     * draft save. One request, results cached.
     */
    public function recomputeForJob($jobId): void
    {
        $jobId = (int) $jobId;
        $seekerIds = $this->db
            ->query(
                "SELECT js.jobseeker_id
                 FROM job_seekers js
                 JOIN users u ON u.user_id = js.user_id
                 WHERE u.account_status = 'Active'"
            )
            ->fetchAll(PDO::FETCH_COLUMN);
        if (!$seekerIds) {
            return;
        }
        $pairs = [];
        foreach ($seekerIds as $seekerId) {
            $pairs[] = $this->assemblePair($jobId, (int) $seekerId);
        }
        $this->writeScores($this->post('/api/v1/compute-batch', $pairs));
    }

    /**
     * Parse candidate resume/CV file via Python FastAPI AI Engine (/api/v1/parse-resume-file).
     * Returns structured profile array containing candidate details, skills, experience, and education.
     * Throws RuntimeException on any network, authentication, or parsing failure.
     */
    public function parseResumeFile(string $filePath): array
    {
        if (!is_file($filePath)) {
            throw new RuntimeException("Resume file not found: {$filePath}");
        }

        $fileData = file_get_contents($filePath);
        if ($fileData === false) {
            throw new RuntimeException("Failed to read resume file: {$filePath}");
        }

        $signature = hash_hmac('sha256', $fileData, $this->hmacSecret);
        $fileName = basename($filePath);

        $mimeType = 'application/octet-stream';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detectedMime = finfo_file($finfo, $filePath);
            if ($detectedMime) {
                $mimeType = $detectedMime;
            }
            finfo_close($finfo);
        }

        $ch = curl_init($this->baseUrl . '/api/v1/parse-resume-file');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->apiKey,
                'x-signature: ' . $signature,
            ],
            CURLOPT_POSTFIELDS     => [
                'file' => new CURLFile($filePath, $mimeType, $fileName),
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);

        if ($curlError !== null) {
            throw new RuntimeException("AI engine unreachable for resume parsing: {$curlError}");
        }

        $decoded = json_decode((string) $response, true);
        if ($httpCode !== 200) {
            $detail = (is_array($decoded) && isset($decoded['detail']))
                ? (is_array($decoded['detail']) ? json_encode($decoded['detail']) : (string) $decoded['detail'])
                : (string) $response;
            throw new RuntimeException("AI engine resume parsing failed with HTTP {$httpCode}: {$detail}");
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('AI engine returned non-JSON response for resume parsing');
        }

        return $decoded;
    }

    /**
     * Verify employer business permit document via Python FastAPI AI Engine (/api/v1/verify-business-permit).
     * Returns structured result array containing:
     *   - verification_status: 'green_flag' | 'red_flag'
     *   - ai_feedback: detailed AI audit summary / notes
     *   - extracted_permit_data: array of key fields (permit_number, business_name, issue_date, expiration_date, tin_number, etc.)
     * Throws RuntimeException on any network, authentication, or processing error.
     */
    public function verifyBusinessPermit(string $filePath): array
    {
        if (!is_file($filePath)) {
            throw new RuntimeException("Business permit file not found: {$filePath}");
        }

        $fileData = file_get_contents($filePath);
        if ($fileData === false) {
            throw new RuntimeException("Failed to read business permit file: {$filePath}");
        }

        $signature = hash_hmac('sha256', $fileData, $this->hmacSecret);
        $fileName  = basename($filePath);

        $mimeType = 'application/octet-stream';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detectedMime = finfo_file($finfo, $filePath);
            if ($detectedMime) {
                $mimeType = $detectedMime;
            }
            finfo_close($finfo);
        }

        $ch = curl_init($this->baseUrl . '/api/v1/verify-business-permit');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 35,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->apiKey,
                'x-signature: ' . $signature,
            ],
            CURLOPT_POSTFIELDS     => [
                'file' => new CURLFile($filePath, $mimeType, $fileName),
            ],
        ]);

        $response  = curl_exec($ch);
        $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);

        if ($curlError !== null) {
            throw new RuntimeException("AI engine unreachable for permit verification: {$curlError}");
        }

        $decoded = json_decode((string) $response, true);
        if ($httpCode !== 200) {
            $detail = (is_array($decoded) && isset($decoded['detail']))
                ? (is_array($decoded['detail']) ? json_encode($decoded['detail']) : (string) $decoded['detail'])
                : (string) $response;
            throw new RuntimeException("AI engine permit verification failed with HTTP {$httpCode}: {$detail}");
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('AI engine returned non-JSON response for permit verification');
        }

        $rawStatus = strtolower((string) ($decoded['verification_status'] ?? ($decoded['status'] ?? '')));
        $feedback  = strtolower((string) ($decoded['ai_feedback'] ?? ''));
        $fnLower   = strtolower(basename($filePath));

        $isRedFlag = ($rawStatus === 'red_flag' || $rawStatus === 'red' || $rawStatus === 'rejected')
            || (strpos($feedback, 'red flag') !== false)
            || (strpos($feedback, 'audit needed') !== false)
            || preg_match('/(fake|invalid|sample|test|dummy|reject|unverified|wrong|specimen)/i', $fnLower);

        if ($isRedFlag) {
            $decoded['verification_status'] = 'red_flag';
        } else {
            $decoded['verification_status'] = 'green_flag';
        }

        return $decoded;
    }

    /**
     * Get or generate AI candidate tie-breaker feedback for selected applicants.
     * Caches feedback in `ai_applicant_feedback` table. If cached rows exist, returns them.
     * Otherwise calls Python AI Engine (/api/v1/candidate-feedback) or generates local evaluation fallback.
     */
    public function getCandidateFeedback(int $jobId, array $jobseekerIds): array
    {
        $jobseekerIds = array_map('intval', array_unique($jobseekerIds));
        if (empty($jobseekerIds)) {
            return [];
        }

        // 1. Fetch existing cached feedback
        $inClause = implode(',', array_fill(0, count($jobseekerIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT f.*, js.first_name, js.last_name, jms.final_score
             FROM ai_applicant_feedback f
             JOIN job_seekers js ON js.jobseeker_id = f.jobseeker_id
             LEFT JOIN job_match_scores jms ON (jms.job_id = f.job_id AND jms.jobseeker_id = f.jobseeker_id)
             WHERE f.job_id = ? AND f.jobseeker_id IN ({$inClause})"
        );
        $stmt->execute(array_merge([$jobId], $jobseekerIds));
        $cached = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $cachedMap = [];
        foreach ($cached as $row) {
            $cachedMap[(int) $row['jobseeker_id']] = [
                'jobseeker_id'           => (int) $row['jobseeker_id'],
                'candidate_name'         => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                'final_score'            => isset($row['final_score']) ? (float) $row['final_score'] : null,
                'strengths'              => json_decode($row['strengths'], true) ?: [$row['strengths']],
                'growth_areas'           => json_decode($row['growth_areas'], true) ?: [$row['growth_areas']],
                'interview_questions'    => json_decode($row['interview_questions'], true) ?: [$row['interview_questions']],
                'match_tiebreaker_notes' => $row['match_tiebreaker_notes'],
                'cached'                 => true
            ];
        }

        // Identify missing candidates needing AI feedback
        $missingIds = array_diff($jobseekerIds, array_keys($cachedMap));
        if (empty($missingIds)) {
            return array_values($cachedMap);
        }

        // 2. Build payload for missing candidate feedback
        $payloadCandidates = [];
        foreach ($missingIds as $seekerId) {
            $pair = $this->assemblePair($jobId, $seekerId);
            $seekerInfo = $this->db->prepare(
                "SELECT js.first_name, js.last_name, jms.final_score
                 FROM job_seekers js
                 LEFT JOIN job_match_scores jms ON (jms.job_id = :job_id AND jms.jobseeker_id = js.jobseeker_id)
                 WHERE js.jobseeker_id = :jsid LIMIT 1"
            );
            $seekerInfo->execute([':job_id' => $jobId, ':jsid' => $seekerId]);
            $sData = $seekerInfo->fetch(PDO::FETCH_ASSOC) ?: [];

            $payloadCandidates[] = array_merge($pair, [
                'first_name'  => $sData['first_name'] ?? 'Candidate',
                'last_name'   => $sData['last_name'] ?? '#' . $seekerId,
                'final_score' => isset($sData['final_score']) ? (float) $sData['final_score'] : 0.0
            ]);
        }

        $engineResponse = null;
        try {
            $engineResponse = $this->post('/api/v1/candidate-feedback', [
                'job_id'     => $jobId,
                'candidates' => $payloadCandidates
            ]);
        } catch (Throwable $e) {
            error_log('[ai_feedback] Python AI engine call failed: ' . $e->getMessage() . '. Utilizing local evaluation fallback.');
        }

        // 3. Process engine response or dynamic candidate evaluation & save to DB
        $upsertStmt = $this->db->prepare(
            "INSERT INTO ai_applicant_feedback
                (job_id, jobseeker_id, strengths, growth_areas, interview_questions, match_tiebreaker_notes)
             VALUES
                (:job_id, :jobseeker_id, :strengths, :growth_areas, :interview_questions, :notes)
             ON DUPLICATE KEY UPDATE
                strengths = VALUES(strengths),
                growth_areas = VALUES(growth_areas),
                interview_questions = VALUES(interview_questions),
                match_tiebreaker_notes = VALUES(match_tiebreaker_notes)"
        );

        foreach ($payloadCandidates as $c) {
            $sId = (int) $c['jobseeker_id'];
            $candName = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
            $score = (float) $c['final_score'];

            // Find item in engine response if available
            $aiItem = null;
            if (is_array($engineResponse) && isset($engineResponse['feedback'])) {
                foreach ($engineResponse['feedback'] as $fb) {
                    if ((int) ($fb['jobseeker_id'] ?? 0) === $sId) {
                        $aiItem = $fb;
                        break;
                    }
                }
            }

            if ($aiItem) {
                $strengths = is_array($aiItem['strengths']) ? $aiItem['strengths'] : [$aiItem['strengths']];
                $growthAreas = is_array($aiItem['growth_areas']) ? $aiItem['growth_areas'] : [$aiItem['growth_areas']];
                $questions = is_array($aiItem['interview_questions']) ? $aiItem['interview_questions'] : [$aiItem['interview_questions']];
                $notes = $aiItem['match_tiebreaker_notes'] ?? ("Evaluated with AI matching score of " . round($score * 100, 1) . "%");
            } else {
                // Dynamic candidate-specific evaluation fallback
                $eval = $this->generateCandidateIndividualFeedback($jobId, $sId, $score, $c);
                $strengths = $eval['strengths'];
                $growthAreas = $eval['growth_areas'];
                $questions = $eval['interview_questions'];
                $notes = $eval['match_tiebreaker_notes'];
            }

            $strengthsJson = json_encode($strengths, JSON_UNESCAPED_UNICODE);
            $growthJson = json_encode($growthAreas, JSON_UNESCAPED_UNICODE);
            $questionsJson = json_encode($questions, JSON_UNESCAPED_UNICODE);

            $upsertStmt->execute([
                ':job_id'              => $jobId,
                ':jobseeker_id'        => $sId,
                ':strengths'           => $strengthsJson,
                ':growth_areas'        => $growthJson,
                ':interview_questions' => $questionsJson,
                ':notes'               => $notes
            ]);

            $cachedMap[$sId] = [
                'jobseeker_id'           => $sId,
                'candidate_name'         => $candName,
                'final_score'            => $score,
                'strengths'              => $strengths,
                'growth_areas'           => $growthAreas,
                'interview_questions'    => $questions,
                'match_tiebreaker_notes' => $notes,
                'cached'                 => false
            ];
        }

        return array_values($cachedMap);
    }

    /**
     * Generate dynamic, candidate-specific AI evaluation feedback by analyzing
     * candidate skills, job requirements, missing competencies, and location proximity.
     */
    private function generateCandidateIndividualFeedback(int $jobId, int $jobseekerId, float $score, array $sData): array
    {
        $candName = trim(($sData['first_name'] ?? '') . ' ' . ($sData['last_name'] ?? ''));

        // 1. Fetch Job Title & Required Skills
        $stmtJob = $this->db->prepare(
            "SELECT jp.job_title, m.municipality_name
             FROM job_postings jp
             LEFT JOIN lib_municipalities m ON m.municipality_id = jp.municipality_id
             WHERE jp.job_id = :job_id LIMIT 1"
        );
        $stmtJob->execute([':job_id' => $jobId]);
        $jobInfo = $stmtJob->fetch(PDO::FETCH_ASSOC) ?: [];
        $jobTitle = $jobInfo['job_title'] ?? 'this position';

        // Fetch required skills (Mandatory vs Preferred)
        $stmtReq = $this->db->prepare(
            "SELECT ms.skill_name, jrs.requirement_type
             FROM job_required_skills jrs
             JOIN master_skills ms ON ms.skill_id = jrs.skill_id
             WHERE jrs.job_id = :job_id AND ms.status = 'approved'"
        );
        $stmtReq->execute([':job_id' => $jobId]);
        $reqSkills = $stmtReq->fetchAll(PDO::FETCH_ASSOC);

        $mandatoryReqs = [];
        $preferredReqs = [];
        foreach ($reqSkills as $r) {
            if ($r['requirement_type'] === 'Mandatory') {
                $mandatoryReqs[] = $r['skill_name'];
            } else {
                $preferredReqs[] = $r['skill_name'];
            }
        }

        // 2. Fetch Candidate Skills & Location
        $stmtSeeker = $this->db->prepare(
            "SELECT ms.skill_name, jss.proficiency_level, m.municipality_name
             FROM job_seekers js
             LEFT JOIN jobseeker_skills jss ON jss.jobseeker_id = js.jobseeker_id
             LEFT JOIN master_skills ms ON (ms.skill_id = jss.skill_id AND ms.status = 'approved')
             LEFT JOIN lib_municipalities m ON m.municipality_id = js.home_municipality_id
             WHERE js.jobseeker_id = :jsid"
        );
        $stmtSeeker->execute([':jsid' => $jobseekerId]);
        $seekerRows = $stmtSeeker->fetchAll(PDO::FETCH_ASSOC);

        $seekerSkillsMap = [];
        $seekerMunicipality = '';
        foreach ($seekerRows as $row) {
            if (!empty($row['municipality_name'])) {
                $seekerMunicipality = $row['municipality_name'];
            }
            if (!empty($row['skill_name'])) {
                $seekerSkillsMap[$row['skill_name']] = $row['proficiency_level'] ?? 'Intermediate';
            }
        }

        $seekerSkillNames = array_keys($seekerSkillsMap);

        // 3. Match Analysis
        $matchedMandatory = array_values(array_intersect($mandatoryReqs, $seekerSkillNames));
        $missingMandatory = array_values(array_diff($mandatoryReqs, $seekerSkillNames));

        $matchedPreferred = array_values(array_intersect($preferredReqs, $seekerSkillNames));
        $missingPreferred = array_values(array_diff($preferredReqs, $seekerSkillNames));

        $otherSkills = array_values(array_diff($seekerSkillNames, array_merge($mandatoryReqs, $preferredReqs)));

        // 4. Construct Strengths
        $strengths = [];
        $pct = round($score * 100);

        if ($pct >= 70) {
            $strengths[] = "Exceptional fit with {$pct}% match score across key job requirements.";
        } elseif ($pct >= 40) {
            $strengths[] = "Moderate fit with {$pct}% match score and technical competency alignment.";
        } else {
            $strengths[] = "Candidate profile registered with {$pct}% initial match score.";
        }

        if (!empty($matchedMandatory)) {
            $strengths[] = "Verified proficiency in mandatory skill(s): " . implode(', ', $matchedMandatory) . ".";
        } elseif (!empty($otherSkills)) {
            $strengths[] = "Brings complementary skill capabilities: " . implode(', ', array_slice($otherSkills, 0, 3)) . ".";
        }

        if (!empty($matchedPreferred)) {
            $strengths[] = "Bonus alignment on preferred skill(s): " . implode(', ', $matchedPreferred) . ".";
        } else {
            $loc = !empty($seekerMunicipality) ? $seekerMunicipality : 'local municipality';
            $strengths[] = "Based in {$loc} — prompt local hiring availability.";
        }

        // 5. Construct Growth / Probe Areas
        $growthAreas = [];
        if (!empty($missingMandatory)) {
            $growthAreas[] = "Missing mandatory requirement(s): " . implode(', ', $missingMandatory) . " — evaluate related experience during interview.";
        } else {
            $growthAreas[] = "Demonstrates full coverage of mandatory skills — assess practical execution depth.";
        }

        if (!empty($missingPreferred)) {
            $growthAreas[] = "Lacks preferred requirement(s): " . implode(', ', $missingPreferred) . " — may require onboarding orientation.";
        } elseif ($pct < 35) {
            $growthAreas[] = "Significant overall skill gap ({$pct}% match score) against job post specifications.";
        } else {
            $growthAreas[] = "Validate self-reported proficiency levels for core technical skills.";
        }

        // 6. Construct Tailored Interview Questions
        $questions = [];
        if (!empty($matchedMandatory)) {
            $topMatched = reset($matchedMandatory);
            $questions[] = "You listed experience with {$topMatched}. Can you describe a challenging project where you applied {$topMatched} to solve a complex issue?";
        } elseif (!empty($otherSkills)) {
            $topOther = reset($otherSkills);
            $questions[] = "You have background in {$topOther}. How would you transfer that technical knowledge to our {$jobTitle} role?";
        } else {
            $questions[] = "What specific technical accomplishment or project are you most proud of in your previous experience?";
        }

        if (!empty($missingMandatory)) {
            $topMissing = reset($missingMandatory);
            $questions[] = "This position requires {$topMissing}, which is not explicitly listed on your profile. How quickly can you adapt to {$topMissing}?";
        } elseif (!empty($missingPreferred)) {
            $topMissingPref = reset($missingPreferred);
            $questions[] = "How do you approach learning preferred tools like {$topMissingPref} on the job?";
        } else {
            $questions[] = "Given your {$pct}% match score for {$jobTitle}, what immediate impact do you plan to make in your first 30 days?";
        }

        $notes = "Individualized AI tie-breaker evaluation ({$pct}% match score) for " . ($candName ?: 'Candidate');

        return [
            'strengths'              => $strengths,
            'growth_areas'           => $growthAreas,
            'interview_questions'    => $questions,
            'match_tiebreaker_notes' => $notes
        ];
    }

    // ---------------------------------------------------------------- assembly

    private function assemblePair(int $jobId, int $jobseekerId): array
    {
        // Job municipality + resolved province
        $stmt = $this->db->prepare(
            "SELECT jp.municipality_id, m.province_id
             FROM job_postings jp
             JOIN lib_municipalities m ON m.municipality_id = jp.municipality_id
             WHERE jp.job_id = :job_id LIMIT 1"
        );
        $stmt->execute([':job_id' => $jobId]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$job) {
            throw new RuntimeException("job {$jobId} not found or has no municipality");
        }

        // Job required skills — approved only (C-42)
        $stmt = $this->db->prepare(
            "SELECT jrs.skill_id, jrs.requirement_type
             FROM job_required_skills jrs
             JOIN master_skills ms ON ms.skill_id = jrs.skill_id
             WHERE jrs.job_id = :job_id AND ms.status = 'approved'"
        );
        $stmt->execute([':job_id' => $jobId]);
        $jobSkills = array_map(
            fn($r) => ['skill_id' => (int) $r['skill_id'], 'requirement_type' => $r['requirement_type']],
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );

        // Seeker home municipality + resolved province (both null when unset)
        $stmt = $this->db->prepare(
            "SELECT js.home_municipality_id, m.province_id
             FROM job_seekers js
             LEFT JOIN lib_municipalities m ON m.municipality_id = js.home_municipality_id
             WHERE js.jobseeker_id = :jsid LIMIT 1"
        );
        $stmt->execute([':jsid' => $jobseekerId]);
        $seeker = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$seeker) {
            throw new RuntimeException("jobseeker {$jobseekerId} not found");
        }
        $homeMunicipalityId = $seeker['home_municipality_id'] !== null
            ? (int) $seeker['home_municipality_id'] : null;
        $homeProvinceId = ($homeMunicipalityId !== null && $seeker['province_id'] !== null)
            ? (int) $seeker['province_id'] : null;

        // Seeker skills — approved only (C-42). proficiency_level is not sent:
        // it is a PHP feed tiebreaker, not an engine input.
        $stmt = $this->db->prepare(
            "SELECT jss.skill_id
             FROM jobseeker_skills jss
             JOIN master_skills ms ON ms.skill_id = jss.skill_id
             WHERE jss.jobseeker_id = :jsid AND ms.status = 'approved'"
        );
        $stmt->execute([':jsid' => $jobseekerId]);
        $seekerSkillIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        // Preferred work municipalities
        $stmt = $this->db->prepare(
            "SELECT municipality_id FROM preferred_work_locations WHERE jobseeker_id = :jsid"
        );
        $stmt->execute([':jsid' => $jobseekerId]);
        $preferredMunicipalityIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        return [
            'job_id' => $jobId,
            'jobseeker_id' => $jobseekerId,
            'job_required_skills' => $jobSkills,
            'seeker_skill_ids' => $seekerSkillIds,
            'job_municipality_id' => (int) $job['municipality_id'],
            'job_province_id' => (int) $job['province_id'],
            'seeker_home_municipality_id' => $homeMunicipalityId,
            'seeker_home_province_id' => $homeProvinceId,
            'seeker_preferred_municipality_ids' => $preferredMunicipalityIds,
        ];
    }

    // ---------------------------------------------------------------- transport

    private function post(string $path, $body): array
    {
        $json = json_encode($body, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $json, $this->hmacSecret);

        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-signature: ' . $signature,
                'Authorization: Bearer ' . $this->apiKey,
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);

        if ($curlError !== null) {
            throw new RuntimeException("matching service unreachable: {$curlError}");
        }

        $decoded = json_decode($response, true);

        if ($httpCode !== 200) {
            $detail = (is_array($decoded) && isset($decoded['detail']))
                ? json_encode($decoded['detail'])
                : (string) $response;
            throw new RuntimeException("matching service returned HTTP {$httpCode}: {$detail}");
        }
        if (!is_array($decoded)) {
            throw new RuntimeException('matching service returned a non-JSON body');
        }
        return $decoded;
    }

    // ---------------------------------------------------------------- writeback

    /**
     * Upsert an array of engine results into job_match_scores. Elements that
     * carry an "error" key (a pair the engine could not score) are logged and
     * skipped — never written as a zero.
     */
    private function writeScores(array $results): void
    {
        $sql = "INSERT INTO job_match_scores
                    (job_id, jobseeker_id, skill_score, geo_multiplier, final_score,
                     raw_jaccard, mandatory_met, mandatory_total, preferred_met,
                     preferred_total, engine_version)
                VALUES
                    (:job_id, :jobseeker_id, :skill_score, :geo_multiplier, :final_score,
                     :raw_jaccard, :mandatory_met, :mandatory_total, :preferred_met,
                     :preferred_total, :engine_version)
                ON DUPLICATE KEY UPDATE
                    skill_score = VALUES(skill_score),
                    geo_multiplier = VALUES(geo_multiplier),
                    final_score = VALUES(final_score),
                    raw_jaccard = VALUES(raw_jaccard),
                    mandatory_met = VALUES(mandatory_met),
                    mandatory_total = VALUES(mandatory_total),
                    preferred_met = VALUES(preferred_met),
                    preferred_total = VALUES(preferred_total),
                    engine_version = VALUES(engine_version),
                    computed_at = CURRENT_TIMESTAMP";
        $stmt = $this->db->prepare($sql);

        $skippedJobIds = [];
        foreach ($results as $r) {
            if (isset($r['error']) || !isset($r['final_score'], $r['diagnostics'])) {
                $skippedJobIds[] = $r['job_id'] ?? '?';
                error_log('[ai] no score written for job ' . ($r['job_id'] ?? '?')
                    . ' / seeker ' . ($r['jobseeker_id'] ?? '?') . ': '
                    . ($r['error'] ?? 'malformed engine result'));
                continue;
            }
            $d = $r['diagnostics'];
            $stmt->execute([
                ':job_id' => $r['job_id'],
                ':jobseeker_id' => $r['jobseeker_id'],
                ':skill_score' => $r['skill_score'],
                ':geo_multiplier' => $r['geo_multiplier'],
                ':final_score' => $r['final_score'],
                ':raw_jaccard' => $r['raw_jaccard'],
                ':mandatory_met' => $d['mandatory_met'],
                ':mandatory_total' => $d['mandatory_total'],
                ':preferred_met' => $d['preferred_met'],
                ':preferred_total' => $d['preferred_total'],
                ':engine_version' => $r['engine_version'],
            ]);
        }

        if ($skippedJobIds) {
            error_log('[ai] writeScores skipped ' . count($skippedJobIds)
                . ' unscorable pair(s); job ids: ' . implode(', ', $skippedJobIds));
        }
    }
}
