# S.I.K.A.P. Hub — Project State

Current Phase: Phase 4 (Refactoring & Implementation)
Active Task: DONE — Review Candidate page off HTTP 500 (Task 6 Commit 4; C-51,
  C-44). Employer::getApplicationDetails() selected js.resume_file and
  js.street_address — neither exists in schema v2 (street_name; resumes are in
  resume_uploads) — so GET /employer/review-candidate threw an uncaught
  PDOException and 500'd for every valid application (the dashboard list was
  fine; opening an applicant died). The query now selects street_name and
  drops resume_file; the résumé is sourced from resume_uploads via the new
  Employer::getSeekerResume(), with an honest "No résumé on file" empty state
  where no row exists (UC-02 résumé upload is deferred, so that is the state
  for every real applicant today); the review_candidate view reads street_name
  and $resume instead of the two phantom keys, and its photo/résumé links drop
  the dead ?type= param (the 7d44b12 gateway resolves ?file= itself).
  EmployerController::reviewCandidate() wraps the whole path in
  try/catch -> http_response_code(500) + errors/500 (C-44 pattern from
  JobSeekerController::dashboard) so a future schema drift shows the error
  view, not a stack trace. The score on the page is unchanged: the frozen
  applications.ai_match_score (UC-05 A3), never a live job_match_scores read.
  updateApplicationStatus() was verified working and left as-is (ownership
  enforced by the controller's IDOR pre-check AND the UPDATE's own JOIN).
  seed_demo.php now records application_id in the manifest. 36 assertions in
  scripts/verify_review_candidate.php, all passing; the six prior harnesses
  re-run green.
Follow-up (Task 6 Commit 5): JobSeeker::saveCompleteProfile() DELETED. It was
  confirmed unreachable during Commit 4 — no route, controller, view, JS,
  service or internal caller referenced it; the string appeared only at its own
  definition and in this doc. It wrote the dropped job_seekers.resume_file
  column and INSERTed unknown skills with a hardcoded category_id = 7
  (Information Technology), which the locked taxonomy decision forbids — dead
  code that would have been a real defect if wired up. The live seeker
  profile-write path is unchanged: POST /build-profile ->
  ProfileController::processJobSeekerUpdate -> Profile::saveCompleteSeekerProfile(),
  which files unknown skills under 'Uncategorised'. All seven harnesses
  (adding verify_review_candidate) re-run green.
Prev Task: DONE — Admin employer verification hardened (Task 6 Commit 3).
  AdminController::verifyEmployer() now: rejects a non-admin with HTTP 403 +
  errors/500, ignores non-POST with a redirect, rejects an unknown status or
  bad id with a redirect to ?error=invalid_status (no more blank 200), and
  turns a failed write into HTTP 500 + errors/500. On success it writes an
  employer_verified / employer_rejected audit_logs row via Audit::write()
  (C-36) and enqueues a notifications row (Q-18 direct insert), the insert
  wrapped so a failure is logged and swallowed — it never rolls back the
  decision (same rule as SMTP). Admin::updateEmployerVerification() runs one
  transaction: verified_status + verified_at, and on REJECTION only, every
  Open posting by that employer -> job_status 'Suspended' (D-18); the audit
  row records the count. Every remaining die() in AdminController is gone
  (dashboard, approveSkill, exportPdf, the four nav placeholders) except
  CSRF::verifyRequest()'s and Controller::view()'s. The dashboard permit link
  bug is fixed (business_permit_file, not $emp['business_permit'] — the link
  rendered empty), the pending-skills list shows the proposer's email
  (LEFT JOIN users; the view read $ps['suggested_by'] against a column that
  was never selected), and $admin_name is passed to the view from
  peso_admins. 48 assertions in scripts/verify_admin_verification.php (one
  SKIPPED when the mod_php error_log is not resolvable), all passing; the five
  prior harnesses still green.
Next Task: none scheduled. Candidates: build AuditService + wire the other
  five UC-00c include sites and NotificationService (finishes C-36); the
  job-EDIT route (see the T2 note in Blockers); peso_admins.access_level
  enforcement (C-37); Google OAuth (UC-01 Path A).
Later: Google OAuth (UC-01 Path A) — separate follow-up.

Conflict status:
- C-26 CLOSED — Onboarding::createJobSeekerProfile() deleted; the barangay_id
  insert into job_seekers is gone.
- C-27 CLOSED — app/views/auth/onboarding.php (the seeker view reading an
  undefined $barangays) deleted.
- C-28 CLOSED — the surviving Onboarding model queries are lowercased
  (employers, users); no PascalCase table names remain in the onboarding path.
- C-29 CLOSED — /build-profile is the sole profile builder for BOTH roles.
  OnboardingController.php, app/models/Onboarding.php,
  app/views/auth/onboarding_employer.php and the GET/POST /onboarding routes
  (plus the index.php require) are deleted. Every prior /onboarding redirect
  now targets /build-profile: RoleController::redirectForRole(),
  AuthController::routeAfterAuth(), AuthGuard::requireActiveProfile() and
  ::requireCompleteEntity(). ProfileController::processEmployerUpdate() +
  Profile::createEmployerProfile()/saveEmployerProfile() replace
  Onboarding::createEmployerProfile(). Verified over HTTP: a fresh Pending
  employer signs in, the role picker lands on /build-profile (not
  /onboarding), the form renders, a POST with a real PDF permit inserts one
  employers row (verified_status 'Pending') and flips account_status to
  'Active', GET /onboarding returns 404, and no controller/model/view/route
  named onboarding remains (repo grep clean apart from historical mentions in
  docs and one "bypassed onboarding" code comment).
- C-32 PARTIAL — the dropped flat `barangays` table is no longer queried.
  Profile::getBarangays() reads lib_barangays (the v2 PSGC table) filtered by
  municipality_id and feeds BOTH builders' municipality→barangay cascade
  through the single /profile/barangays JSON route. Profile::resolveBarangay()
  now validates the employer's chosen barangay against the municipality on
  both create and edit — a cross-municipality barangay_id is stored NULL
  (verified). Full closure still tracked with the barangays-table drop
  verification.
- C-17 CLOSED — the Python service recomputes HMAC-SHA256 over the raw body
  and constant-time compares the signature AND the bearer token; either
  failure is a 401. Verified: tampered body, missing signature, wrong secret,
  missing/wrong bearer all return 401 and write no score.
- C-19 CLOSED — the x 0.40 cap is deleted. final_score = skill_score x
  geo_multiplier. Verified: a perfect match in the home municipality scores
  exactly 1.0000.
- C-20 CLOSED (all layers, Task 6 Commit 2) — every layer swept:
  * engine + DB enum: already clean (Mandatory / Preferred).
  * app/views/employer/post_job.php: the <option value="Optional"> is gone,
    and the JS that builds each skill row now emits only Mandatory / Preferred.
    No requirement_type option value 'Optional' remains in the page or its
    script (verified by an assertion narrowed to the requirement_type context;
    the unrelated "Salary Range (Optional)" label is left as it was).
  * app/controllers/JobController.php: requirement_type is whitelisted to
    ['Mandatory','Preferred'] — a submitted 'Optional' (or anything else) is a
    form error, no job row and no job_required_skills row. It also now enforces
    "at least one Mandatory" (UC-04 BR-3 / E1).
  * app/models/Job.php: the "Mandatory/Optional" comment is corrected.
  Verified over HTTP: a direct POST of requirement_type 'Optional' is rejected
  with HTTP 200 + form error and writes nothing.
- C-21 CLOSED — home_municipality_id IS NULL takes an explicit branch to
  geo_multiplier 1.00, geo_tier 'no_location_data', location_data_present
  false. Verified: NULL-location seeker gets 1.00, not 0.50, and final_score
  is not scaled down.
- C-22 CLOSED — the try/except fabricated-success payload and every hardcoded
  .get() default are gone. Failures are 401/422/500; PHP computeMatch() throws
  rather than returning 0.0; apply() blocks the application on failure instead
  of storing a fabricated 0 (UC-05 BR-7).
- C-23 CLOSED — raw_jaccard is computed and returned in diagnostics only; the
  'final = raw_jaccard' fallback is removed. Unit test asserts final_score ==
  round(skill_score x geo, 4) exactly.
- C-24 CLOSED — proficiency_level is no longer sent in the payload. It is a
  PHP feed-ordering tiebreaker, not an engine input. First real consumer
  shipped in Task 5: JobSeeker::getRecommendationFeed()'s correlated subquery
  sums the ordinal (Beginner 1 / Intermediate 2 / Expert 3) over the seeker's
  matched Mandatory skills for each job. It correlates on jp.job_id and binds
  the seeker id from its own placeholder — not s.jobseeker_id, which is NULL
  under the score LEFT JOIN for unscored jobs — so the tiebreaker still ranks
  jobs that have no score row yet.
- C-25 CLOSED — sikaphub_ai_engine/database.py deleted; mysql-connector /
  PyMySQL dropped from requirements; the service holds no DB connection.
- C-33 CLOSED — the debug ProfileController call
  triggerMatchComputation(1, $userId) is removed. T1 (batch score recompute)
  is the matching engine's responsibility, not the profile save path.
- C-35 CLOSED — the seeker dashboard's per-job HTTP loop is gone; the feed
  reads job_match_scores and makes zero calls to the matching service.
  Employer publish (T2) and profile save (T1) recompute in one batch request.
  Task 5 finished the read side: JobSeeker::getRecommendationFeed() is the
  §6.6 / D-14 dual-key sort — verified_status, then final_score (NULLs last),
  then the summed proficiency ordinal over matched Mandatory skills, then
  date_posted, then job_id — as a LEFT JOIN so unscored open jobs render as
  "match pending" (D-15), never a fabricated 0%. The controller adds exactly
  three flat supporting queries (approved skill set, one batched
  job_required_skills lookup, seeker context); the query budget is 4 and
  constant in card count, with zero per-card queries.
  Evidence (fixture scripts/seed_demo.php --scores, verified over HTTP through
  Apache): query count identical at 1 card and 6 (Com_select delta 4 both);
  no job_match_scores row created during GET /dashboard for any seeker,
  including the deliberately-unscored Andrea (stays at 0); the Verified tier
  outranks a pending-employer job scoring 1.0000 for the same seeker; the
  proficiency ordinal separates Maria's two 1.0000 Guimba jobs (5 vs 4,
  correct order); a total tie is byte-identical across two consecutive
  requests; Andrea sees all six open jobs as "Match pending" plus the
  "vacancies awaiting scoring" notice; Web Developer shows GraphQL as awaiting
  approval and excluded from preferred_total (2, not 3); Jayson (no home
  municipality) sees the neutral location nudge and every geo_multiplier row
  is still 1.00; a forced missing table returns HTTP 500 + errors/500 with no
  SQL leaked; the only requirement_type values in the schema are Mandatory /
  Preferred; the one seeded application (Maria → bookkeeper) renders that card
  in its Applied state while the other five keep a working Apply button, feed
  row count unchanged. 58 assertions across scripts/verify_feed.php (30) and
  scripts/http_verify.php (28), all passing; both are committed so the
  evidence is re-runnable at defense.
- C-42 CLOSED — AIEngineService filters both job_required_skills and
  jobseeker_skills to master_skills.status='approved' before the call.
  Verified: a job's pending Mandatory skill is excluded (mandatory_total 2,
  not 3).
- C-36 PARTIAL — auth state changes AND admin employer-verification decisions
  now write to audit_logs via app/helpers/Audit.php, whose signature matches
  the AuditService still to be built. Task 6 Commit 3 wired UC-03: an
  employer_verified / employer_rejected row on every decision (BR-4 — no
  unlogged verification actions), attributed to the admin's user_id, with the
  rejection row recording the suspended-posting count. Still PARTIAL: the
  other four UC-00c include sites (UC-09, UC-14, UC-15, UC-19) remain unwired,
  AuditService itself is not built, and the /admin/audit-logs VIEW is still a
  placeholder redirect (UC-18 has data now but nothing renders it).
- C-37 DEFERRED (Task 6 Commit 3) — peso_admins is seeded (one SuperAdmin row
  for user_id 1) and Admin::getAdminName() reads it, but access_level is still
  not ENFORCED: every admin can verify, reject, approve skills and export.
  UC-03 preconditions 2 and BR-5 ("Viewer may read the queue but not decide")
  are not honoured. Deferred because enforcement belongs in AuthGuard as a
  cross-cutting tier check (M2 §9 "enforced in AuthGuard, not merely stored")
  alongside the SuperAdmin-only user-promotion path, which is its own use case
  (UC-19) with no UI yet — building half of it now (a role gate with no
  screen to manage roles) would be a dead end. Tracked for the admin task
  that builds UC-18/UC-19.
- C-41 PARTIAL — job_preferences.desired_job_type / expected_salary are now
  stored with valid values (enum-whitelisted, empty → NULL not ''). They are
  still not read by anything until the matching engine or employer view uses
  them.
- C-38 CLOSED (code, Task 6 Commit 2) — the schema migration gave
  job_postings a real min_years_experience TINYINT UNSIGNED; the code caught
  up. Job::createJobPosting() writes it as an integer, the form is a
  type=number field (0-50, Q-17), and JobController::create() range-checks it
  server-side. Because the column is NOT NULL with default 0 and there is no
  sentinel for "unspecified", the field is required: a blank or non-numeric
  value is a form error, not a silent 0 — "0 years required" and "unstated"
  are different claims to an applicant. BR-4: displayed, not scored.
- C-39 PARTIAL — job_postings.work_arrangement (ENUM On-site/Remote/Hybrid) is
  now written on every publish, whitelisted in the controller, shown on
  job/show and already read by the seeker feed. It is still NOT an input to
  matching: the M2 §7.3 engine payload contract is locked and work_arrangement
  is not one of its fields (BR-5 — "displayed against the seeker's
  preferred_work_setup, not scored in version 1"). Closing C-39 fully would
  mean reopening that contract and the engine, which is out of scope. Column
  written and displayed; scoring deferred by design.
- C-44 PARTIAL — display_errors is off; the auth layer, the seeker profile
  save path and the seeker dashboard return real HTTP status codes
  (400/403/500 + app/views/errors/500) instead of die().
  AuthGuard::requireCompleteEntity() no longer die()s. Task 5 tightened the
  dashboard: JobSeekerController::dashboard() wraps every model call in
  try/catch and serves errors/500 on failure — a forced PDO error now renders
  the 500 view, not a blank page or a leaked stack trace. The employer
  save/post path, JobSeekerController::tracker() and a few other call sites
  still die().
- C-18 PARTIAL — the /test-ai route is deleted. display_errors is already off
  (Task 2). No unauthenticated route now calls the matching service.
- C-44 (further, Task 6 Commit A) — GET /admin/view-document no longer die()s:
  both exits are now http_response_code(403|404) + app/views/errors/500.
- C-44 (further, Task 6 Commit 1) — the employer profile-save path is off
  die(). ProfileController::processEmployerUpdate() replaces the two die()s
  (invalid upload / failed update) with a re-rendered builder carrying a
  validation message (HTTP 200) or app/views/errors/500 (HTTP 500);
  EmployerController::dashboard()'s "employer profile not found" die() now
  redirects to /build-profile (matching AuthGuard::requireCompleteEntity());
  the redundant manual CSRF check in EmployerController::reviewCandidate()
  (die on mismatch) is removed — the base Controller constructor already runs
  CSRF::verifyRequest() on every POST.
  Still on die(): CSRF::verifyRequest() itself, JobSeekerController::tracker(),
  app/core/Controller::view().
- C-44 (further, Task 6 Commit 2) — JobController::create() is off die().
  Its four die()s become: HTTP 403 + errors/500 (non-employer), a redirect to
  /build-profile (Active account, no employers row), a re-rendered post_job
  form with the validation message and submitted values (every input failure —
  no skills, bad experience, bad enum), and HTTP 500 + errors/500 (the
  transaction returned false).
- C-44 (further, Task 6 Commit 3) — AdminController is now entirely off die().
  verifyEmployer()'s silent fall-throughs are gone: an unknown status or bad
  id redirects to ?error=invalid_status, a non-admin gets HTTP 403 +
  errors/500, a failed write gets HTTP 500 + errors/500, and every success
  path ends in a redirect with a toast code. dashboard(), approveSkill(),
  exportPdf() and the four nav placeholders (employers/seekers/jobs/skills/
  auditLogs) replaced their die("Access Denied.") with the same 403 view.
  Still on die() (the remaining C-44 surface, both cross-cutting and out of
  scope for the admin task): app/helpers/CSRF.php CSRF::verifyRequest() — a
  token miss or mismatch dies with a plain-text "Security Violation" string
  instead of HTTP 403 + errors/500; and app/core/Controller::view() — a
  missing view template dies with "View does not exist." Also still on die():
  JobSeekerController::tracker() and AuthGuard::requireCompleteEntity()'s
  invalid-role branch (exit('Forbidden: invalid role.')).
- D-18 IMPLEMENTED (Task 6 Commit 3) — on employer rejection, every
  job_status='Open' posting by that employer moves to 'Suspended' in the same
  transaction as the verified_status change. Rejection SUSPENDS postings, it
  does not delete them — the rows and their job_required_skills survive and a
  future job-edit / un-suspend path can restore them. Supersedes UC-03
  Alternative Flow A1's original wording; UC-03 A1 and BR-2 in
  docs/USE_CASE_MODEL_and_SPECIFICATIONS.md were rewritten and D-18 is
  recorded in docs/AUDIT_PASS3-4_conflict_register_and_verdicts.md §11.
- C-50 CLOSED — IDOR on the document gateway (GET /admin/view-document),
  discovered and resolved during Task 6 implementation. It was authorized by
  requireLogin() alone and resolved ?file= by scanning three storage
  directories for any basename match, so any authenticated session could pull
  any business permit, resume or profile photo, and any file left in those
  directories was served whether or not a row referenced it. Now inverted
  (see the verification block below); 40-assertion HTTP evidence in
  scripts/verify_documents.php. Registered as C-50 in
  docs/AUDIT_PASS3-4_conflict_register_and_verdicts.md.
- C-51 CLOSED (Task 6 Commit 4) — schema-v2 mismatch on the employer "Review
  Candidate" page (UC-13). Employer::getApplicationDetails() selected
  js.resume_file and js.street_address; job_seekers has neither (street_name;
  resumes live in resume_uploads). The uncaught PDOException made
  GET /employer/review-candidate a 500 for every valid application.
  review_candidate.php read the same two phantom keys. Found during the Task 6
  inspection; previously carried as an unregistered blocker. Fixed: the query
  selects street_name and drops resume_file; Employer::getSeekerResume() reads
  the latest resume_uploads row (null -> honest empty state, UC-02 upload
  deferred); the view builds "street, municipality" from street_name +
  municipality_name skipping blanks, reads $resume, and drops the dead ?type=
  gateway param; EmployerController::reviewCandidate() wraps the path in
  try/catch -> errors/500 (C-44). The displayed score is unchanged — the
  frozen applications.ai_match_score (UC-05 A3). Registered as C-51 in
  docs/AUDIT_PASS3-4_conflict_register_and_verdicts.md §4. Evidence:
  scripts/verify_review_candidate.php, 36 HTTP assertions + the six prior
  harnesses re-run green.
- C-44 (further, Task 6 Commit 4) — EmployerController::reviewCandidate() is
  off the "blank 500" path: the getApplicationDetails / getSeekerResume /
  getSeekerEducation / getSeekerExperience calls and the POST status-update
  branch are wrapped in one try/catch that renders http_response_code(500) +
  errors/500 with an error_log line (same pattern as
  JobSeekerController::dashboard). The method had no die() of its own; the
  remaining C-44 surface (CSRF::verifyRequest(), Controller::view(),
  JobSeekerController::tracker(), AuthGuard invalid-role) is unchanged and
  still cross-cutting / out of scope.

Verification status for this change (Task 6 Commit A — document authorization; C-44, C-50):
- The document gateway (GET /admin/view-document, AdminController::viewDocument)
  was authorized by nothing but requireLogin() and scanned three directories
  for any basename match — any authenticated session could pull any business
  permit, resume or profile photo (M2 §9 "must be ownership/role-scoped";
  CLAUDE.md ownership-JOIN rule). Inverted: it now resolves the filename to an
  owning DB row first (app/models/Document.php — employers.business_permit_file,
  resume_uploads.stored_filename, job_seekers.profile_photo) and 403s any file
  no row references, then authorizes against the owner — admin: any; jobseeker:
  own uploads only; employer: only a document belonging to a seeker who applied
  to one of THAT employer's jobs, proven by applications -> job_postings ->
  employer_id; every non-admin request also requires users.account_status
  ='Active' (same authority as the feed). The served Content-Type is
  whitelisted to application/pdf | image/jpeg | image/png (403 otherwise) with
  X-Content-Type-Options: nosniff, and the path is built from a hardcoded dir
  + the DB-matched name (never the request value) behind a realpath fence.
- FileUpload::secureUpload() now derives the stored extension from the
  validated MIME type, not the user's filename (CLAUDE.md "never by
  extension"). extensionForMime() is fail-closed: an unmapped MIME type throws
  RuntimeException rather than guessing an extension from the string, so a
  caller that widens $allowedMimeTypes without adding the extension gets a
  hard error, not a silent bad name. Only callers are OnboardingController;
  neither reads the returned extension, and both stay on the default
  pdf/jpeg/png allow-list. Safe.
- New storage/.htaccess denies all direct HTTP access (Require all denied /
  Deny from all) — D.2 found the only thing protecting permits was the root
  mod_rewrite, which the production host may not run.
- Verified over HTTP through Apache: scripts/verify_documents.php, 40
  assertions, all passing (admin fetch OK; seeker/other-employer/suspended-
  employer/non-applicant all 403; unreferenced-file 403 with a byte-identical
  body to the deny case; path traversal 403 with no disclosure; logged-out
  302; direct /storage/ URL not served with AND without the root .htaccess;
  MIME-derived extension; unmapped MIME throws). The harness mutates no live
  row: it seeds a dedicated Verified employer whose users.account_status is
  Suspended (Andrea applied to its now-Closed vacancy) so the account_status
  gate is tested against a fixture, and it guards its .htaccess move-aside
  behind APP_ENV=development + PHP_SAPI=cli. scripts/seed_demo.php extended to
  drop real fixture files (a permit per employer, two resumes, a photo, one
  unreferenced file) and Andrea now applies to the Cabanatuan employer's job;
  cleaned on re-run; idempotent (file count stable across re-runs; pre-existing
  non-demo files untouched). verify_feed.php (30) and http_verify.php (28)
  still green.
- Route note (Q-19): /admin/view-document keeps its path this commit but now
  serves seeker- and employer-owned files too; a /document/view rename is
  deferred to the builder commit (touches 3 views).

Verification status for this change (Task 6 Commit 1 — employer builder + /onboarding purge; C-29, C-32, C-44):
- One route, ProfileController::buildProfile(), dispatches by session role.
  Employer POST: no employers row -> Profile::createEmployerProfile() (single
  transaction: INSERT employers with the identity fields + street_name +
  resolveBarangay()-validated barangay_id + municipality_id +
  business_permit_file + verified_status 'Pending', then UPDATE users
  account_status 'Active'); row present -> Profile::saveEmployerProfile()
  (UPDATE scoped WHERE user_id = session user, never a form field). Every
  column except business_permit_file is editable on re-edit, including
  company_name, street_name and municipality_id (Q-21 was reversed — see
  below); getEmployerProfile() pre-fills the builder.
- Q-21 (reversed) — company_name / street_name / municipality_id are editable
  on re-edit, not locked. saveEmployerProfile() re-validates barangay_id
  against the municipality being saved. When company_name changes on an
  employer whose verified_status is already 'Verified', an audit_logs row is
  written (action_type 'profile_updated' — there is no dedicated enum member;
  see Q-22). The method does NOT change verified_status.
- Q-22 (open, for the admin task) — should a Verified employer editing its
  registered company name drop back to verified_status 'Pending' for
  re-verification, and does audit_logs.action_type need a dedicated
  'employer_profile_changed' / 'employer_reverification_required' enum member?
  Deferred to UC-03 / UC-19. Today the change is logged and the badge is
  kept.
- Reuse, not duplication: Profile::getMunicipalities(), getBarangays(),
  resolveBarangay() and the /profile/barangays JSON route serve both builders.
  No second cascade, no second endpoint.
- Profile::saveEmployerProfile() no longer references website_url /
  facebook_url / linkedin_url / twitter_url (columns that do not exist in
  schema v2); it writes company_website. employer_builder.php rebuilt for the
  real columns (company_website, street_name) and for create-vs-edit.
- Permit upload lifted from the old OnboardingController into
  processEmployerUpdate(): FileUpload::secureUpload() into storage/documents/.
  business_permit_file is NOT NULL, so on CREATE a missing or rejected file is
  a form validation error (HTTP 200, builder re-rendered), never a DB error;
  on re-edit with no new file the stored value is kept; if the transaction
  fails after a successful upload the controller unlinks the file (verified: no
  orphan left in storage/documents/).
- Q-16: friendly "Company Size" labels ("11-50 Employees") map to the enum
  member ("11-50") in the controller; an unrecognised value maps to NULL,
  never a raw passthrough (verified: "Galaxy Class" -> company_size NULL, no
  PDOException).
- Seeker photo upload routed through FileUpload::secureUpload() (see Blockers).
- PURGE: OnboardingController.php, app/models/Onboarding.php,
  app/views/auth/onboarding_employer.php and the GET/POST /onboarding routes +
  index.php require deleted. All four /onboarding redirects repointed to
  /build-profile (RoleController:71, AuthController:225, AuthGuard:53 and :79).
  Repo grep for 'onboarding' outside docs: only JobController's "bypassed
  onboarding" comment remains (cosmetic). seeker_builder.php's heading now
  reads "Build Profile".
- Verified over HTTP through Apache: scripts/verify_employer_builder.php, 62
  assertions, all passing — fresh employer OTP-less flow (Pending user + crafted
  session) lands on /build-profile not /onboarding; form renders; POST with a
  real PDF permit -> one employers row, verified_status 'Pending',
  account_status 'Active', redirect to /employer/dashboard which shows the
  under-review banner; POST /post-job still refused by the gate; re-edit
  pre-fills, keeps business_permit_file, and can change company_name /
  street_name / municipality_id; a Verified-employer name change writes an
  audit_logs row and leaves verified_status alone (Q-22); cross-municipality
  barangay -> NULL; unknown company_size -> NULL; no-permit create -> HTTP 200
  form error, no row, no orphan; a bad municipality FK mid-transaction ->
  HTTP 500, no row, no orphan (no schema touched); GET/POST /onboarding -> 404;
  the new permit is fetchable by an admin through the Commit A gateway and 403
  to another employer; the seeker photo upload stores a valid image under a
  MIME-derived .png (uploaded as headshot.jpg) and rejects a text file named
  evil.jpg with HTTP 400. verify_documents.php (40), verify_feed.php (30) and
  http_verify.php (28) still green (asserted from inside the harness). The
  harness creates throwaway users, so it carries the same APP_ENV=development
  + PHP_SAPI=cli guard as verify_documents.php and self-cleans its
  @demo-eb.sikaphub.local fixtures (users + permit/photo files).

Verification status for this change (Task 6 Commit 2 — job-posting path vs schema v2; C-20, C-38, C-39, C-44, UC-05 step 15):
- Scope held: app/models/Job.php, app/controllers/JobController.php,
  app/views/employer/post_job.php, app/views/job/show.php (added to scope —
  needed to render province_name for step 15). The matching engine,
  AIEngineService, the seeker feed model and the admin path were not touched.
- Job::createJobPosting(): INSERT column list is now min_years_experience +
  work_arrangement + employment_type (+ the unchanged fields); the dropped
  required_experience column is gone. job_status stays hardcoded 'Open'
  (Q-14). catch is Throwable + inTransaction() guard.
- Job::getMunicipalities() deleted — its only caller (the post-job GET) now
  uses Profile::getMunicipalities(), which already carries the lib_provinces
  join. No third copy of that query.
- Job::getOpenJobDetails() joins lib_provinces for province_name (the field
  that 500'd job/show and blocked UC-05 step 15). job/show.php now shows
  location as "Municipality, Province" plus the real work_arrangement and
  min_years_experience. The met/missing skill list on the detail page itself
  is still a follow-up — the feed card already carries that breakdown (Task 5)
  and the blocker (the 500) is gone.
- JobController::create(): every enum whitelisted to the live members
  (employment_type = 4, work_arrangement = 3, requirement_type = 2);
  min_years_experience is ctype_digit + <= 50 or a form error; >= 1 Mandatory
  skill required (BR-3/E1). All four die()s replaced (see C-44 above). A
  validation failure re-renders post_job.php with $error and the submitted
  scalar values + skill rows (rebuilt in JS from window.OLD_SKILLS).
- post_job.php: 'Freelance' option removed; the skill-row JS emits only
  Mandatory / Preferred; the literal string "Optional" no longer appears
  anywhere in the page or its script; the unrelated "Salary Range (Optional)"
  label is unchanged. Municipality select grouped by province.
- Display: min_years_experience = 0 renders as "No experience required" (not
  "0") on job/show.php and on the seeker feed cards. The feed query
  (JobSeeker::getRecommendationFeed) now also selects jp.min_years_experience
  and the card shows an experience chip; this is a read-only display column,
  no new query, Com_select delta unchanged (verify_feed still 30/30).
- Seeker-builder copy audit (requested): the desired_job_type and
  preferred_work_setup fields in app/views/profile/seeker_builder.php carry
  plain labels ("Desired Job Type", "Preferred Work Setup") and no helper
  text — nothing implies they affect the recommendation score, so the copy is
  left as-is. This is correct: neither field is an input to matching
  (desired_job_type is not in the M2 §7.3 payload; work-setup matching is
  display-only per BR-5), and job_preferences is still read by nothing
  (C-41). The only scoring-related copy on that page — "new skills are
  reviewed by PESO before they count toward matches" — is about skills and is
  accurate.
- Verified over HTTP through Apache: scripts/verify_job_posting.php, 52
  assertions, all passing — the Verified fixture employer publishes through the
  real form (integer experience 3, work_arrangement 'Hybrid', job_status
  'Open', both skills Mandatory); T2 writes Maria's job_match_scores row with
  geo_multiplier 0.75 (same-province tier), skill_score 0.5000 (1 of 2
  Mandatory met) and final_score 0.3750 = skill x geo, and the vacancy shows
  "38%" in her feed (ROUND(0.375*100)); she opens it at GET /job/view -> 200
  with the province name (was a 500); a Pending employer is still bounced to
  ?error=pending_verification with no row; non-numeric / blank / >50 /
  fractional experience each -> HTTP 200 form error, no row, submitted title
  preserved; employment_type 'Freelance' by direct POST -> rejected, no row;
  requirement_type 'Optional' by direct POST -> rejected, no job and no
  job_required_skills row; a Preferred-only submission -> rejected (BR-3); a
  job carrying the seeded pending skill 'GraphQL' still inserts all three
  job_required_skills rows but Maria's score counts preferred_total = 1, not 2
  (C-42 regression). verify_employer_builder.php (62), verify_documents.php
  (40), verify_feed.php (30) and http_verify.php (28) still green, asserted
  from inside the harness after it deletes its own '[C2-verify]'-tagged jobs.

Verification status for this change (Task 6 Commit 3 — admin verification; C-36, C-37, C-44, D-18):
- Scope held: app/controllers/AdminController.php, app/models/Admin.php,
  app/views/admin/dashboard.php, plus docs. The document gateway
  (viewDocument/denyDocument), the employer builder, the job-posting path and
  the matching engine were not touched.
- Admin::updateEmployerVerification($employerId, $status) returns
  ['ok'=>true, 'employer_user_id'=>int, 'suspended'=>int] or false. It opens a
  transaction, SELECT ... FOR UPDATE on the employer row (E4 last-write-wins),
  UPDATE verified_status + verified_at = NOW(), and on 'Rejected' only UPDATE
  job_postings SET job_status='Suspended' WHERE employer_id=? AND
  job_status='Open' (rowCount is the suspended tally). catch is Throwable +
  inTransaction() guard; false is the only failure signal — no fabricated
  success. 'Verified' never touches job_status (BR-3 — status is read by join).
- verifyEmployer() maps: non-admin -> 403 + errors/500; non-POST -> redirect;
  employer_id <= 0 or status not in {Verified,Rejected} -> redirect
  ?error=invalid_status; model false -> 500 + errors/500; success -> Audit
  ::write(adminUserId, 'employer_verified'|'employer_rejected', ..., 'employer',
  employerId) then enqueueNotification() then redirect ?success=verified|
  rejected. The rejection audit description carries the suspended count.
- enqueueNotification() is a private direct INSERT into notifications
  (event_type employer_verified|employer_rejected, entity_type 'employer',
  title <= 150, body <= 500). Wrapped in try/catch Throwable -> error_log,
  swallowed. It runs AFTER the transaction has committed, so a failure cannot
  roll the decision back.
- getPendingSkills() LEFT JOINs users and aliases u.email AS suggested_by (the
  view reads $ps['suggested_by']; the query previously selected only
  submitted_by_user_id, so the column was always "Unknown User"). LEFT, not
  INNER — submitted_by_user_id is nullable (FK SET NULL).
- getAdminName($userId) reads peso_admins.admin_name, falls back to
  'PESO Admin'. dashboard() passes it as $admin_name.
- dashboard.php: the permit link now reads $emp['business_permit_file'] (was
  $emp['business_permit'] — a column that does not exist, so href was
  ?file= with an empty value and the admin could not open the document they
  were meant to verify). The docblock names and a new 'invalid_status' toast
  message were updated.
- Verified over HTTP through Apache: scripts/verify_admin_verification.php, 48
  assertions (47 + one that is SKIPPED, never passed, when the mod_php
  error_log target is not resolvable from CLI — set SIKAP_ERROR_LOG to assert
  it; here it resolves to C:/xampp/apache/logs/error.log), all passing — the
  seeded Pending employer's permit link is
  populated and opens 200 as application/pdf through the Commit A gateway;
  approve -> Verified + verified_at + exactly one employer_verified audit row
  + exactly one employer_verified notification for the employer's user, and
  that employer can then POST /post-job (gate open); a throwaway employer with
  two Open jobs in Maria's feed is rejected -> Rejected + verified_at + both
  jobs Suspended + one employer_rejected audit row containing "2" + one
  notification + Maria's feed row count drops by exactly 2 and neither job
  reappears; an employer whose user_id does not exist (FK violation forces the
  notification INSERT to fail) is still rejected, verified_at set, D-18
  suspension applied, audit row written, NO notification row, and
  "[notify] ... failed" is in the Apache error log; status 'Banana' -> 302
  ?error=invalid_status with no state change and no audit/notification rows;
  a seeker POST -> 403 + errors view, no change; an admin POST with a wrong
  CSRF token is refused by the base Controller, no change. After tearing down
  its fixtures the harness asserts no '[C3-verify]' employer, no @demo-av user,
  and — crucially — no employers row with a dangling user_id anywhere in the
  table (so a crashed run cannot strand an invalid row in the admin pending
  list) and no employer-orphaned job_postings row. Then verify_job_posting
  (55), verify_employer_builder (62), verify_documents (40), verify_feed (30)
  and http_verify (28) all still green, asserted from inside the harness.
  The harness leaves its employer_verified/employer_rejected audit_logs rows
  in place (an audit trail is immutable; same posture as
  verify_employer_builder), so a dev DB accumulates a few test rows per run
  until the next reseed.
- Known gaps recorded (not built this commit):
  * Nothing renders the notifications table. No NotificationService, no
    in-app inbox / bell, no unread count. The row written on a verification
    decision is the durable record UC-03 step 11 calls for; the employer is
    not shown it anywhere yet. First consumer lands with C-36's
    NotificationService.
  * No verification email. Mailer is OTP-only (sendOtp() with a hardcoded
    template/subject); there is no generic send method and no
    employer_verified template. email_log itself works as CLAUDE.md
    describes. Wiring the email is deferred to the NotificationService task.
  * Approving a pending master_skill does not recompute any scores. approveSkill()
    calls Admin::updateSkillStatus() and stops — no T7, no AIEngineService
    call. Because C-42 filters scoring to status='approved' skills, an
    approval silently changes nobody's matches until the next unrelated
    T1/T2. M2 §9 names TRIGGER T7 for this; T7 is unbuilt (it exists only as
    a comment in JobSeeker.php). Tracked for the matching/analytics task.

Verification status for this change (Task 6 Commit 4 — Review Candidate off HTTP 500; C-51, C-44):
- Scope held: app/models/Employer.php, app/controllers/EmployerController.php,
  app/views/employer/review_candidate.php, scripts/seed_demo.php (manifest
  only), plus docs. The matching engine, AIEngineService, the document gateway
  (AdminController::viewDocument / Document model), the seeker feed and the
  admin path were not touched.
- Employer::getApplicationDetails(): SELECT list now has js.street_name and no
  js.resume_file; the ownership JOIN through job_postings.employer_id is
  unchanged (CLAUDE.md reference pattern). a.ai_match_score carries a comment
  that it is the FROZEN application-time value (UC-05 A3) and must not become a
  job_match_scores join.
- Employer::getSeekerResume($jobseekerId): new. Returns the latest
  resume_uploads row (stored_filename, original_filename, mime_type) or null,
  ORDER BY created_at DESC, upload_id DESC LIMIT 1. Ownership is already
  established by the caller (getApplicationDetails proved the seeker applied to
  one of this employer's vacancies — the same condition the document gateway
  re-checks), so the résumé link resolves for the legitimate employer and
  403s everyone else.
- review_candidate.php: location is built from street_name + municipality_name,
  blanks skipped, "Not provided" when both empty (Maria's seeded row has an
  empty street_name -> renders "Guimba"). The résumé block reads $resume: a
  gateway link on stored_filename when a row exists, else the empty state
  "No résumé on file. Review this applicant from the profile details below."
  — no roadmap/"release" language. Photo and résumé links drop the dead
  ?type= param (the 7d44b12 gateway ignores it and resolves ?file= itself).
- EmployerController::reviewCandidate(): getApplicationDetails + the POST
  status-update branch + getSeekerResume / getSeekerEducation /
  getSeekerExperience are inside one try { } catch (Throwable) that renders
  http_response_code(500) + errors/500 and writes an error_log line. No die()
  existed on this method.
- updateApplicationStatus(): verified working, left as-is. Ownership is
  enforced twice — the controller's getApplicationDetails IDOR pre-check
  (redirect on null) AND the UPDATE's own JOIN on jp.employer_id. A
  cross-employer POST is firewalled before the write; the redundant-update
  guard means a call that reaches the model always changes exactly one owned
  row.
- Score on the page = applications.ai_match_score, unchanged. Confirmed it was
  already correct (not a job_match_scores read) and proven frozen by a
  throwaway employer/seeker/application whose ai_match_score (0.7300) and a
  divergent job_match_scores.final_score (0.1100) disagree: the page shows
  73%, never 11%.
- seed_demo.php: each applications INSERT now captures lastInsertId into the
  manifest (applications.*.application_id). No behavioural change; the six
  prior harnesses re-run green after the manifest addition.
- Verified over HTTP through Apache: scripts/verify_review_candidate.php, 36
  assertions, all passing — the Guimba employer opens Maria's bookkeeper
  application at HTTP 200 (was 500) with her name, "Guimba" and the frozen
  60% rendered; with a resume_uploads row present the résumé section emits a
  gateway link that returns 200 + PDF for that employer; with the row deleted
  the honest empty state shows and no download link is emitted; the row is
  restored byte-for-byte (same stored_filename) so verify_documents stays
  valid; the Cabanatuan employer requesting the same app_id gets a 302 to the
  dashboard with none of Maria's details in the body; Maria's photo link is
  200 for Guimba and 403 for Cabanatuan through the gateway; a status update
  from the page moves Pending -> Reviewed for the owning employer while a
  cross-employer POST redirects and changes nothing; the frozen-score proof
  passes on the throwaway rows (73%, not 11%) and the throwaway rows self-clean.
  Then verify_documents (40), verify_feed (30), http_verify (28),
  verify_job_posting (55), verify_employer_builder (62) and
  verify_admin_verification (47 + 1 SKIP) all still green, asserted from
  inside the harness. The harness restores Maria's resume_uploads row via a
  shutdown hook so a crash mid-run cannot leave the fixture corrupted, and it
  carries the same CLI + APP_ENV=development guard as the others; its
  throwaway rows use @demo-rc.sikaphub.local / '[RC-verify]'.
- Read-only finding (requested), now acted on: JobSeeker::saveCompleteProfile()
  — dead code that wrote the phantom job_seekers.resume_file and INSERTed
  unknown skills under a hardcoded category_id 7 (Information Technology, which
  the locked taxonomy decision forbids) — was confirmed genuinely unreachable.
  The string "saveCompleteProfile" appeared only at its own definition and in
  this document; no route, controller, view, JS, service or internal JobSeeker
  method called it. The sole seeker profile-write path is POST /build-profile
  -> ProfileController::processJobSeekerUpdate ->
  Profile::saveCompleteSeekerProfile(), a different class that files unknown
  skills under 'Uncategorised' (Task 3). DELETED in Task 6 Commit 5.

Verification status for this change (Task 3 — seeker profile layer vs schema v2):
- Full column-by-column audit of every query in app/models/Profile.php, plus
  ProfileController.php and app/views/profile/seeker_builder.php, against the
  live job_seekers, education, work_experience, jobseeker_skills,
  preferred_work_locations, job_preferences and lib_* tables.
- Verified end to end over HTTP against the running Apache: fresh seeker signs
  up via OTP → picks jobseeker → GET /build-profile renders (was a 500) →
  POST a complete profile → 302 /dashboard → dashboard renders with no bounce
  back to the builder. 35 automated assertions, all passing, plus edge cases
  (invalid barangay for the chosen municipality → NULL; bogus visibility →
  Public; Freelance/empty enum values → NULL not ''; "no experience declared"
  still satisfies the completeness signal; re-edit pre-fills all three steps).
- Confirmed on the live DB: users.account_status flips to 'Active' inside the
  save transaction; one row lands in each of job_seekers (with barangay_id and
  profile_completeness), education, work_experience, jobseeker_skills,
  preferred_work_locations and job_preferences; work_experience dates store as
  YYYY-MM-01 rather than 0000-00-00.

What Task 3 reconciled:
- Profile::getMunicipalities() joins lib_provinces for province_name.
- saveCompleteSeekerProfile() drops the phantom job_seekers.resume_file, fixes
  the job_preferences existence check (preference_id → jobseeker_id), writes
  barangay_id (validated against the chosen municipality), normalises
  type=month dates, whitelists the profile_visibility / desired_job_type /
  preferred_work_setup enums, and computes profile_completeness (M2 §6.6 — the
  fourth feed-ordering tiebreaker, previously always 0).
- getSeekerProfile() now returns the full profile graph so the builder
  pre-fills steps 2 and 3 on re-edit.
- Seeker-proposed skills are created as status='pending' under the new
  'Uncategorised' skill_categories row (added to docs/schema_v2_migration.sql
  and the live DB) with submitted_by_user_id recorded (UC-02 A3) — never
  silently filed under Information Technology, which would corrupt the sector
  analytics feeding Chapter IV.
- ProfileController's die() calls on the seeker path and the resume-upload
  block (which targeted the dropped resume_file column) are removed.

Verification status for this change (Task 4 — matching engine rebuild):
- Engine repo (../sikaphub_ai_engine): full rewrite of main.py and
  matching_engine.py; database.py deleted. 30 pytest cases (pure function +
  API): 2:1 weighting, four geo tiers, NULL-location neutrality, jaccard not
  in the score, no cap, zero-weight rejection, HMAC/bearer 401s (tampered
  body, missing signature, wrong secret, missing/wrong bearer), 422s,
  compute-batch order + per-element error, GET /health liveness.
- PHP <-> engine end to end (engine on :8000, seeded data): computeMatch
  returns the contract shape; recomputeForSeeker writes job_match_scores rows
  with engine_version '2.0.0' and final_score == skill_score x geo; a wrong
  HMAC secret makes computeMatch throw and writes no row.
- C-19 / C-20 / C-21 closure evidence (Chapter IV material) captured in the
  harness output: perfect same-municipality match = final_score 1.0000;
  Preferred-only job = skill_score 0.5000; NULL home municipality =
  geo_multiplier 1.00 / geo_tier no_location_data / final_score == skill_score.

Payload contract (M2 §7.3, now written into the spec):
- Request: job_id, jobseeker_id, job_required_skills[{skill_id,
  requirement_type}], seeker_skill_ids[int], job_municipality_id,
  job_province_id, seeker_home_municipality_id, seeker_home_province_id,
  seeker_preferred_municipality_ids[int]. Both skill sides pre-filtered to
  approved; province resolved by PHP; home municipality/province both-or-
  neither; no proficiency_level.
- Response: skill_score, geo_multiplier, final_score, raw_jaccard,
  engine_version, diagnostics{mandatory_met/total, preferred_met/total,
  location_data_present, geo_tier}. compute-batch returns an array in order;
  an unscorable element is {job_id, jobseeker_id, error} and PHP writes no row.
- engine_version travels in the response and PHP stores it verbatim;
  'x0.40'-era rows would read engine_version LIKE '1.%'.

Removed in the auth rebuild (Task 2):
- User::register(), User::login(), the /register route, POST /login,
  app/views/auth/register.php, and the password form. There is no separate
  registration — the first successful email verification creates the account.
- The orphaned doctest employer user (user_id 2, 0 job_postings) and its
  cascaded employers row.

Blockers (pre-existing):
- Seeker photo upload magic-byte validation — CLEARED (Task 6 Commit 1).
  ProfileController::processJobSeekerUpdate() now routes the profile photo
  through FileUpload::secureUpload() (allow-list image/jpeg|png|webp, 2 MB),
  the same pipeline as every other upload; a rejected file returns HTTP 400 +
  errors/500, and the inline uniqid()/pathinfo() logic is gone. This is the
  write-side half of what Commit A fixed on the read side. The employer logo
  upload in the same controller was moved onto secureUpload() at the same time.
- Q-14 — DEVIATION FROM M2 §8, recorded (not an omission). §8 has a Pending
  employer able to DRAFT jobs before verification; the job path ships without
  it. job_status is hardcoded 'Open' in Job::createJobPosting(); a new employer
  cannot reach /post-job at all (JobController's verified_status gate
  redirects). job_postings.job_status already carries a 'Draft' member for the
  future path.
- No job-EDIT route. M2 §7.1 defines T2 as fires on "publishes OR updates a
  job" and UC-04 A2 ("edit a published vacancy") expects a re-fire of T2 with
  job_match_scores overwritten. Neither exists: there is no GET/POST route, no
  Job::updateJobPosting(), and no UI to edit a posting. An employer who
  mistypes a vacancy cannot correct it. Not built in Commit 2 — the "or
  updates" half of T2 has no caller today.
- Rejected-then-approved employer keeps Suspended postings (Task 6 Commit 3,
  D-18). Rejection sets Open postings to job_status='Suspended'; a later
  approval sets verified_status='Verified' but deliberately does NOT touch
  job_status (approval is a pure status change, BR-3). With no job-edit /
  un-suspend route, those postings stay Suspended and out of every feed — the
  employer has to re-create them. Auto-restoring on approval was rejected on
  purpose: it would collide with a future per-job admin suspension (M2 §9
  /admin/jobs "suspend if needed") — approval must not silently un-suspend a
  posting an admin suspended for cause. This is a dead end an employer can
  reach; the fix is the job-edit route above (let the employer re-open their
  own suspended postings), not a blanket restore.
- Sibling schema-v2 mismatches still OUTSIDE the tasks done so far:
  JobSeeker::saveCompleteProfile() (dead, resume_file + category_id=7) —
  CONFIRMED unreachable in Task 6 Commit 4 (no route/controller/view/JS/caller
  reached it; the live seeker save path is Profile::saveCompleteSeekerProfile,
  which files unknown skills under 'Uncategorised') and DELETED in Task 6
  Commit 5. The Employer.php / review_candidate.php
  resume_file + street_address mismatch was FIXED in Task 6 Commit 4 (C-51):
  getApplicationDetails() selects street_name, the résumé comes from
  resume_uploads via Employer::getSeekerResume(), and the view reads
  street_name / $resume. The job-posting-path mismatches
  (Job::createJobPosting required_experience, employment_type 'Freelance',
  Job::getMunicipalities province_name gap, Job::getOpenJobDetails
  province_name) were fixed in Task 6 Commit 2 — Job::getMunicipalities() is
  deleted and the post-job form calls Profile::getMunicipalities().
- M2 §6.3 geo multiplier RESOLVED — updated to the four-tier version
  (1.00 / 0.90 / 0.75 / 0.50, neutral 1.00) per decision D-12.
- Deployment blocker — 101 hardcoded `/sikaphub/` URL prefixes across
  app/ and public/ (redirects, form actions, view links, including the
  seeker dashboard and errors/500). Production is not served from a
  `/sikaphub/` path; these need a single base-URL helper before deploy.
  Not touched in Task 5 — it is a cross-cutting change, not feed work.
- T1 / T2 / T7 recompute failures (AIEngineService) are logged via error_log
  only. Once C-36's AuditService lands, a failed batch recompute should also
  write an audit_logs entry so a silent scoring outage is visible in
  /admin/audit-logs. Task 5 added an aggregate error_log line in
  writeScores() recording the count and job ids of skipped unscorable pairs
  as a stopgap.

Open Questions: None
