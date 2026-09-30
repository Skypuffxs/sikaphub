-- Migration: Add ai_applicant_feedback table
-- Purpose: Cache AI tie-breaker feedback per candidate per job posting to avoid duplicate AI engine calls

CREATE TABLE IF NOT EXISTS ai_applicant_feedback (
    feedback_id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    jobseeker_id INT NOT NULL,
    strengths TEXT NOT NULL,
    growth_areas TEXT NOT NULL,
    interview_questions TEXT NOT NULL,
    match_tiebreaker_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_job_seeker (job_id, jobseeker_id),
    FOREIGN KEY (job_id) REFERENCES job_postings(job_id) ON DELETE CASCADE,
    FOREIGN KEY (jobseeker_id) REFERENCES job_seekers(jobseeker_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
