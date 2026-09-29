# S.I.K.A.P. Hub — Audit Pass 3 & Pass 4

**Conflict Register and Layer Verdicts**

| | |
|---|---|
| Version | v1 |
| Date | 21 August 2026 |
| Sources audited | DB schema (2026-08-19), PHP digest (2026-08-19), Python digest (2026-08-19), Research paper (2026-07-29), Legacy diagrams (2026-03-14) |
| Status | DRAFT — pending the open items in Section 5 |

> This document supersedes nothing yet. It becomes the project baseline only after the open items in Section 5 are answered and the verdicts in Section 4 are accepted.

---

## 1. How to read this register

Every conflict has an ID you can refer to later ("C-19 is fixed"). Severity is assigned by **consequence, not by effort** — some blockers are ten-minute fixes and some debts are week-long jobs.

| Tier | Meaning |
|---|---|
| **BLOCKER** | Stops a submission, a defense, or the evaluation itself. Must be resolved before the capstone can pass. |
| **BREAK** | The system does not do what it claims to do. Visible to a user or a panelist who clicks around. |
| **DEBT** | Real problem, no immediate consequence. Fix when convenient or accept and document. |

Each row also carries a **Fix size**: S (under an hour), M (a day), L (multiple days), XL (a week or more).

---

## 2. Conflict Register — BLOCKERS

### 2.1 Research paper

| ID | Conflict | Evidence | Fix size |
|---|---|---|---|
| **C-01** | The paper describes a different system than the one built. Zero occurrences of Python, FastAPI, microservice, API, Jaccard, weighted, HMAC, Tailwind, or 3NF across 102 pages. Scope section states the stack as "HTML, CSS, JavaScript, PHP, MySQL." | Full-text search of `04_research_paper_ch1_to_ch3.pdf` | XL |
| **C-02** | Chapters I–III appear twice in the same PDF. The "102-page paper" is ~51 pages printed twice. | Chapter III begins at two separate points in the extracted text | S |
| **C-03** | No objective covers the matching algorithm. All three objectives assess the system via QuIDTech-CaPE. The core technical contribution has no stated objective, so nothing in the study validates it. | Statement of the Objectives, items 1–3 | M |
| **C-04** | Chapter III has no **Statistical Treatment of Data** section. Survey responses cannot be legitimately analyzed without one declared in advance. | Ch III section list | M |
| **C-05** | Chapter III has no **Data Gathering Procedure** section. | Ch III section list | M |
| **C-06** | Chapter III has no **System Architecture / System Development** section and no Hardware & Software Requirements. This is precisely where the decoupled architecture belongs. | Ch III section list | L |
| **C-07** | Sampling method contradicts itself: purposive sampling with inclusion criteria, followed immediately by a sample-size calculator at 95% confidence / 5% margin of error. Margin of error presupposes probability sampling. | Ch III, Respondents | M |
| **C-08** | Table 1 arithmetic is wrong three ways. Population column sums to 83, table says 56. Target column sums to 60, table says 53. Percentages sum to 100.10 and correspond to neither column. | Ch III, Table 1 | S |
| **C-09** | Zero survey data collected. Chapters IV and V do not exist. The QuIDTech-CaPE instrument is written but never administered. | Confirmed by proponent | XL |
| **C-10** | Project title contains no reference to AI or intelligent matching, while the project is presented as an AI-powered job matching portal. Requires an explicit decision, not a silent inconsistency. | Title page | S (decision) / M (if changed) |

### 2.2 Technical diagrams — blocks Priority 1 submission

| ID | Conflict | Evidence | Fix size |
|---|---|---|---|
| **C-11** | ERD models the abandoned barangay architecture. 14 entities vs 17 live tables. Missing entirely: `lib_municipalities`, `preferred_work_locations`, `job_preferences`. | Diagram §1.4 vs schema dump | L |
| **C-12** | DFDs show match calculation as internal processes 3.1–3.4. No external entity or separate process represents the Python service. The diagrams hide the project's single most defensible engineering decision. | Diagram §3.1–3.3 | L |
| **C-13** | Data Dictionary column names contradict the live schema: `importance_level`→`requirement_type`, `skill_level`→`proficiency_level`, `Audit_Logs.admin_id`→`user_id`. `ai_match_score`, `required_experience`, `updated_at`, `master_skills.status`, `profile_visibility`, and all employer branding fields are absent. | Diagram §2 vs schema dump | L |
| **C-14** | Gantt chart ends 30 April 2026 and states "Current Phase: Mid-Development (March 2026)." Today is 21 August 2026 — roughly four months past the plan. Sprint 4 reads "Program the PHP Skill-Based Matching Algorithm"; the algorithm is Python. | Diagram §7 | M |
| **C-15** | 2NF section asserts "All tables currently satisfy 2NF… Schema remains identical to 1NF" with no justification. Panels routinely challenge unjustified normalization steps. | Diagram §1.3 | S |
| **C-16** | Wireframes promise match scores of 92%, 88%, and 85%. The engine caps every score at 40% (see C-19). The UI mockup and the system cannot both be true. | Diagram §6, Screen 3 vs `matching_engine.py` | S (after C-19) |

### 2.3 Security claims vs. security reality

| ID | Conflict | Evidence | Fix size |
|---|---|---|---|
| **C-17** | HMAC-SHA256 request signing is declared a project non-negotiable and is implemented on the PHP side, but `verify_bearer_and_hmac()` returns `True` on every path. It reads the body and discards it. It never computes or compares a signature. It returns `True` even with no Authorization header. Directly falsifies the "Security and Trust" construct the study measures. | `main.py`, `verify_bearer_and_hmac` | M |
| **C-18** | `/test-ai` is a live, unauthenticated public route that calls the AI engine and dumps the raw response via `print_r`. `display_errors = 1` is set in the front controller. Both are exploitable and both contradict the stated security posture. | `public/index.php` | S |

---

## 3. Conflict Register — BREAKS

### 3.1 Matching engine

| ID | Conflict | Evidence | Fix size |
|---|---|---|---|
| **C-19** | The `* 0.40` multiplier caps every possible score at 40%. A candidate meeting 100% of mandatory skills in the exact job municipality scores 0.40. No score above 40% is arithmetically reachable. | `matching_engine.py`, final return | S |
| **C-20** | The engine filters `requirement_type == 'Optional'`. The database enum is `('Mandatory','Preferred')`. `optional_requirements` is therefore always empty, and Preferred skills are dropped from both numerator and denominator — they have zero effect on any score. The Pydantic comment `# 'Mandatory' or 'Optional'` is where the mismatch is recorded. | `main.py` + `matching_engine.py` vs schema | S |
| **C-21** | Seekers with `home_municipality_id = NULL` always receive the 0.50 geographic penalty: the first branch fails on `None`, and `None in pref_locs` is `False`, so control falls to `else`. Combined with C-19 this caps those users at 20%. Affects jobseekers 1, 2, 3, 4, 6. | `matching_engine.py`, geo block | S |
| **C-22** | `compute_match` wraps everything in `try/except` and returns `{"status": "success", "weighted_skill_score": 0.85, "raw_jaccard_score": 0.75}` on *any* exception. The success path also uses hardcoded `.get()` defaults. A broken engine and a working engine produce identical output. This is why the municipality migration failure was invisible. | `main.py`, exception handler | M |
| **C-23** | `raw_jaccard` is computed and returned but never used in the final score. The reported "Jaccard similarity" is decorative. | `matching_engine.py` | S (decision) |
| **C-24** | `proficiency_level` is collected from every job seeker and transmitted in the payload but never read by the scoring function. Skill depth has no effect on ranking. | `jobseeker_skills` + `matching_engine.py` | M |
| **C-25** | `database.py` builds a MySQL connection pool that `main.py` never imports. Dead code that misrepresents the service as stateful. | `database.py` | S |

### 3.2 Onboarding and location migration

| ID | Conflict | Evidence | Fix size |
|---|---|---|---|
| **C-26** | `Onboarding::createJobSeekerProfile()` inserts `barangay_id` into `job_seekers`, which has no such column. The insert throws, the transaction rolls back, and the user sees "Error saving profile." | `app/models/Onboarding.php` vs schema | S |
| **C-27** | `OnboardingController::index()` passes `municipalities` to the view; `auth/onboarding.php` reads `$barangays`, which nothing sets. | Controller vs view | S |
| **C-28** | `Onboarding.php` queries `Job_Seekers`, `Users`, and `Barangays` in PascalCase while the rest of the codebase uses lowercase. On Linux — the stated production target — these resolve as different tables and fail. | `app/models/Onboarding.php` | S |
| **C-29** | Two competing profile-creation paths are both routed and live: the legacy `OnboardingController` (barangay era) and `ProfileController::buildProfile()` (municipality era). | `public/index.php` routes | M |
| **C-30** | Employers 1, 2, and 3 hold `municipality_id = 0`. No such municipality exists. The foreign key was applied after the rows existed with checks disabled. Any location JOIN silently drops these employers. | `employers` INSERT rows | S |
| **C-31** | `job_required_skills` row 2 holds `requirement_type = ''` — the MySQL enum error value in a NOT NULL column. | `job_required_skills` INSERT | S |
| **C-32** | The `barangays` table (64 rows plus sentinel `9999`) has no foreign key from anywhere, yet `Onboarding::getBarangays()` still queries it. An orphan table kept alive by dead code. | Schema + `Onboarding.php` | S |
| **C-33** | `ProfileController` calls `triggerMatchComputation(1, $userId)` — a hardcoded job ID, and a `user_id` passed where a `jobseeker_id` is expected. The result is discarded. Leftover debug code. | `app/controllers/ProfileController.php` | S |

### 3.3 Data model and performance

| ID | Conflict | Evidence | Fix size |
|---|---|---|---|
| **C-34** | `applications.ai_match_score` is `decimal(5,2)`. The engine returns four decimal places. 0.0850 stores as 0.09. Enough truncation to scramble rankings when scores cluster — which they will, given C-19. | Schema vs `matching_engine.py` | S |
| **C-35** | The job seeker dashboard fires one HTTP request per open job in a loop — labelled "The N+1 AI Trigger Loop" in the source. Directly attacks the "System Reliability and Performance" construct being measured. | `JobSeekerController::dashboard()` | L |
| **C-36** | Nothing anywhere writes to `audit_logs`. The table is empty, the `/admin/audit-logs` route exists, and "View System Audit Logs" is a use case in the diagram. The QuIDTech-CaPE instrument asks IT professionals to verify audit trails (Item 18). | Codebase-wide search | M |
| **C-37** | `peso_admins` is empty. Admin identity rests entirely on `users.role = 'admin'`, assigned manually in the database. `access_level` (SuperAdmin/Moderator/Viewer) is modelled but unused. | Schema + `TECH_DEBT.md` | M |
| **C-38** | `job_postings.required_experience` is free-text `varchar` holding values like `'2 years'` and `'1'`. Not comparable, not filterable, not usable in matching. | `job_postings` INSERT rows | M |
| **C-39** | Work arrangement is asymmetric. Seekers declare `preferred_work_setup` (Remote/On-site/Hybrid) in `job_preferences`; job postings have only `employment_type` (Full-time/Part-time/…). The employer wireframe shows a Work Arrangement selector with no column behind it. Nothing can be matched on it. | Schema vs Diagram §6 Screen 4 | M |
| **C-40** | The skill-profile wireframe shows a per-skill "Years of Experience: Entry Level / 1–3 / 3+" control. No column exists to store it. | Diagram §6 Screen 2 vs schema | S (decision) |
| **C-41** | `job_preferences.desired_job_type` and `expected_salary` are collected from every seeker and never read. | Codebase-wide search | S |
| **C-42** | Skills with `status = 'pending'` (SQL, Web Developer, Dermatologist) are already attached to job seekers and enter matching unreviewed. The approval workflow does not gate participation in scoring. | `master_skills` + `jobseeker_skills` | M |

---

## 4. Conflict Register — DEBT

| ID | Conflict | Fix size |
|---|---|---|
| **C-43** | `Router.php` supports exact-string matching only. No URL parameters, no middleware, no route groups. Every resource ID travels as a query string. | L |
| **C-44** | Authentication and authorization failures terminate via `die()` with a plain-text string rather than a proper HTTP response or error view. | M |
| **C-45** | The `.env` parser does `explode('=', $line, 2)` with no guard. Malformed lines raise notices; quoted values retain their quotes. | S |
| **C-46** | `preferred_work_locations` has a composite primary key but no foreign key constraints to `job_seekers` or `lib_municipalities`. | S |
| **C-47** | `lib_municipalities` holds 5 rows with no "Other / Outside" option. The abandoned `barangays` table had sentinel row 9999 for exactly this case. | S |
| **C-48** | Admin dashboard code and labels mix vocabulary: `getSeekersByMunicipality()` feeds a table headed "Registered seekers by barangay", and the view docblock still names `barangay_name`. | S |
| **C-49** | Both uploaded digests are duplicated end-to-end (GitIngest run twice). Cosmetic, but it doubled context cost. | S |
| **C-50** | IDOR on the document gateway. `GET /admin/view-document` (`AdminController::viewDocument`) was authorized by `requireLogin()` alone and resolved `?file=` by scanning three storage directories for any basename match — any authenticated session (any seeker, any employer) could pull any business permit, resume or profile photo, and any file dropped in those directories was served whether or not a row referenced it. Discovered and resolved during Task 6 implementation. **CLOSED** — the gateway now resolves the filename to an owning DB row first (new `Document` model), 403s anything unreferenced, then authorizes against the owner (admin: any; jobseeker: own uploads; employer: only an applicant's document via `applications → job_postings → employer_id`, and only while `account_status = 'Active'`); served Content-Type whitelisted to pdf/jpeg/png with `nosniff`; path built from a hardcoded dir + the DB-matched name behind a `realpath` fence. Evidence: `scripts/verify_documents.php`, 40 HTTP assertions. | M |
| **C-51** | Schema-v2 mismatch on the employer "Review Candidate" page (UC-13, `GET /employer/review-candidate`). `Employer::getApplicationDetails()` selected `js.resume_file` and `js.street_address`; `job_seekers` has neither column (the address column is `street_name`; resumes live in the `resume_uploads` table). With `ATTR_EMULATE_PREPARES = false` the prepare threw an uncaught `PDOException`, so the page returned **HTTP 500 for every valid application** — the dashboard listed applicants fine, clicking one died. The view (`app/views/employer/review_candidate.php`) read the same two phantom keys. Found during the Task 6 inspection; it was carried as an unregistered blocker in `PROJECT_STATE.md`. **CLOSED** — the query now selects `street_name` and drops `resume_file`; the résumé is sourced from `resume_uploads` via a new `Employer::getSeekerResume()` (honest "No résumé on file" empty state where no row exists, since UC-02 résumé upload is deferred); the controller wraps the path in `try/catch → errors/500` (C-44); the score shown remains the frozen `applications.ai_match_score` (UC-05 A3), never a live `job_match_scores` read. Evidence: `scripts/verify_review_candidate.php`, 36 HTTP assertions + the six prior harnesses re-run green. | S |

---

## 5. Open items blocking the baseline

These must be answered before this document can be locked.

| # | Item | Why it blocks |
|---|---|---|
| **O-01** | **Priority 1 deadline.** Still unanswered across two rounds. | Everything in the plan sequences off this date. |
| **O-02** | **The adviser's new per-diagram requirements** posted in Google Classroom, plus the system manual specification. | C-11 through C-16 cannot be resolved against the March rubric if the rubric has changed. Recreating diagrams to the wrong spec wastes the same week twice. |
| **O-03** | **The RESUME READER assets** — `train_model.py` at minimum, plus what corpus the model was trained on, what it classifies, and what accuracy the adviser reported. | Determines whether this is a genuine AI contribution or an unfalsifiable black box. See Section 7. |
| **O-04** | **Confirm whether the legacy onboarding route is reachable.** Routes for `/onboarding` remain registered even though the flow was bypassed. | Determines whether C-26 is live-breaking or merely dead code. |
| **O-05** | **Remaining calendar.** Final defense date, chapter submission dates, and how many weeks of development time actually remain. | Section 6 sequencing depends on it. |

---

## 6. Pass 4 — Layer Verdicts

Verdict scale: **KEEP** (sound as-is) · **REFACTOR** (structure survives, internals change) · **REDESIGN** (model changes, data survives) · **REBUILD** (start over) · **REWRITE** (documents).

### Layer A — Database → **REDESIGN**

**Keep:** the table decomposition, the bridge tables, the composite unique constraints, InnoDB + utf8mb4, the foreign key discipline, the index choices. This is the strongest technical asset in the project and it is genuinely 3NF where it matters.

**Redesign:** the location model end-to-end. Decide once whether the system is municipality-scoped or municipality-plus-barangay, then migrate cleanly rather than patching. Resolve C-30, C-31, C-32, C-46, C-47 in one migration.

**Change:** `ai_match_score` to `decimal(6,4)` (C-34). Give `required_experience` a real type (C-38). Add a job-side work-arrangement column or delete the seeker-side one (C-39).

**Decide and then either use or drop:** `proficiency_level`, `job_preferences.*`, `peso_admins.access_level`, `master_skills.status` gating. Each is either a feature you implement or a column you remove. Carrying unused columns into a defense invites the question "why is this here?" with no good answer.

**Do not rebuild.** There is no structural reason to start over, and you would lose your normalization narrative.

### Layer B — PHP MVC → **REFACTOR** *(explicitly not a rebuild)*

**This is the answer to your repository-restart question: no.**

The security layer is the most defensible thing you have built and the hardest to reproduce under time pressure. Global CSRF interception in the base `Controller` constructor, `hash_equals()` constant-time comparison, three-tier `AuthGuard`, magic-byte MIME validation with randomized filenames, and an ownership-enforcing JOIN in `getRankedApplicantsForJob()` with a comment explaining the IDOR it prevents. You will be examined on exactly these things under the Security and Trust construct, and you can defend all of them because you wrote them.

Throwing that away to escape a broken onboarding controller and three debug lines would be trading your strongest asset for your weakest problem.

**Refactor work, in order:**
1. Delete the legacy onboarding path entirely — controller, model, view, and routes (C-26, C-27, C-28, C-29, C-32).
2. Remove `/test-ai`, set `display_errors = 0`, harden the `.env` parser (C-18, C-45).
3. Delete the orphaned `triggerMatchComputation(1, $userId)` call (C-33).
4. Replace the N+1 loop with a single batch call once the engine supports it (C-35).
5. Implement audit logging, since the instrument asks about it (C-36).
6. Router parameters and proper error responses last — real debt, no deadline pressure (C-43, C-44).

### Layer C — AI Engine → **REBUILD**

Roughly 200 lines across three files, and every layer of it is either stubbed, wrong, or dead: the auth guard is a no-op, the exception handler fabricates success, the scoring constant is arbitrary, the enum string does not exist in your database, and the connection pool is never imported.

This is the **cheapest rebuild in the project** and the one with the highest return. It is a weekend of work, not a sprint.

**Non-negotiables for the rebuild:**
- **Real HMAC verification.** Recompute the signature over the raw body, compare with `hmac.compare_digest`, and return HTTP 401 on mismatch. PHP is already signing correctly — the receiving end simply has to check.
- **Fail loudly.** Return proper 4xx/5xx codes. A fabricated success is worse than an error, because it hides the failure you need to see.
- **Read the enum from the database contract, not from a comment.** Use `Mandatory` / `Preferred`.
- **Derive the weighting, don't assert it.** Whatever multiplier you choose, you must be able to say in a defense *why* it is that number. "0.40 because a formula we never finished" is not survivable.
- **Handle NULL location explicitly** rather than letting it fall through to the penalty branch.
- **Batch endpoint** so one seeker's dashboard is one request.

### Layer D — Research Paper → **REWRITE Ch III · REVISE Ch I · KEEP Ch II**

| Chapter | Verdict | Work |
|---|---|---|
| Ch I | **REVISE** | Scope and Delimitation must state the real stack (C-01). Add an objective covering the matching model (C-03). Decide the title question (C-10). Definition of Terms needs the new vocabulary. |
| Ch II | **KEEP, mostly** | The RRL is topically sound and largely independent of your architecture. Add literature supporting your chosen matching method — this becomes the justification the panel will ask for. |
| Ch III | **REWRITE** | Add Statistical Treatment, Data Gathering Procedure, System Architecture, and Hardware/Software Requirements (C-04, C-05, C-06). Resolve the sampling contradiction (C-07). Fix Table 1 (C-08). |
| Ch IV–V | **CREATE** | Blocked on survey data (C-09). |
| Whole file | **DE-DUPLICATE** | Immediately (C-02). |

**Coordination note.** The root cause here is stated plainly in your own account: the paper was drafted separately by groupmates with no technical coordination. Fixing C-01 through C-08 without fixing that process guarantees the drift returns. Whatever the group arrangement, one person needs to own the correspondence between the code and the document — and if that is you, the writers need the finalized architecture in a form they can copy from, not a conversation to interpret.

### Layer E — Technical Diagrams → **RECREATE FROM SCRATCH**

Nothing is salvageable except the entity list as a starting point, and even that is missing three tables and misnames four columns.

**Order matters, and it is not the order the assignment lists them in.** Diagrams derive from the system flow; if you draw them before the flow is locked you will redraw them.

1. **Lock the system flow first** — the single most important unbuilt artifact in this project.
2. ERD (0NF → 3NF), with the 2NF step actually justified (C-15).
3. Data Dictionary generated from the live schema after the Layer A redesign.
4. Context Diagram and Level 0 — **showing the Python engine as a distinct process or external entity** (C-12).
5. Level 1 low-level DFD for the matching subprocess.
6. Use-Case Diagram, with every use case traceable to a real route.
7. Wireframes, with realistic match percentages (C-16) and no controls that lack storage (C-40).
8. Gantt chart rebuilt from today forward (C-14).

---

## 7. The RESUME READER decision

You mentioned a machine-learning asset from your adviser — `train_model.py`, `vectorizer.pkl`, `model.pkl` — that you plan to integrate.

I have not seen these files, so this is a caution rather than a verdict.

**The case for it.** It would make "AI-powered" a defensible claim rather than a marketing word. A TF-IDF vectorizer plus a trained classifier is a real, explainable, defensible technique at capstone level, and it answers the question your current architecture cannot: *what makes this AI?*

**The case against it, honestly stated.** A pickled model you did not train is a black box you will be asked to explain. Panels ask: what corpus was it trained on, how many samples, what accuracy, what are the classes, why this algorithm, what happens on Filipino resumes with local school names and Taglish job titles. If the answer to any of those is "our adviser gave it to us," it becomes a liability rather than an asset.

**There is also a scope question.** You currently have a broken engine, a paper that describes the wrong system, zero survey data, and a stale diagram set. Adding a resume-parsing ML pipeline is a substantial new subsystem — file upload handling, text extraction, vectorization, inference, and a way to reconcile classifier output with the skill-tag data you already collect.

**Three viable paths:**

| Path | What it means | Cost |
|---|---|---|
| **A — Integrate fully** | Resume upload feeds the classifier; extracted skills merge with declared skills; both feed matching. | XL. Only viable with substantial time remaining. |
| **B — Integrate narrowly** | Classifier suggests skill tags during profile building; the seeker confirms them. Matching still runs on confirmed structured data. | M. Real AI, contained blast radius, human-in-the-loop is defensible. |
| **C — Defer and document** | Finish the weighted matching model properly; cite the resume reader as future work in Ch V. | S. |

**My recommendation, pending O-05:** Path B, if and only if you can retrain the model yourself from a corpus you can describe. If you cannot retrain it, Path C. A well-justified weighted matching model that you fully understand will defend better than a borrowed classifier you cannot explain.

Send `train_model.py` and I will give you a real verdict instead of a caution.

---

## 8. Critical path

The long pole is not the code. It is the **survey**.

```
Lock system flow
   ↓
Fix engine (C-19,20,21,22) + onboarding (C-26..C-29)
   ↓
System stable end-to-end
   ↓
Distribute QuIDTech-CaPE survey  ← 60 respondents, real calendar time
   ↓
Chapters IV & V
   ↓
Final defense
```

Everything else runs in parallel:

- **Diagrams** depend on the locked flow, not on working code. They can be recreated while the engine is being rebuilt.
- **Chapter I and III rewrites** depend on the locked flow and the finalized architecture, not on a running system.
- **Chapter II** needs nothing. It can be improved today.

**The practical implication:** the fixes that unblock the survey (C-19, C-20, C-21, C-22, C-26 through C-29) total roughly one focused week. The survey then takes as long as it takes, and it cannot be compressed by working harder. Everything you do before the survey goes out is on the critical path; everything after is parallelizable.

Do not spend the next three weeks rewriting Chapter III while the system stays broken.

---

## 9. Summary verdict

**This project needs a targeted rebuild, not an overhaul.**

| Layer | Verdict |
|---|---|
| Database | REDESIGN (location model + type corrections) |
| PHP MVC | REFACTOR (keep the security layer — it is the strongest asset) |
| AI Engine | REBUILD (~200 lines, highest return per hour in the project) |
| Research Paper | REWRITE Ch III · REVISE Ch I · KEEP Ch II · CREATE Ch IV–V |
| Diagrams | RECREATE FROM SCRATCH, after the flow is locked |

**Do not restart the repository.** The accumulated debt is concentrated in two small, well-identified places: the Python engine and the abandoned onboarding path. Both are surgically removable. The rest of the codebase is defensible work that you wrote and understand, which is exactly what a capstone defense tests.

**The single most important unbuilt artifact is the finalized system flow.** Six of the eight required diagrams derive from it, both chapter rewrites reference it, and the engine rebuild needs it to define the payload contract. That is the next milestone.

---

## 10. Next milestone

**M2 — Locked System Flow.**

Entry condition: open items O-01 through O-05 answered.

Deliverable: one agreed document describing, end to end, what a job seeker, an employer, and a PESO admin each do in the final system — every screen, every decision point, every place the AI engine is invoked — with the resume-reader decision resolved.

Nothing else should start until M2 is signed off.

---

## 11. Decisions taken during Phase 4 implementation

| ID | Decision | Rationale |
|---|---|---|
| **D-18** | On employer **rejection** (`verified_status = 'Rejected'`), every `job_status = 'Open'` posting by that employer is set to `job_status = 'Suspended'` in the same transaction as the status change. The rejection audit entry records the count. Approval never touches `job_status`. | A rejected business must not keep live vacancies in seeker feeds wearing the same amber badge as a not-yet-reviewed employer. Supersedes UC-03 Alternative Flow A1's original "existing postings retain the amber badge"; UC-03 A1 and BR-2 in `USE_CASE_MODEL_and_SPECIFICATIONS.md` were rewritten to match. Suspension is reversible (rows are not deleted); a rejected-then-approved employer's postings stay Suspended because approval does not restore `job_status` and no job-edit route exists yet — a known dead end, recorded in `PROJECT_STATE.md`. |
