-- =====================================================================
--  S.I.K.A.P. HUB  --  SCHEMA V2 MIGRATION
--  Target: sikaphub_v2_db   (MySQL 8.x / MariaDB 10.4+)
--  Version: 2.0.0
--  Date: 21 August 2026
--  Result: 26 relations, 3NF, passwordless authentication,
--          4-level PSGC geographic hierarchy, match score cache
-- =====================================================================
--
--  READ THIS BEFORE RUNNING
--
--  This is a REBUILD, not an in-place migration.
--
--  Decision D-15 approved truncating legacy accounts, and every user
--  row depends on a password that no longer exists in the design.
--  Attempting forty sequential ALTER TABLE statements against a schema
--  this changed is slower, riskier, and harder to roll back than
--  rebuilding cleanly. With 15 test seekers and 2 job postings there is
--  no production data to preserve.
--
--  WHAT IS PRESERVED
--    - Guimba barangay names, copied out of the legacy `barangays`
--      table in Section 2 before it is dropped
--    - skill_categories and master_skills, reseeded in Section 7
--
--  WHAT IS DESTROYED
--    - All user accounts, seeker profiles, employer profiles,
--      job postings, applications, and match scores
--
--  RUN ORDER
--    0. Take the backup in Section 0. Do not skip this.
--    1. Run this script on Hostinger STAGING first.
--    2. Run the verification queries in Section 9.
--    3. Only then consider production.
--
--  ROLLBACK
--    Restore the Section 0 backup. A structural rollback script is
--    provided in Section 10 for the case where the backup is
--    unavailable, but restoring the dump is always preferable.
-- =====================================================================


-- =====================================================================
--  SECTION 0  --  PRE-FLIGHT
-- =====================================================================
-- Run these from the shell, NOT inside the SQL client:
--
--   mysqldump -u USER -p --routines --triggers --single-transaction \
--     sikaphub_v2_db > backup_pre_v2_$(date +%Y%m%d_%H%M).sql
--
--   mysql -u USER -p -e "SELECT VERSION();"
--
-- Confirm the backup file is non-empty before continuing.

-- Verify you are connected to the intended database.
SELECT DATABASE() AS connected_database, VERSION() AS server_version;

-- Record what exists now, for the Section 9 comparison.
SELECT COUNT(*) AS legacy_table_count
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE();

START TRANSACTION;
-- Note: MySQL performs implicit commits on DDL. The transaction above
-- protects the DML sections only. This is why the backup matters.
COMMIT;


-- =====================================================================
--  SECTION 1  --  SESSION SETTINGS
-- =====================================================================
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET SESSION sql_mode =
  'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
SET SESSION foreign_key_checks = 1;

ALTER DATABASE sikaphub_v2_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;


-- =====================================================================
--  SECTION 2  --  PRESERVE LEGACY BARANGAY DATA
-- =====================================================================
-- The legacy `barangays` table holds 64 hand-entered Guimba barangays.
-- That is real work. Copy it to a staging table before anything drops.

DROP TABLE IF EXISTS _tmp_legacy_barangays;

CREATE TABLE _tmp_legacy_barangays (
  legacy_id     INT NOT NULL,
  barangay_name VARCHAR(100) NOT NULL,
  PRIMARY KEY (legacy_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Guarded copy: succeeds whether or not the legacy table exists.
SET @legacy_exists = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'barangays'
);

SET @sql = IF(@legacy_exists > 0,
  'INSERT INTO _tmp_legacy_barangays (legacy_id, barangay_name)
     SELECT barangay_id, barangay_name FROM barangays
     WHERE barangay_id < 9000',           -- exclude the 9999 sentinel row
  'SELECT "legacy barangays table not present - skipping copy" AS note');

PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) AS barangays_preserved FROM _tmp_legacy_barangays;


-- =====================================================================
--  SECTION 3  --  DROP LEGACY SCHEMA  (reverse dependency order)
-- =====================================================================
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS job_match_scores;
DROP TABLE IF EXISTS applications;
DROP TABLE IF EXISTS job_required_skills;
DROP TABLE IF EXISTS job_postings;
DROP TABLE IF EXISTS jobseeker_skills;
DROP TABLE IF EXISTS master_skills;
DROP TABLE IF EXISTS skill_categories;
DROP TABLE IF EXISTS preferred_work_locations;
DROP TABLE IF EXISTS job_preferences;
DROP TABLE IF EXISTS work_experience;
DROP TABLE IF EXISTS education;
DROP TABLE IF EXISTS resume_uploads;
DROP TABLE IF EXISTS job_seekers;
DROP TABLE IF EXISTS employers;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS email_log;
DROP TABLE IF EXISTS peso_admins;
DROP TABLE IF EXISTS user_devices;
DROP TABLE IF EXISTS email_otp_codes;
DROP TABLE IF EXISTS user_auth_identities;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS lib_barangays;
DROP TABLE IF EXISTS lib_municipalities;
DROP TABLE IF EXISTS lib_provinces;
DROP TABLE IF EXISTS lib_regions;
DROP TABLE IF EXISTS barangays;              -- C-32: legacy orphan removed

SET FOREIGN_KEY_CHECKS = 1;


-- =====================================================================
--  SECTION 4  --  GEOGRAPHIC HIERARCHY  (relations 5-8)
-- =====================================================================
-- Modelled on the Philippine Standard Geographic Code (PSGC).
-- Created first: everything else references it.

CREATE TABLE lib_regions (
  region_id   INT AUTO_INCREMENT PRIMARY KEY,
  region_code VARCHAR(12)  NOT NULL,
  region_name VARCHAR(100) NOT NULL,
  UNIQUE KEY uq_region_code (region_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lib_provinces (
  province_id   INT AUTO_INCREMENT PRIMARY KEY,
  region_id     INT NOT NULL,
  province_name VARCHAR(100) NOT NULL,
  UNIQUE KEY uq_province (region_id, province_name),
  KEY idx_province_region (region_id),
  CONSTRAINT fk_province_region FOREIGN KEY (region_id)
    REFERENCES lib_regions(region_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lib_municipalities (
  municipality_id   INT AUTO_INCREMENT PRIMARY KEY,
  province_id       INT NOT NULL,
  municipality_name VARCHAR(100) NOT NULL,
  municipality_type ENUM('City','Municipality') NOT NULL DEFAULT 'Municipality',
  UNIQUE KEY uq_municipality (province_id, municipality_name),
  KEY idx_municipality_province (province_id),
  CONSTRAINT fk_municipality_province FOREIGN KEY (province_id)
    REFERENCES lib_provinces(province_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lib_barangays (
  barangay_id     INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NOT NULL,
  barangay_name   VARCHAR(120) NOT NULL,
  UNIQUE KEY uq_barangay (municipality_id, barangay_name),
  KEY idx_barangay_municipality (municipality_id),
  CONSTRAINT fk_barangay_municipality FOREIGN KEY (municipality_id)
    REFERENCES lib_municipalities(municipality_id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
--  SECTION 5  --  AUTHENTICATION  (relations 1-4)
-- =====================================================================

CREATE TABLE users (
  user_id           INT AUTO_INCREMENT PRIMARY KEY,
  email             VARCHAR(150) NOT NULL,
  email_verified_at DATETIME NULL,
  role              ENUM('jobseeker','employer','admin') NULL,
  account_status    ENUM('Pending','Active','Suspended','Deactivated')
                      NOT NULL DEFAULT 'Pending',
  last_login_at     DATETIME NULL,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                      ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role_status (role, account_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- `role` is NULL until the post-auth role picker. AuthGuard treats a
-- NULL role as "authenticated, role not yet chosen".
-- No password_hash column exists anywhere in this schema by design.

CREATE TABLE user_auth_identities (
  identity_id  INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT NOT NULL,
  provider     ENUM('google','email') NOT NULL,
  provider_uid VARCHAR(255) NOT NULL,
  last_used_at DATETIME NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_provider_uid  (provider, provider_uid),
  UNIQUE KEY uq_user_provider (user_id, provider),
  CONSTRAINT fk_identity_user FOREIGN KEY (user_id)
    REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- provider_uid holds Google's `sub` claim, NOT the Google email.
-- The sub claim is stable; a Google account's email can change.

CREATE TABLE email_otp_codes (
  otp_id        INT AUTO_INCREMENT PRIMARY KEY,
  email         VARCHAR(150) NOT NULL,
  code_hash     VARCHAR(255) NOT NULL,
  purpose       ENUM('signup','login') NOT NULL,
  attempt_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
  expires_at    DATETIME NOT NULL,
  consumed_at   DATETIME NULL,
  ip_address    VARCHAR(45) NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_otp_lookup (email, expires_at),
  KEY idx_otp_cleanup (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Keyed on email, not user_id: at signup the account does not exist yet.
-- code_hash stores password_hash() output. Never store the code itself.
-- Application must: expire after 10 minutes, invalidate at 5 attempts,
-- rate limit per email and per IP, and return an identical response
-- whether or not the address is registered (prevents user enumeration).

CREATE TABLE user_devices (
  device_id       INT AUTO_INCREMENT PRIMARY KEY,
  user_id         INT NOT NULL,
  token_hash      CHAR(64) NOT NULL,
  device_label    VARCHAR(100) NULL,
  user_agent_hash CHAR(64) NOT NULL,
  last_used_at    DATETIME NULL,
  expires_at      DATETIME NOT NULL,
  revoked_at      DATETIME NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_device_token (token_hash),
  KEY idx_device_user (user_id, expires_at),
  CONSTRAINT fk_device_user FOREIGN KEY (user_id)
    REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- One row per remembered device. token_hash = SHA-256 of a 32-byte
-- random_bytes() value, compared with hash_equals().
-- Rotate the token on every use. If an already-consumed token is
-- presented, treat it as theft: revoke every row for that user.
-- Admin accounts must never receive a row here.


-- =====================================================================
--  SECTION 6  --  PROFILES  (relations 9-14, 18)
-- =====================================================================

CREATE TABLE job_seekers (
  jobseeker_id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id              INT NOT NULL,
  first_name           VARCHAR(100) NOT NULL,
  middle_name          VARCHAR(100) NULL,
  last_name            VARCHAR(100) NOT NULL,
  gender               ENUM('Male','Female','Prefer not to say') NULL,
  birthdate            DATE NULL,
  contact_number       VARCHAR(20) NULL,
  house_number         VARCHAR(50) NULL,
  street_name          VARCHAR(150) NULL,
  barangay_id          INT NULL,
  home_municipality_id INT NULL,
  profile_photo        VARCHAR(255) NULL,
  profile_visibility   ENUM('Public','Private') NOT NULL DEFAULT 'Public',
  profile_completeness TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                         ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_seeker_user (user_id),
  KEY idx_seeker_municipality (home_municipality_id),
  CONSTRAINT fk_seeker_user FOREIGN KEY (user_id)
    REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_seeker_barangay FOREIGN KEY (barangay_id)
    REFERENCES lib_barangays(barangay_id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_seeker_municipality FOREIGN KEY (home_municipality_id)
    REFERENCES lib_municipalities(municipality_id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- home_municipality_id is a documented denormalization: derivable via
-- barangay_id, retained so the matching engine avoids a join on every
-- score computation. The application MUST keep the two consistent.
-- profile_completeness (0-100) is a materialized derived value used as
-- the third-level ranking tiebreaker.

CREATE TABLE education (
  education_id   INT AUTO_INCREMENT PRIMARY KEY,
  jobseeker_id   INT NOT NULL,
  degree_level   VARCHAR(100) NOT NULL,
  school_name    VARCHAR(150) NOT NULL,
  year_graduated YEAR NULL,
  KEY idx_education_seeker (jobseeker_id),
  CONSTRAINT fk_education_seeker FOREIGN KEY (jobseeker_id)
    REFERENCES job_seekers(jobseeker_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE work_experience (
  experience_id   INT AUTO_INCREMENT PRIMARY KEY,
  jobseeker_id    INT NOT NULL,
  job_title       VARCHAR(150) NOT NULL,
  company_name    VARCHAR(150) NOT NULL,
  start_date      DATE NULL,
  end_date        DATE NULL,
  job_description TEXT NULL,
  KEY idx_experience_seeker (jobseeker_id),
  CONSTRAINT fk_experience_seeker FOREIGN KEY (jobseeker_id)
    REFERENCES job_seekers(jobseeker_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- end_date NULL means "currently employed".

CREATE TABLE job_preferences (
  jobseeker_id         INT NOT NULL PRIMARY KEY,
  desired_job_type     ENUM('Full-time','Part-time','Contract','Internship') NULL,
  preferred_work_setup ENUM('On-site','Remote','Hybrid') NULL,
  expected_salary      DECIMAL(10,2) NULL,
  updated_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                         ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_preferences_seeker FOREIGN KEY (jobseeker_id)
    REFERENCES job_seekers(jobseeker_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE preferred_work_locations (
  jobseeker_id    INT NOT NULL,
  municipality_id INT NOT NULL,
  PRIMARY KEY (jobseeker_id, municipality_id),
  KEY idx_pwl_municipality (municipality_id),
  CONSTRAINT fk_pwl_seeker FOREIGN KEY (jobseeker_id)
    REFERENCES job_seekers(jobseeker_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pwl_municipality FOREIGN KEY (municipality_id)
    REFERENCES lib_municipalities(municipality_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- C-46 resolved: both foreign keys now declared.

CREATE TABLE resume_uploads (
  upload_id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id           INT NOT NULL,
  jobseeker_id      INT NULL,
  stored_filename   VARCHAR(255) NOT NULL,
  original_filename VARCHAR(255) NOT NULL,
  file_hash         CHAR(64) NOT NULL,
  mime_type         VARCHAR(100) NOT NULL,
  file_size_bytes   INT UNSIGNED NOT NULL,
  parse_status      ENUM('pending','parsed','failed') NOT NULL DEFAULT 'pending',
  parsed_payload    JSON NULL,
  parse_error       VARCHAR(255) NULL,
  parser_version    VARCHAR(20) NULL,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_upload_user (user_id),
  KEY idx_upload_seeker (jobseeker_id),
  CONSTRAINT fk_upload_user FOREIGN KEY (user_id)
    REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_upload_seeker FOREIGN KEY (jobseeker_id)
    REFERENCES job_seekers(jobseeker_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- jobseeker_id is nullable: the upload happens before the profile saves.
-- parsed_payload is a transient staging buffer, not a modelled entity.
-- parser_version enables the Chapter IV parser accuracy table.

CREATE TABLE employers (
  employer_id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id             INT NOT NULL,
  company_name        VARCHAR(150) NOT NULL,
  contact_person      VARCHAR(100) NOT NULL,
  company_email       VARCHAR(150) NOT NULL,
  company_phone       VARCHAR(20) NOT NULL,
  house_number        VARCHAR(50) NULL,
  street_name         VARCHAR(150) NULL,
  barangay_id         INT NULL,
  municipality_id     INT NOT NULL,
  industry            VARCHAR(100) NULL,
  company_size        ENUM('1-10','11-50','51-200','201-500','500+') NULL,
  company_description TEXT NULL,
  company_logo        VARCHAR(255) NULL,
  company_website     VARCHAR(255) NULL,
  business_permit_file VARCHAR(255) NOT NULL,
  permit_file_path     VARCHAR(255) NULL,
  verified_status      ENUM('Pending','Verified','Rejected') NOT NULL DEFAULT 'Pending',
  verification_status  ENUM('pending', 'green_flag', 'red_flag') NOT NULL DEFAULT 'pending',
  ai_feedback          TEXT NULL,
  extracted_permit_data LONGTEXT NULL,
  admin_decision       ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  verified_at          DATETIME NULL,
  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_employer_user (user_id),
  KEY idx_employer_verified (verified_status),
  KEY idx_employer_municipality (municipality_id),
  CONSTRAINT fk_employer_user FOREIGN KEY (user_id)
    REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_employer_barangay FOREIGN KEY (barangay_id)
    REFERENCES lib_barangays(barangay_id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_employer_municipality FOREIGN KEY (municipality_id)
    REFERENCES lib_municipalities(municipality_id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- C-30 resolved: municipality_id is now a real FK. The legacy rows
-- holding municipality_id = 0 cannot recur.
-- idx_employer_verified supports the feed's verified-first sort.


-- =====================================================================
--  SECTION 7  --  SKILLS TAXONOMY  (relations 15-17)
-- =====================================================================

CREATE TABLE skill_categories (
  category_id   INT AUTO_INCREMENT PRIMARY KEY,
  category_name VARCHAR(100) NOT NULL,
  UNIQUE KEY uq_category_name (category_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE master_skills (
  skill_id             INT AUTO_INCREMENT PRIMARY KEY,
  category_id          INT NOT NULL,
  skill_name           VARCHAR(100) NOT NULL,
  status               ENUM('pending','approved','rejected')
                         NOT NULL DEFAULT 'pending',
  submitted_by_user_id INT NULL,
  created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_skill_name (skill_name),
  KEY idx_skill_category (category_id),
  KEY idx_skill_status (status),
  CONSTRAINT fk_skill_category FOREIGN KEY (category_id)
    REFERENCES skill_categories(category_id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_skill_submitter FOREIGN KEY (submitted_by_user_id)
    REFERENCES users(user_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- C-42: only status='approved' skills participate in matching.

CREATE TABLE jobseeker_skills (
  jobseeker_id      INT NOT NULL,
  skill_id          INT NOT NULL,
  proficiency_level ENUM('Beginner','Intermediate','Expert') NOT NULL
                      DEFAULT 'Beginner',
  PRIMARY KEY (jobseeker_id, skill_id),
  KEY idx_js_skill (skill_id),
  CONSTRAINT fk_js_seeker FOREIGN KEY (jobseeker_id)
    REFERENCES job_seekers(jobseeker_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_js_skill FOREIGN KEY (skill_id)
    REFERENCES master_skills(skill_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- D-13: proficiency_level is the second-level ranking tiebreaker.
-- Ordinal mapping is fixed in the application, not left to MySQL's
-- implicit enum ordering: Beginner=1, Intermediate=2, Expert=3.


-- =====================================================================
--  SECTION 8  --  JOBS, APPLICATIONS, MATCHING  (relations 19-22)
-- =====================================================================

CREATE TABLE job_postings (
  job_id                INT AUTO_INCREMENT PRIMARY KEY,
  employer_id           INT NOT NULL,
  job_title             VARCHAR(150) NOT NULL,
  job_description       TEXT NOT NULL,
  salary_range          VARCHAR(100) NULL,
  min_years_experience  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  employment_type       ENUM('Full-time','Part-time','Contract','Internship')
                          NOT NULL DEFAULT 'Full-time',
  work_arrangement      ENUM('On-site','Remote','Hybrid')
                          NOT NULL DEFAULT 'On-site',
  municipality_id       INT NOT NULL,
  date_posted           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                          ON UPDATE CURRENT_TIMESTAMP,
  job_status            ENUM('Draft','Open','Closed','Suspended')
                          NOT NULL DEFAULT 'Draft',
  KEY idx_job_employer (employer_id),
  KEY idx_job_status (job_status, date_posted DESC),
  KEY idx_job_municipality (municipality_id),
  CONSTRAINT fk_job_employer FOREIGN KEY (employer_id)
    REFERENCES employers(employer_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_job_municipality FOREIGN KEY (municipality_id)
    REFERENCES lib_municipalities(municipality_id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- C-38 resolved: min_years_experience is TINYINT, not free-text varchar.
-- C-39 resolved: work_arrangement now exists, matchable against
--                job_preferences.preferred_work_setup.
-- No company_name or verified_status column: those live on employers,
-- reached by join. Verifying an employer promotes all their postings
-- instantly, with no update cascade (§6.5 of the normalization document).

CREATE TABLE job_required_skills (
  job_id           INT NOT NULL,
  skill_id         INT NOT NULL,
  requirement_type ENUM('Mandatory','Preferred') NOT NULL,
  PRIMARY KEY (job_id, skill_id),
  KEY idx_jrs_skill (skill_id),
  CONSTRAINT fk_jrs_job FOREIGN KEY (job_id)
    REFERENCES job_postings(job_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_jrs_skill FOREIGN KEY (skill_id)
    REFERENCES master_skills(skill_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- C-20 / C-31 resolved. The enum is ('Mandatory','Preferred').
-- The value 'Optional' does not exist in this system. The Python engine
-- must read these two values and no others. NOT NULL with no default
-- prevents the empty-string enum error value seen in the legacy data.

CREATE TABLE applications (
  application_id     INT AUTO_INCREMENT PRIMARY KEY,
  jobseeker_id       INT NOT NULL,
  job_id             INT NOT NULL,
  application_date   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  application_status ENUM('Pending','Reviewed','Accepted','Rejected')
                       NOT NULL DEFAULT 'Pending',
  ai_match_score     DECIMAL(6,4) NULL,
  employer_feedback  TEXT NULL,
  reviewed_at        DATETIME NULL,
  UNIQUE KEY uq_application (jobseeker_id, job_id),
  KEY idx_app_job_rank (job_id, ai_match_score DESC),
  KEY idx_app_seeker (jobseeker_id, application_date DESC),
  CONSTRAINT fk_app_seeker FOREIGN KEY (jobseeker_id)
    REFERENCES job_seekers(jobseeker_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_app_job FOREIGN KEY (job_id)
    REFERENCES job_postings(job_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- C-34 resolved: DECIMAL(6,4) preserves the engine's four decimals.
-- ai_match_score is a POINT-IN-TIME capture. It must never be
-- recalculated after insert. It is the score the employer evaluated.

CREATE TABLE job_match_scores (
  job_id          INT NOT NULL,
  jobseeker_id    INT NOT NULL,
  skill_score     DECIMAL(6,4) NOT NULL,
  geo_multiplier  DECIMAL(3,2) NOT NULL,
  final_score     DECIMAL(6,4) NOT NULL,
  raw_jaccard     DECIMAL(6,4) NOT NULL,
  mandatory_met   SMALLINT UNSIGNED NOT NULL,
  mandatory_total SMALLINT UNSIGNED NOT NULL,
  preferred_met   SMALLINT UNSIGNED NOT NULL,
  preferred_total SMALLINT UNSIGNED NOT NULL,
  engine_version  VARCHAR(20) NOT NULL,
  computed_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (job_id, jobseeker_id),
  KEY idx_seeker_rank (jobseeker_id, final_score DESC),
  KEY idx_job_rank (job_id, final_score DESC),
  CONSTRAINT fk_match_job FOREIGN KEY (job_id)
    REFERENCES job_postings(job_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_match_seeker FOREIGN KEY (jobseeker_id)
    REFERENCES job_seekers(jobseeker_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- C-35 resolved. The seeker dashboard reads this table and makes ZERO
-- HTTP calls to the AI service. Populated by triggers T1, T2 and T7.
-- final_score = skill_score * geo_multiplier, range 0.0000 - 1.0000.
-- The 0.40 constant (C-19) is gone: a perfect match is 1.0000 = 100%.


-- =====================================================================
--  SECTION 9  --  ADMINISTRATION AND MESSAGING  (relations 23-26)
-- =====================================================================

CREATE TABLE peso_admins (
  admin_id     INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT NOT NULL,
  admin_name   VARCHAR(150) NOT NULL,
  access_level ENUM('SuperAdmin','Moderator','Viewer')
                 NOT NULL DEFAULT 'Viewer',
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_admin_user (user_id),
  CONSTRAINT fk_admin_user FOREIGN KEY (user_id)
    REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- C-37 resolved: admin identity is now a real row, seeded in Section 11.
-- access_level must be enforced in AuthGuard, not merely stored.

CREATE TABLE audit_logs (
  log_id      INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NULL,
  action_type ENUM('login_success','login_failed','otp_requested','otp_failed',
                   'account_created','role_selected','profile_updated',
                   'job_created','job_updated','job_suspended',
                   'application_submitted','application_status_changed',
                   'employer_verified','employer_rejected',
                   'skill_approved','skill_rejected',
                   'account_suspended','account_reactivated') NOT NULL,
  entity_type VARCHAR(50) NULL,
  entity_id   INT NULL,
  description VARCHAR(255) NOT NULL,
  ip_address  VARCHAR(45) NULL,
  user_agent  VARCHAR(255) NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_user (user_id, created_at DESC),
  KEY idx_audit_action (action_type, created_at DESC),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id)
    REFERENCES users(user_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- C-36 resolved. The FK now targets users, not peso_admins: seekers and
-- employers generate auditable events too.
-- user_id is NULLABLE so that login_failed events - the ones you most
-- want logged - can be recorded when no account matched.

CREATE TABLE notifications (
  notification_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id         INT NOT NULL,
  event_type      ENUM('application_submitted','application_reviewed',
                       'application_accepted','application_rejected',
                       'new_applicant','employer_verified','employer_rejected',
                       'skill_approved','skill_rejected') NOT NULL,
  entity_type     ENUM('application','job_posting','employer','skill') NOT NULL,
  entity_id       INT NOT NULL,
  title           VARCHAR(150) NOT NULL,
  body            VARCHAR(500) NOT NULL,
  is_read         TINYINT(1) NOT NULL DEFAULT 0,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_notif_user (user_id, is_read, created_at DESC),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id)
    REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_log (
  email_id        INT AUTO_INCREMENT PRIMARY KEY,
  user_id         INT NULL,
  recipient_email VARCHAR(150) NOT NULL,
  template        VARCHAR(50) NOT NULL,
  subject         VARCHAR(200) NOT NULL,
  send_status     ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
  error_message   VARCHAR(255) NULL,
  sent_at         DATETIME NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_email_status (send_status, created_at),
  KEY idx_email_recipient (recipient_email, created_at DESC),
  CONSTRAINT fk_email_user FOREIGN KEY (user_id)
    REFERENCES users(user_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Exists because of C-22. A silent SMTP failure would repeat the exact
-- mistake the Python engine made: hiding a failure behind an apparent
-- success. Since logins now depend on email, a silent failure locks
-- users out with no trace. send_status='failed' makes it visible.
-- user_id is nullable: OTP mail goes to addresses with no account yet.


-- =====================================================================
--  SECTION 10  --  GEOGRAPHIC SEED DATA  (PSGC)
-- =====================================================================
-- NOTE ON REGIONS: Pangasinan is in Region I (Ilocos Region), NOT
-- Region III. It is seeded under its correct region below.
-- NCR contains no provinces; its four official PSGC districts are
-- seeded as lib_provinces rows so province_id can remain NOT NULL.

INSERT INTO lib_regions (region_code, region_name) VALUES
  ('010000000', 'Region I (Ilocos Region)'),
  ('030000000', 'Region III (Central Luzon)'),
  ('130000000', 'National Capital Region (NCR)');

SET @reg_i   = (SELECT region_id FROM lib_regions WHERE region_code='010000000');
SET @reg_iii = (SELECT region_id FROM lib_regions WHERE region_code='030000000');
SET @reg_ncr = (SELECT region_id FROM lib_regions WHERE region_code='130000000');

INSERT INTO lib_provinces (region_id, province_name) VALUES
  (@reg_iii, 'Nueva Ecija'),
  (@reg_iii, 'Tarlac'),
  (@reg_iii, 'Pampanga'),
  (@reg_iii, 'Bulacan'),
  (@reg_i,   'Pangasinan'),
  (@reg_ncr, 'NCR, City of Manila, First District'),
  (@reg_ncr, 'NCR, Second District (Eastern Manila)'),
  (@reg_ncr, 'NCR, Third District (CAMANAVA / Northern)'),
  (@reg_ncr, 'NCR, Fourth District (Southern)');

SET @p_ne  = (SELECT province_id FROM lib_provinces WHERE province_name='Nueva Ecija');
SET @p_tar = (SELECT province_id FROM lib_provinces WHERE province_name='Tarlac');
SET @p_pam = (SELECT province_id FROM lib_provinces WHERE province_name='Pampanga');
SET @p_bul = (SELECT province_id FROM lib_provinces WHERE province_name='Bulacan');
SET @p_pan = (SELECT province_id FROM lib_provinces WHERE province_name='Pangasinan');
SET @p_n1  = (SELECT province_id FROM lib_provinces WHERE province_name='NCR, City of Manila, First District');
SET @p_n2  = (SELECT province_id FROM lib_provinces WHERE province_name='NCR, Second District (Eastern Manila)');
SET @p_n3  = (SELECT province_id FROM lib_provinces WHERE province_name='NCR, Third District (CAMANAVA / Northern)');
SET @p_n4  = (SELECT province_id FROM lib_provinces WHERE province_name='NCR, Fourth District (Southern)');

-- Nueva Ecija: 5 cities + 27 municipalities (complete)
INSERT INTO lib_municipalities (province_id, municipality_name, municipality_type) VALUES
  (@p_ne,'Cabanatuan','City'), (@p_ne,'Gapan','City'),
  (@p_ne,'Science City of Muñoz','City'), (@p_ne,'Palayan','City'),
  (@p_ne,'San Jose','City'),
  (@p_ne,'Aliaga','Municipality'), (@p_ne,'Bongabon','Municipality'),
  (@p_ne,'Cabiao','Municipality'), (@p_ne,'Carranglan','Municipality'),
  (@p_ne,'Cuyapo','Municipality'), (@p_ne,'Gabaldon','Municipality'),
  (@p_ne,'General Mamerto Natividad','Municipality'),
  (@p_ne,'General Tinio','Municipality'), (@p_ne,'Guimba','Municipality'),
  (@p_ne,'Jaen','Municipality'), (@p_ne,'Laur','Municipality'),
  (@p_ne,'Licab','Municipality'), (@p_ne,'Llanera','Municipality'),
  (@p_ne,'Lupao','Municipality'), (@p_ne,'Nampicuan','Municipality'),
  (@p_ne,'Pantabangan','Municipality'), (@p_ne,'Peñaranda','Municipality'),
  (@p_ne,'Quezon','Municipality'), (@p_ne,'Rizal','Municipality'),
  (@p_ne,'San Antonio','Municipality'), (@p_ne,'San Isidro','Municipality'),
  (@p_ne,'San Leonardo','Municipality'), (@p_ne,'Santa Rosa','Municipality'),
  (@p_ne,'Santo Domingo','Municipality'), (@p_ne,'Talavera','Municipality'),
  (@p_ne,'Talugtug','Municipality'), (@p_ne,'Zaragoza','Municipality');

-- Neighbouring employment corridors (cities and major municipalities)
INSERT INTO lib_municipalities (province_id, municipality_name, municipality_type) VALUES
  (@p_tar,'Tarlac City','City'), (@p_tar,'Capas','Municipality'),
  (@p_tar,'Concepcion','Municipality'), (@p_tar,'Paniqui','Municipality'),
  (@p_tar,'Gerona','Municipality'), (@p_tar,'Victoria','Municipality'),
  (@p_pam,'Angeles','City'), (@p_pam,'City of San Fernando','City'),
  (@p_pam,'Mabalacat','City'), (@p_pam,'Apalit','Municipality'),
  (@p_pam,'Arayat','Municipality'), (@p_pam,'Candaba','Municipality'),
  (@p_bul,'Malolos','City'), (@p_bul,'San Jose del Monte','City'),
  (@p_bul,'Meycauayan','City'), (@p_bul,'Baliwag','City'),
  (@p_pan,'Dagupan','City'), (@p_pan,'San Carlos','City'),
  (@p_pan,'Urdaneta','City'), (@p_pan,'Alaminos','City'),
  (@p_pan,'Rosales','Municipality');

-- NCR cities by district
INSERT INTO lib_municipalities (province_id, municipality_name, municipality_type) VALUES
  (@p_n1,'City of Manila','City'),
  (@p_n2,'Quezon City','City'), (@p_n2,'Marikina','City'),
  (@p_n2,'Pasig','City'), (@p_n2,'San Juan','City'), (@p_n2,'Mandaluyong','City'),
  (@p_n3,'Caloocan','City'), (@p_n3,'Malabon','City'),
  (@p_n3,'Navotas','City'), (@p_n3,'Valenzuela','City'),
  (@p_n4,'Makati','City'), (@p_n4,'Taguig','City'), (@p_n4,'Pasay','City'),
  (@p_n4,'Parañaque','City'), (@p_n4,'Las Piñas','City'),
  (@p_n4,'Muntinlupa','City'), (@p_n4,'Pateros','Municipality');

-- Restore the preserved Guimba barangays under the correct municipality.
SET @m_guimba = (
  SELECT municipality_id FROM lib_municipalities
  WHERE municipality_name = 'Guimba' AND province_id = @p_ne
);

INSERT IGNORE INTO lib_barangays (municipality_id, barangay_name)
  SELECT @m_guimba, barangay_name FROM _tmp_legacy_barangays;

SELECT COUNT(*) AS guimba_barangays_restored
FROM lib_barangays WHERE municipality_id = @m_guimba;

-- Barangays for other municipalities are added on demand. The address
-- form must query lib_barangays FILTERED BY the selected municipality:
-- never load the full list client-side.


-- =====================================================================
--  SECTION 11  --  REFERENCE AND DEMO SEED DATA
-- =====================================================================

INSERT INTO skill_categories (category_name) VALUES
  ('Information Technology'), ('Healthcare'), ('Education'),
  ('Agriculture and Agri-Business'), ('Retail and E-Commerce'),
  ('Manufacturing and Production'), ('Finance and Accounting'),
  ('Hospitality and Tourism'), ('Skilled Trades and Construction'),
  ('Administrative and Clerical'), ('Transport and Logistics'),
  ('Customer Service'), ('Uncategorised');
-- Twelve sector categories spanning what a municipal PESO actually
-- serves. The legacy taxonomy was IT-weighted; a multi-industry
-- platform cannot be. 'Uncategorised' is the holding bucket for
-- seeker-proposed skills awaiting admin review: a nurse's or farmer's
-- proposed skill must not be silently filed under IT (it would corrupt
-- the sector analytics that feed Chapter IV). Sector breakdowns must
-- exclude 'Uncategorised' or report it as its own line.

SET @c_it   = (SELECT category_id FROM skill_categories WHERE category_name='Information Technology');
SET @c_hlth = (SELECT category_id FROM skill_categories WHERE category_name='Healthcare');
SET @c_educ = (SELECT category_id FROM skill_categories WHERE category_name='Education');
SET @c_agri = (SELECT category_id FROM skill_categories WHERE category_name='Agriculture and Agri-Business');
SET @c_retl = (SELECT category_id FROM skill_categories WHERE category_name='Retail and E-Commerce');
SET @c_manu = (SELECT category_id FROM skill_categories WHERE category_name='Manufacturing and Production');
SET @c_fin  = (SELECT category_id FROM skill_categories WHERE category_name='Finance and Accounting');
SET @c_trad = (SELECT category_id FROM skill_categories WHERE category_name='Skilled Trades and Construction');
SET @c_adm  = (SELECT category_id FROM skill_categories WHERE category_name='Administrative and Clerical');
SET @c_cust = (SELECT category_id FROM skill_categories WHERE category_name='Customer Service');

INSERT INTO master_skills (category_id, skill_name, status) VALUES
  (@c_it,'PHP','approved'), (@c_it,'MySQL','approved'),
  (@c_it,'JavaScript','approved'), (@c_it,'Python','approved'),
  (@c_it,'HTML/CSS','approved'), (@c_it,'Network Administration','approved'),
  (@c_it,'Technical Support','approved'), (@c_it,'Web Design','approved'),
  (@c_hlth,'Patient Care','approved'), (@c_hlth,'Nursing','approved'),
  (@c_hlth,'Caregiving','approved'), (@c_hlth,'Medical Records','approved'),
  (@c_educ,'Classroom Teaching','approved'), (@c_educ,'Tutoring','approved'),
  (@c_educ,'Curriculum Development','approved'),
  (@c_agri,'Rice Farming','approved'), (@c_agri,'Crop Management','approved'),
  (@c_agri,'Farm Equipment Operation','approved'),
  (@c_agri,'Poultry and Livestock','approved'),
  (@c_retl,'Cashiering','approved'), (@c_retl,'Inventory Management','approved'),
  (@c_retl,'Merchandising','approved'), (@c_retl,'Online Selling','approved'),
  (@c_manu,'Machine Operation','approved'), (@c_manu,'Quality Control','approved'),
  (@c_manu,'Assembly Line Work','approved'),
  (@c_fin,'Bookkeeping','approved'), (@c_fin,'Payroll Processing','approved'),
  (@c_fin,'Financial Reporting','approved'),
  (@c_trad,'Carpentry','approved'), (@c_trad,'Masonry','approved'),
  (@c_trad,'Electrical Installation','approved'),
  (@c_trad,'Welding','approved'), (@c_trad,'Plumbing','approved'),
  (@c_adm,'Data Entry','approved'), (@c_adm,'Filing and Records','approved'),
  (@c_adm,'MS Office','approved'), (@c_adm,'Scheduling','approved'),
  (@c_cust,'Customer Service','approved'), (@c_cust,'Call Handling','approved'),
  (@c_cust,'Complaint Resolution','approved');

-- Seed the PESO SuperAdmin. Replace the email before running.
INSERT INTO users (email, email_verified_at, role, account_status)
VALUES ('peso.admin@REPLACE-ME.gov.ph', NOW(), 'admin', 'Active');

SET @admin_user = LAST_INSERT_ID();

INSERT INTO user_auth_identities (user_id, provider, provider_uid)
VALUES (@admin_user, 'email', 'peso.admin@REPLACE-ME.gov.ph');

INSERT INTO peso_admins (user_id, admin_name, access_level)
VALUES (@admin_user, 'PESO Guimba Administrator', 'SuperAdmin');

INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, description)
VALUES (@admin_user, 'account_created', 'user', @admin_user,
        'Schema v2 migration: SuperAdmin account seeded');


-- =====================================================================
--  SECTION 12  --  VERIFICATION
-- =====================================================================
-- Every query below should return the expected value. If any does not,
-- stop and restore the Section 0 backup.

-- Expect 26 (plus _tmp_legacy_barangays until Section 13 runs).
SELECT COUNT(*) AS table_count
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE';

-- Expect 0. Any row here means a table was created without InnoDB
-- or with the wrong collation.
SELECT TABLE_NAME, ENGINE, TABLE_COLLATION
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE='BASE TABLE'
  AND (ENGINE <> 'InnoDB' OR TABLE_COLLATION <> 'utf8mb4_unicode_ci');

-- Expect roughly 30 foreign keys.
SELECT COUNT(*) AS foreign_key_count
FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'FOREIGN KEY';

-- Expect: 3 regions, 9 provinces, 76 municipalities.
SELECT
  (SELECT COUNT(*) FROM lib_regions)        AS regions,
  (SELECT COUNT(*) FROM lib_provinces)      AS provinces,
  (SELECT COUNT(*) FROM lib_municipalities) AS municipalities,
  (SELECT COUNT(*) FROM lib_barangays)      AS barangays;

-- Expect 0. Proves the hierarchy is intact end to end.
SELECT COUNT(*) AS orphaned_municipalities
FROM lib_municipalities m
LEFT JOIN lib_provinces p ON p.province_id = m.province_id
LEFT JOIN lib_regions   r ON r.region_id   = p.region_id
WHERE p.province_id IS NULL OR r.region_id IS NULL;

-- Expect exactly 1 row: Guimba, Nueva Ecija, Region III.
SELECT m.municipality_name, p.province_name, r.region_name,
       (SELECT COUNT(*) FROM lib_barangays b
        WHERE b.municipality_id = m.municipality_id) AS barangay_count
FROM lib_municipalities m
JOIN lib_provinces p ON p.province_id = m.province_id
JOIN lib_regions   r ON r.region_id   = p.region_id
WHERE m.municipality_name = 'Guimba';

-- Expect 12 categories, 41 approved skills, 0 pending.
SELECT
  (SELECT COUNT(*) FROM skill_categories) AS categories,
  (SELECT COUNT(*) FROM master_skills WHERE status='approved') AS approved_skills,
  (SELECT COUNT(*) FROM master_skills WHERE status='pending')  AS pending_skills;

-- Expect 1 admin, with a linked identity row.
SELECT u.user_id, u.email, u.role, u.account_status,
       a.access_level, i.provider
FROM users u
JOIN peso_admins a ON a.user_id = u.user_id
JOIN user_auth_identities i ON i.user_id = u.user_id;

-- Expect 0. Proves no password column survived anywhere.
SELECT COUNT(*) AS password_columns_remaining
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND (COLUMN_NAME LIKE '%password%' OR COLUMN_NAME LIKE '%passwd%');

-- Expect 0. Proves the 'Optional' enum value is gone (C-20).
SELECT COUNT(*) AS optional_enum_remaining
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND COLUMN_NAME = 'requirement_type'
  AND COLUMN_TYPE LIKE '%Optional%';

-- Expect 0. Proves the legacy orphan table is gone (C-32).
SELECT COUNT(*) AS legacy_barangays_table
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'barangays';


-- =====================================================================
--  SECTION 13  --  CLEANUP
-- =====================================================================
-- Run ONLY after Section 12 passes and the barangay count is correct.
-- Once this runs, the preserved legacy barangay names are gone from
-- the database and exist only in your Section 0 backup.

-- DROP TABLE IF EXISTS _tmp_legacy_barangays;


-- =====================================================================
--  SECTION 14  --  ROLLBACK
-- =====================================================================
-- PREFERRED: restore the Section 0 backup.
--
--   mysql -u USER -p sikaphub_v2_db < backup_pre_v2_YYYYMMDD_HHMM.sql
--
-- If the backup is unavailable, the block below removes everything this
-- script created, returning an empty database. It does NOT restore the
-- legacy schema or any legacy data. Uncomment to use.
--
-- SET FOREIGN_KEY_CHECKS = 0;
-- DROP TABLE IF EXISTS job_match_scores, applications, job_required_skills,
--   job_postings, jobseeker_skills, master_skills, skill_categories,
--   preferred_work_locations, job_preferences, work_experience, education,
--   resume_uploads, job_seekers, employers, audit_logs, notifications,
--   email_log, peso_admins, user_devices, email_otp_codes,
--   user_auth_identities, users, lib_barangays, lib_municipalities,
--   lib_provinces, lib_regions, _tmp_legacy_barangays;
-- SET FOREIGN_KEY_CHECKS = 1;


-- =====================================================================
--  SECTION 15  --  APPLICATION CHANGES REQUIRED AFTER THIS MIGRATION
-- =====================================================================
-- The database is only half the change. None of the following is
-- enforced by SQL, and the system will not work until each is done.
--
--  PHP
--   1. Delete OnboardingController.php, Onboarding.php, auth/onboarding.php
--      and the /onboarding routes                        (C-26..C-29)
--   2. Remove /test-ai; set display_errors = 0           (C-18)
--   3. Delete the triggerMatchComputation(1, $userId) call in
--      ProfileController                                  (C-33)
--   4. Replace the dashboard's per-job HTTP loop with a single read
--      from job_match_scores                              (C-35)
--   5. Write to audit_logs on every state change          (C-36)
--   6. Session cookie: SameSite=Lax, HttpOnly, Secure only under HTTPS
--   7. session_regenerate_id(true) after auth and after role selection
--   8. Fingerprint on User-Agent hash; do not destroy a session on IP
--      change (CGNAT and mobile carriers rotate addresses)
--   9. OAuth: verify state, nonce, and the ID token signature against
--      Google's public keys, plus iss / aud / exp
--  10. Every table name lowercase - Hostinger is Linux    (C-28)
--
--  PYTHON
--  11. Verify the HMAC for real; return 401 on mismatch   (C-17)
--  12. Return 4xx/5xx on failure; delete the fabricated success
--      fallback payload and the hardcoded .get() defaults (C-22)
--  13. Read requirement_type as 'Mandatory' / 'Preferred' (C-20)
--  14. Remove the * 0.40 constant                         (C-19)
--  15. Tiered geo multiplier: 1.00 same municipality, 0.90 preferred,
--      0.75 same province, 0.50 other, 1.00 when location is unknown
--  16. Add POST /api/v1/compute-batch and /api/v1/extract-skills
--  17. Delete database.py - the service is stateless      (C-25)
--
--  RANKING (must match the normalization document exactly)
--  18. Feed order:  employers.verified_status='Verified' DESC,
--                   final_score DESC,
--                   summed proficiency ordinal DESC,
--                   profile_completeness DESC,
--                   jobseeker_id ASC
--      Every card displays its percentage regardless of badge state.
-- =====================================================================
--  END OF MIGRATION
-- =====================================================================
