-- =====================================================================
-- Migration: Add Business Permit AI Verification Columns to employers
-- Repository: SIKAPHUB v2
-- Description: Stores permit file path, AI flag status (green_flag/red_flag),
--              AI feedback summary, OCR extracted JSON data, and admin decision.
-- =====================================================================

ALTER TABLE employers
  ADD COLUMN permit_file_path VARCHAR(255) NULL AFTER business_permit_file,
  ADD COLUMN verification_status ENUM('pending', 'green_flag', 'red_flag') NOT NULL DEFAULT 'pending' AFTER verified_status,
  ADD COLUMN ai_feedback TEXT NULL AFTER verification_status,
  ADD COLUMN extracted_permit_data LONGTEXT NULL AFTER ai_feedback,
  ADD COLUMN admin_decision ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending' AFTER extracted_permit_data;

-- Index to optimize Admin Verification Queue sorting and filtering
CREATE INDEX idx_employers_verification_status ON employers (verification_status);
CREATE INDEX idx_employers_admin_decision ON employers (admin_decision);
