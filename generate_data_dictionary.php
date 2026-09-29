<?php
/**
 * =====================================================================
 *  S.I.K.A.P. HUB  --  DATA DICTIONARY GENERATOR
 * =====================================================================
 *  Reads the LIVE database schema from information_schema and emits a
 *  complete Data Dictionary in Markdown.
 *
 *  Why generated instead of typed:
 *  A hand-written data dictionary drifts from the database within days.
 *  That drift is Conflict C-13 in our audit register -- the March
 *  dictionary listed importance_level and skill_level when the database
 *  held requirement_type and proficiency_level. Types, sizes, keys,
 *  nullability and defaults are read from the database itself, so those
 *  facts cannot be wrong. Only the human-written Description column is
 *  maintained by hand, and the script reports any column missing one.
 *
 *  USAGE (XAMPP, from the project root):
 *      php generate_data_dictionary.php > DATA_DICTIONARY.md
 *
 *  Windows, if php is not on PATH:
 *      C:\xampp\php\php.exe generate_data_dictionary.php > DATA_DICTIONARY.md
 *
 *  Then convert for submission:
 *      pandoc DATA_DICTIONARY.md -o DATA_DICTIONARY.docx
 * =====================================================================
 */

declare(strict_types=1);

// ---------------------------------------------------------------------
//  CONNECTION
// ---------------------------------------------------------------------
const DB_HOST = '127.0.0.1';
const DB_NAME = 'sikaphub_v2_db';
const DB_USER = 'root';
const DB_PASS = '';          // XAMPP default. Never commit a real password.

// ---------------------------------------------------------------------
//  DOMAIN GROUPING  --  controls section order in the output
// ---------------------------------------------------------------------
$DOMAINS = [
    'Authentication and Identity' => [
        'users', 'user_auth_identities', 'email_otp_codes', 'user_devices',
    ],
    'Geographic Hierarchy (PSGC)' => [
        'lib_regions', 'lib_provinces', 'lib_municipalities', 'lib_barangays',
    ],
    'Job Seeker Domain' => [
        'job_seekers', 'education', 'work_experience', 'job_preferences',
        'preferred_work_locations', 'resume_uploads',
    ],
    'Skills Taxonomy' => [
        'skill_categories', 'master_skills', 'jobseeker_skills',
    ],
    'Employer and Job Domain' => [
        'employers', 'job_postings', 'job_required_skills',
    ],
    'Matching and Applications' => [
        'applications', 'job_match_scores',
    ],
    'Administration and Messaging' => [
        'peso_admins', 'audit_logs', 'notifications', 'email_log',
    ],
];

$TABLE_PURPOSE = [
    'users'                    => 'Account root. One row per person. Holds no password: authentication is delegated to user_auth_identities.',
    'user_auth_identities'     => 'One row per authentication provider per account. Enables a single account to sign in via Google and via email OTP.',
    'email_otp_codes'          => 'Short-lived one-time codes for email authentication. Keyed on email rather than user_id because the account may not exist yet at signup.',
    'user_devices'             => 'Remembered-device tokens. One row per device, so a phone and a laptop can both be remembered independently.',
    'lib_regions'              => 'Top level of the Philippine Standard Geographic Code hierarchy.',
    'lib_provinces'            => 'Second level. NCR is represented by its four official PSGC districts so that province_id can remain NOT NULL.',
    'lib_municipalities'       => 'Third level. The granularity at which job matching evaluates geographic proximity.',
    'lib_barangays'            => 'Fourth level. Used for postal addresses only; never used in match scoring.',
    'job_seekers'              => 'Job seeker profile. One row per jobseeker account.',
    'education'                => 'Educational attainment. Repeating group extracted from the seeker profile at 1NF.',
    'work_experience'          => 'Employment history. Repeating group extracted at 1NF. A NULL end_date means currently employed.',
    'job_preferences'          => 'Optional seeker preferences, held as a vertical partition to keep nullable columns out of the core profile relation.',
    'preferred_work_locations' => 'Associative relation linking a seeker to the municipalities they will work in. Drives the 0.90 geographic tier.',
    'resume_uploads'           => 'Uploaded resume files and their parser output. parsed_payload is a transient staging buffer, not authoritative data.',
    'skill_categories'         => 'Industry sectors. Twelve categories spanning the sectors a municipal PESO serves.',
    'master_skills'            => 'Controlled skill vocabulary. Only rows with status = approved participate in matching.',
    'jobseeker_skills'         => 'Skills claimed by a seeker, with self-declared proficiency. Proficiency is the second-level ranking tiebreaker.',
    'employers'                => 'Employer company profile and verification state.',
    'job_postings'             => 'Job vacancies. Carries no employer name or verification status: both are reached by join, so verifying an employer promotes all their postings at once.',
    'job_required_skills'      => 'Skills a posting requires, each flagged Mandatory or Preferred. Mandatory carries double weight in scoring.',
    'applications'             => 'Submitted applications. ai_match_score is a point-in-time capture and is never recalculated after insert.',
    'job_match_scores'         => 'Precomputed match score cache. The seeker dashboard reads this table and makes zero HTTP calls to the AI service.',
    'peso_admins'              => 'Administrative subtype of users. access_level governs what an administrator may do.',
    'audit_logs'               => 'Immutable record of state changes. user_id is nullable so failed logins can be recorded when no account matched.',
    'notifications'            => 'In-app notification feed.',
    'email_log'                => 'SMTP delivery record. Exists so that a failed send is visible rather than silent.',
];

// ---------------------------------------------------------------------
//  COLUMN DESCRIPTIONS
//  The only hand-maintained part. Keyed 'table.column'.
//  Any column absent from this map is reported in the coverage section.
// ---------------------------------------------------------------------
$DESC = [
    // users
    'users.user_id' => 'Unique account identifier.',
    'users.email' => 'Account email address. Also the key used to link authentication providers.',
    'users.email_verified_at' => 'Timestamp at which ownership of the address was proven. NULL means unverified.',
    'users.role' => 'Account role. NULL until the post-authentication role picker is completed.',
    'users.account_status' => 'Lifecycle state of the account.',
    'users.last_login_at' => 'Timestamp of the most recent successful authentication.',
    'users.created_at' => 'Record creation timestamp.',
    'users.updated_at' => 'Timestamp of the most recent modification.',

    // user_auth_identities
    'user_auth_identities.identity_id' => 'Unique identity record identifier.',
    'user_auth_identities.user_id' => 'Account this identity belongs to.',
    'user_auth_identities.provider' => 'Authentication provider used.',
    'user_auth_identities.provider_uid' => "Google's stable sub claim, or the email address for OTP authentication. Never the Google email, which can change.",
    'user_auth_identities.last_used_at' => 'Timestamp this identity was last used to sign in.',
    'user_auth_identities.created_at' => 'Record creation timestamp.',

    // email_otp_codes
    'email_otp_codes.otp_id' => 'Unique code record identifier.',
    'email_otp_codes.email' => 'Address the code was issued to.',
    'email_otp_codes.code_hash' => 'Hash of the six-digit code. The code itself is never stored.',
    'email_otp_codes.purpose' => 'Whether the code was issued for signup or for login.',
    'email_otp_codes.attempt_count' => 'Failed verification attempts. The code is invalidated at five.',
    'email_otp_codes.expires_at' => 'Expiry timestamp, ten minutes after issue.',
    'email_otp_codes.consumed_at' => 'Timestamp the code was successfully redeemed. NULL if unused.',
    'email_otp_codes.ip_address' => 'Requesting IP address, retained for rate limiting.',
    'email_otp_codes.created_at' => 'Issue timestamp.',

    // user_devices
    'user_devices.device_id' => 'Unique remembered-device identifier.',
    'user_devices.user_id' => 'Account the device belongs to.',
    'user_devices.token_hash' => 'SHA-256 hash of a 32-byte random token. Rotated on every use.',
    'user_devices.device_label' => 'Human-readable device name shown in account settings.',
    'user_devices.user_agent_hash' => 'Hash of the browser user agent, used for session fingerprinting.',
    'user_devices.last_used_at' => 'Timestamp the token was last presented.',
    'user_devices.expires_at' => 'Expiry timestamp, thirty days after issue.',
    'user_devices.revoked_at' => 'Timestamp of revocation. Set for all of a user\'s devices if token reuse is detected.',
    'user_devices.created_at' => 'Record creation timestamp.',

    // geography
    'lib_regions.region_id' => 'Unique region identifier.',
    'lib_regions.region_code' => 'Official PSGC region code.',
    'lib_regions.region_name' => 'Official region name.',
    'lib_provinces.province_id' => 'Unique province or NCR district identifier.',
    'lib_provinces.region_id' => 'Parent region.',
    'lib_provinces.province_name' => 'Official province name, or NCR district name.',
    'lib_municipalities.municipality_id' => 'Unique city or municipality identifier.',
    'lib_municipalities.province_id' => 'Parent province or NCR district.',
    'lib_municipalities.municipality_name' => 'Official city or municipality name.',
    'lib_municipalities.municipality_type' => 'Whether the locality is a city or a municipality.',
    'lib_barangays.barangay_id' => 'Unique barangay identifier.',
    'lib_barangays.municipality_id' => 'Parent city or municipality.',
    'lib_barangays.barangay_name' => 'Official barangay name.',

    // job_seekers
    'job_seekers.jobseeker_id' => 'Unique job seeker identifier.',
    'job_seekers.user_id' => 'Account this profile belongs to.',
    'job_seekers.first_name' => 'Given name.',
    'job_seekers.middle_name' => 'Middle name, optional.',
    'job_seekers.last_name' => 'Surname.',
    'job_seekers.gender' => 'Self-declared gender.',
    'job_seekers.birthdate' => 'Date of birth.',
    'job_seekers.contact_number' => 'Primary mobile number.',
    'job_seekers.house_number' => 'House or unit number.',
    'job_seekers.street_name' => 'Street name.',
    'job_seekers.barangay_id' => 'Barangay of residence, for postal address only.',
    'job_seekers.home_municipality_id' => 'Home municipality. Documented denormalization: derivable via barangay_id, retained so the matching engine avoids a join on every computation.',
    'job_seekers.profile_photo' => 'Path to the uploaded profile image.',
    'job_seekers.profile_visibility' => 'Whether employers may browse this profile.',
    'job_seekers.profile_completeness' => 'Percentage of profile fields completed. Materialized derived value used as the third-level ranking tiebreaker.',
    'job_seekers.created_at' => 'Record creation timestamp.',
    'job_seekers.updated_at' => 'Timestamp of the most recent modification.',

    // education
    'education.education_id' => 'Unique education record identifier.',
    'education.jobseeker_id' => 'Seeker this record belongs to.',
    'education.degree_level' => 'Level or title of the qualification attained.',
    'education.school_name' => 'Name of the institution.',
    'education.year_graduated' => 'Year the qualification was completed.',

    // work_experience
    'work_experience.experience_id' => 'Unique employment record identifier.',
    'work_experience.jobseeker_id' => 'Seeker this record belongs to.',
    'work_experience.job_title' => 'Position held.',
    'work_experience.company_name' => 'Employer name.',
    'work_experience.start_date' => 'Date employment began.',
    'work_experience.end_date' => 'Date employment ended. NULL means currently employed.',
    'work_experience.job_description' => 'Summary of duties and achievements.',

    // job_preferences
    'job_preferences.jobseeker_id' => 'Seeker these preferences belong to. Also the primary key: one row per seeker.',
    'job_preferences.desired_job_type' => 'Preferred employment type.',
    'job_preferences.preferred_work_setup' => 'Preferred work arrangement, matched for display against job_postings.work_arrangement.',
    'job_preferences.expected_salary' => 'Expected monthly compensation. Display only in version 1.',
    'job_preferences.updated_at' => 'Timestamp of the most recent modification.',

    // preferred_work_locations
    'preferred_work_locations.jobseeker_id' => 'Seeker expressing the preference.',
    'preferred_work_locations.municipality_id' => 'Municipality the seeker will work in. Drives the 0.90 geographic proximity tier.',

    // resume_uploads
    'resume_uploads.upload_id' => 'Unique upload identifier.',
    'resume_uploads.user_id' => 'Account that performed the upload.',
    'resume_uploads.jobseeker_id' => 'Seeker profile the upload belongs to. Nullable because the upload precedes profile creation.',
    'resume_uploads.stored_filename' => 'Randomized filename on disk. Never the name supplied by the user.',
    'resume_uploads.original_filename' => 'Filename as supplied, retained for display only.',
    'resume_uploads.file_hash' => 'SHA-256 of the file, used to detect re-uploads and verify integrity.',
    'resume_uploads.mime_type' => 'MIME type determined by magic-byte inspection, not by file extension.',
    'resume_uploads.file_size_bytes' => 'File size in bytes.',
    'resume_uploads.parse_status' => 'Outcome of the parsing attempt.',
    'resume_uploads.parsed_payload' => 'Extracted fields awaiting seeker confirmation. Transient staging buffer, outside the scope of relational normalization.',
    'resume_uploads.parse_error' => 'Failure reason when parse_status is failed.',
    'resume_uploads.parser_version' => 'Build of the parser that produced the payload. Enables the parser accuracy analysis in Chapter IV.',
    'resume_uploads.created_at' => 'Upload timestamp.',

    // skills
    'skill_categories.category_id' => 'Unique category identifier.',
    'skill_categories.category_name' => 'Industry sector name.',
    'master_skills.skill_id' => 'Unique skill identifier.',
    'master_skills.category_id' => 'Industry sector this skill belongs to.',
    'master_skills.skill_name' => 'Canonical skill name.',
    'master_skills.status' => 'Moderation state. Only approved skills participate in match scoring.',
    'master_skills.submitted_by_user_id' => 'Account that proposed the skill, when user-submitted.',
    'master_skills.created_at' => 'Record creation timestamp.',
    'jobseeker_skills.jobseeker_id' => 'Seeker claiming the skill.',
    'jobseeker_skills.skill_id' => 'Skill claimed.',
    'jobseeker_skills.proficiency_level' => 'Self-declared proficiency. Ordinal mapping fixed in application code: Beginner 1, Intermediate 2, Expert 3.',

    // employers
    'employers.employer_id' => 'Unique employer identifier.',
    'employers.user_id' => 'Account this company profile belongs to.',
    'employers.company_name' => 'Registered business name.',
    'employers.contact_person' => 'Name of the HR representative or owner.',
    'employers.company_email' => 'Official business email address.',
    'employers.company_phone' => 'Official business contact number.',
    'employers.house_number' => 'Building or unit number.',
    'employers.street_name' => 'Street name.',
    'employers.barangay_id' => 'Barangay of the business address.',
    'employers.municipality_id' => 'Municipality of the business address.',
    'employers.industry' => 'Industry sector the company operates in.',
    'employers.company_size' => 'Employee headcount band.',
    'employers.company_description' => 'Company profile text shown to job seekers.',
    'employers.company_logo' => 'Path to the uploaded company logo.',
    'employers.company_website' => 'Company website URL.',
    'employers.business_permit_file' => 'Path to the uploaded business permit, reviewed during verification.',
    'employers.verified_status' => 'PESO verification state. Governs the green or amber badge and the feed ranking tier.',
    'employers.verified_at' => 'Timestamp of the verification decision.',
    'employers.created_at' => 'Record creation timestamp.',
    'employers.updated_at' => 'Timestamp of the most recent modification.',

    // job_postings
    'job_postings.job_id' => 'Unique job posting identifier.',
    'job_postings.employer_id' => 'Employer that owns the posting.',
    'job_postings.job_title' => 'Position title.',
    'job_postings.job_description' => 'Duties, responsibilities and requirements.',
    'job_postings.salary_range' => 'Advertised compensation range. Display only.',
    'job_postings.min_years_experience' => 'Minimum years of relevant experience required.',
    'job_postings.employment_type' => 'Nature of the engagement.',
    'job_postings.work_arrangement' => 'On-site, remote or hybrid working pattern.',
    'job_postings.municipality_id' => 'Municipality of the workplace. Input to the geographic proximity multiplier.',
    'job_postings.date_posted' => 'Timestamp the posting was created.',
    'job_postings.updated_at' => 'Timestamp of the most recent modification.',
    'job_postings.job_status' => 'Publication state of the posting.',

    // job_required_skills
    'job_required_skills.job_id' => 'Posting the requirement belongs to.',
    'job_required_skills.skill_id' => 'Skill required.',
    'job_required_skills.requirement_type' => 'Whether the skill is Mandatory (weight 2.0) or Preferred (weight 1.0). The value Optional does not exist in this system.',

    // applications
    'applications.application_id' => 'Unique application identifier.',
    'applications.jobseeker_id' => 'Applicant.',
    'applications.job_id' => 'Posting applied to.',
    'applications.application_date' => 'Submission timestamp.',
    'applications.application_status' => 'Progress of the application through employer review.',
    'applications.ai_match_score' => 'Match score captured at the moment of application. Never recalculated: this is the value the employer evaluated.',
    'applications.employer_feedback' => 'Remarks returned to the applicant.',
    'applications.reviewed_at' => 'Timestamp the employer first reviewed the application.',

    // job_match_scores
    'job_match_scores.job_id' => 'Posting side of the scored pair.',
    'job_match_scores.jobseeker_id' => 'Seeker side of the scored pair.',
    'job_match_scores.skill_score' => 'Weighted skill match, range 0 to 1, before geographic adjustment.',
    'job_match_scores.geo_multiplier' => 'Proximity tier: 1.00 same municipality, 0.90 preferred, 0.75 same province, 0.50 other, 1.00 when location is unknown.',
    'job_match_scores.final_score' => 'skill_score multiplied by geo_multiplier. Range 0 to 1; displayed as a percentage.',
    'job_match_scores.raw_jaccard' => 'Unweighted set-overlap similarity. Reported as a diagnostic baseline; not a component of final_score.',
    'job_match_scores.mandatory_met' => 'Count of mandatory requirements the seeker satisfies.',
    'job_match_scores.mandatory_total' => 'Count of mandatory requirements at the time of computation. Point-in-time snapshot.',
    'job_match_scores.preferred_met' => 'Count of preferred requirements the seeker satisfies.',
    'job_match_scores.preferred_total' => 'Count of preferred requirements at the time of computation. Point-in-time snapshot.',
    'job_match_scores.engine_version' => 'Build of the matching service that produced the row.',
    'job_match_scores.computed_at' => 'Timestamp the score was calculated.',

    // administration
    'peso_admins.admin_id' => 'Unique administrator identifier.',
    'peso_admins.user_id' => 'Account holding administrative rights.',
    'peso_admins.admin_name' => 'Full name of the PESO staff member.',
    'peso_admins.access_level' => 'Privilege tier. Enforced in the authorization guard, not merely recorded.',
    'peso_admins.created_at' => 'Record creation timestamp.',
    'audit_logs.log_id' => 'Unique log entry identifier.',
    'audit_logs.user_id' => 'Account responsible for the action. NULL for failed logins where no account matched.',
    'audit_logs.action_type' => 'Category of the recorded action.',
    'audit_logs.entity_type' => 'Type of record the action affected.',
    'audit_logs.entity_id' => 'Identifier of the affected record.',
    'audit_logs.description' => 'Human-readable summary of the action.',
    'audit_logs.ip_address' => 'Originating IP address.',
    'audit_logs.user_agent' => 'Originating browser user agent.',
    'audit_logs.created_at' => 'Timestamp of the action.',
    'notifications.notification_id' => 'Unique notification identifier.',
    'notifications.user_id' => 'Recipient account.',
    'notifications.event_type' => 'Event that triggered the notification.',
    'notifications.entity_type' => 'Type of record the notification refers to.',
    'notifications.entity_id' => 'Identifier of the referenced record.',
    'notifications.title' => 'Notification headline.',
    'notifications.body' => 'Notification message text.',
    'notifications.is_read' => 'Whether the recipient has opened the notification.',
    'notifications.created_at' => 'Creation timestamp.',
    'email_log.email_id' => 'Unique delivery record identifier.',
    'email_log.user_id' => 'Recipient account. NULL for one-time-password mail sent before an account exists.',
    'email_log.recipient_email' => 'Address the message was actually sent to. Retained even if the account email later changes.',
    'email_log.template' => 'Message template used.',
    'email_log.subject' => 'Subject line as sent.',
    'email_log.send_status' => 'Delivery outcome. A failed send must be visible, never silent.',
    'email_log.error_message' => 'SMTP error text when delivery failed.',
    'email_log.sent_at' => 'Timestamp of successful dispatch.',
    'email_log.created_at' => 'Timestamp the message was queued.',
];

// =====================================================================
//  EXECUTION
// =====================================================================
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    fwrite(STDERR, "Connection failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

/** Columns, with key role and foreign key target resolved. */
$columns = $pdo->prepare(
    "SELECT
        c.TABLE_NAME,
        c.COLUMN_NAME,
        c.ORDINAL_POSITION,
        c.DATA_TYPE,
        c.COLUMN_TYPE,
        c.CHARACTER_MAXIMUM_LENGTH,
        c.NUMERIC_PRECISION,
        c.NUMERIC_SCALE,
        c.IS_NULLABLE,
        c.COLUMN_DEFAULT,
        c.EXTRA,
        c.COLUMN_KEY,
        k.REFERENCED_TABLE_NAME,
        k.REFERENCED_COLUMN_NAME
     FROM information_schema.COLUMNS c
     LEFT JOIN information_schema.KEY_COLUMN_USAGE k
            ON  k.TABLE_SCHEMA = c.TABLE_SCHEMA
            AND k.TABLE_NAME   = c.TABLE_NAME
            AND k.COLUMN_NAME  = c.COLUMN_NAME
            AND k.REFERENCED_TABLE_NAME IS NOT NULL
     WHERE c.TABLE_SCHEMA = :db
     ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION"
);
$columns->execute([':db' => DB_NAME]);

$byTable = [];
foreach ($columns as $row) {
    $byTable[$row['TABLE_NAME']][] = $row;
}

/** Table-level metadata. */
$tables = $pdo->prepare(
    "SELECT TABLE_NAME, ENGINE, TABLE_COLLATION, TABLE_ROWS
     FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = :db AND TABLE_TYPE = 'BASE TABLE'"
);
$tables->execute([':db' => DB_NAME]);
$meta = [];
foreach ($tables as $t) {
    $meta[$t['TABLE_NAME']] = $t;
}

// ---------------------------------------------------------------------
//  HELPERS
// ---------------------------------------------------------------------

/** Human-readable size for the Size column. */
function formatColumnSize(array $c): string
{
    if ($c['CHARACTER_MAXIMUM_LENGTH'] !== null) {
        return (string) $c['CHARACTER_MAXIMUM_LENGTH'];
    }
    if ($c['DATA_TYPE'] === 'decimal') {
        return $c['NUMERIC_PRECISION'] . ',' . $c['NUMERIC_SCALE'];
    }
    if (in_array($c['DATA_TYPE'], ['enum', 'set'], true)) {
        return '—';
    }
    if ($c['NUMERIC_PRECISION'] !== null) {
        return (string) $c['NUMERIC_PRECISION'];
    }
    return '—';
}

/** Constraint string: PK / FK / UNIQUE / NOT NULL / AUTO_INCREMENT / DEFAULT. */
function constraintsOf(array $c): string
{
    $parts = [];
    if ($c['COLUMN_KEY'] === 'PRI') {
        $parts[] = 'PK';
    }
    if ($c['REFERENCED_TABLE_NAME'] !== null) {
        $parts[] = 'FK → ' . $c['REFERENCED_TABLE_NAME'] . '.' . $c['REFERENCED_COLUMN_NAME'];
    }
    if ($c['COLUMN_KEY'] === 'UNI') {
        $parts[] = 'UNIQUE';
    }
    $parts[] = $c['IS_NULLABLE'] === 'NO' ? 'NOT NULL' : 'NULL';
    if (str_contains($c['EXTRA'], 'auto_increment')) {
        $parts[] = 'AUTO_INCREMENT';
    }
    if ($c['COLUMN_DEFAULT'] !== null && $c['COLUMN_DEFAULT'] !== 'NULL') {
        $parts[] = 'DEFAULT ' . trim((string) $c['COLUMN_DEFAULT'], "'");
    }
    return implode(', ', $parts);
}

/** Enum and set members, rendered for the notes line. */
function enumValues(array $c): ?string
{
    if (!in_array($c['DATA_TYPE'], ['enum', 'set'], true)) {
        return null;
    }
    preg_match_all("/'((?:[^']|'')*)'/", $c['COLUMN_TYPE'], $m);
    return implode(' · ', array_map(
        static fn($v) => str_replace("''", "'", $v),
        $m[1]
    ));
}

function md(string $s): string
{
    return str_replace('|', '\\|', $s);
}

// ---------------------------------------------------------------------
//  OUTPUT
// ---------------------------------------------------------------------
$missing   = [];
$colCount  = 0;
$fkCount   = 0;

echo "# Data Dictionary\n\n";
echo "**SMART INTEGRATED KNOWLEDGE & ABILITY PLATFORM (S.I.K.A.P.) HUB**\n\n";
echo "| | |\n|---|---|\n";
echo "| Database | `" . DB_NAME . "` |\n";
echo "| Generated | " . date('j F Y, H:i') . " |\n";
echo "| Server | " . $pdo->query('SELECT VERSION()')->fetchColumn() . " |\n";
echo "| Relations | " . count($meta) . " |\n";
echo "| Normal form | Third Normal Form (3NF) |\n\n";

echo "> This document is generated directly from `information_schema`. ";
echo "Data types, sizes, keys, nullability and defaults are read from the ";
echo "live database and cannot diverge from it. Regenerate after any schema ";
echo "change rather than editing this file by hand.\n\n";
echo "---\n\n";

$section = 0;
foreach ($DOMAINS as $domainName => $domainTables) {
    $section++;
    echo "## {$section}. {$domainName}\n\n";

    foreach ($domainTables as $table) {
        if (!isset($byTable[$table])) {
            echo "> **`{$table}`** not found in the database. Check the migration.\n\n";
            continue;
        }

        echo "### `{$table}`\n\n";

        if (isset($TABLE_PURPOSE[$table])) {
            echo md($TABLE_PURPOSE[$table]) . "\n\n";
        }

        echo "| Field Name | Data Type | Size | Constraints | Description |\n";
        echo "|---|---|---|---|---|\n";

        $enumNotes = [];

        foreach ($byTable[$table] as $c) {
            $colCount++;
            if ($c['REFERENCED_TABLE_NAME'] !== null) {
                $fkCount++;
            }

            $key = $table . '.' . $c['COLUMN_NAME'];
            if (isset($DESC[$key])) {
                $description = $DESC[$key];
            } else {
                $description = '**[ description required ]**';
                $missing[] = $key;
            }

            printf(
                "| `%s` | %s | %s | %s | %s |\n",
                $c['COLUMN_NAME'],
                strtoupper($c['DATA_TYPE']),
                formatColumnSize($c),
                md(constraintsOf($c)),
                md($description)
            );

            if ($vals = enumValues($c)) {
                $enumNotes[$c['COLUMN_NAME']] = $vals;
            }
        }

        echo "\n";

        if ($enumNotes) {
            echo "**Permitted values**\n\n";
            foreach ($enumNotes as $col => $vals) {
                echo "- `{$col}`: {$vals}\n";
            }
            echo "\n";
        }

        $m = $meta[$table] ?? null;
        if ($m) {
            echo "*Engine: {$m['ENGINE']} · Collation: {$m['TABLE_COLLATION']}*\n\n";
        }
    }
}

// ---------------------------------------------------------------------
//  COVERAGE REPORT
// ---------------------------------------------------------------------
echo "---\n\n";
echo "## Generation Summary\n\n";
echo "| Metric | Value |\n|---|---|\n";
echo "| Relations documented | " . count($meta) . " |\n";
echo "| Columns documented | {$colCount} |\n";
echo "| Foreign key columns | {$fkCount} |\n";
echo "| Descriptions missing | " . count($missing) . " |\n\n";

if ($missing) {
    echo "### Columns Requiring a Description\n\n";
    echo "These exist in the database but have no entry in the `\$DESC` map. ";
    echo "Add them to `generate_data_dictionary.php` and regenerate before submitting.\n\n";
    foreach ($missing as $key) {
        echo "- `{$key}`\n";
    }
    echo "\n";
} else {
    echo "All columns have descriptions. Document is complete.\n\n";
}

// Tables present in the database but absent from $DOMAINS.
$grouped   = array_merge(...array_values($DOMAINS));
$ungrouped = array_diff(array_keys($meta), $grouped);
if ($ungrouped) {
    echo "### Tables Not Assigned to a Domain\n\n";
    foreach ($ungrouped as $t) {
        echo "- `{$t}`\n";
    }
    echo "\n> Assign these in the `\$DOMAINS` array, or drop them if they are ";
    echo "leftovers such as `_tmp_legacy_barangays`.\n\n";
}
