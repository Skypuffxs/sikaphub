# M2 — Locked System Flow Specification

**S.I.K.A.P. Hub · Smart Job Portal Connecting Skills and Opportunities**

| | |
|---|---|
| Version | v1 — DRAFT, pending sign-off on Section 10 |
| Date | 21 August 2026 |
| Supersedes | Assignment No. 11 diagrams (2026-03-14), all prior flow descriptions |
| Derives | 8 required diagrams · Ch I Scope · Ch III System Architecture · engine rebuild contract |

> Once signed off, this is the **single source of truth**. If a diagram, a chapter, or a line of code disagrees with this document, the document wins until it is formally revised.

---

## 1. Scope decisions — locked

### 1.1 IN scope

- Email + password authentication with three roles
- Job seeker profile building via two paths (resume-assisted or manual)
- Employer job posting with structured mandatory/preferred skill requirements
- Deterministic weighted matching with geographic proximity
- Job seeker recommendation dashboard (ranked)
- Employer applicant ranking (ATS view)
- PESO admin verification, oversight, reporting, and audit trail
- Municipality-level geography via `lib_municipalities`

### 1.2 OUT of scope — deferred with reasons

| Item | Decision | Reason |
|---|---|---|
| **Google OAuth** | **DEFERRED** | New scope introduced after audit. Requires a public callback URL; you are on localhost until final presentation. No database columns exist. Not in the paper or any diagram. Costs 3–5 days you do not have before the survey. Email + password already works. |
| **Email verification** | **DEFERRED to v1.1** | `users` has `reset_token` but no `email_verified` column. Admin approval of employers already provides the trust gate. Revisit only if time remains after the survey is out. |
| **ML resume classifier in the live path** | **REJECTED** | See Section 4. Measured at chance performance. Retained as a documented research finding, not as a running component. |
| **Barangay-level geography** | **REMOVED** | Municipality level is the locked granularity. `barangays` table is dropped. |
| **Salary-based matching** | **OUT** | `expected_salary` and `salary_range` are display-only in v1. |
| **VPS deployment** | **Final presentation only** | Development and the survey run on localhost / LAN. |

---

## 2. Actors

| Actor | Identity | Created by |
|---|---|---|
| **Job Seeker** | `users.role='jobseeker'` + `job_seekers` row | Self-registration |
| **Employer** | `users.role='employer'` + `employers` row | Self-registration, then PESO verification |
| **PESO Admin** | `users.role='admin'` + `peso_admins` row | Seeded / promoted by SuperAdmin |
| **AI Matching Service** | Internal system actor, not a person | FastAPI process at `127.0.0.1:8000` |

**Diagram note:** the AI Matching Service appears in the Context Diagram as a **distinct subsystem inside the system boundary**, connected to the web application by a signed internal interface. It is not an external entity (it is yours, not a third party's), and it is not a plain process (it is a separate deployable). Label it *"AI Matching Service (internal microservice)"* and show the trust boundary. This is the requirement your adviser called out explicitly.

---

## 3. Account lifecycle and global rules

### 3.1 Account states

```
REGISTERED (users.account_status='Pending')
    │  role-specific profile completed
    ▼
ACTIVE (account_status='Active')
    │
    ├─ Employer only: verified_status Pending → Verified | Rejected
    │
    └─ Admin action → 'Suspended' | 'Deactivated'
```

**Employer gate.** An employer may register, complete a profile, and upload a business permit while `verified_status='Pending'`. They **cannot publish a job** until a PESO admin sets `Verified`. Draft jobs are allowed; publishing is blocked.

**Job seeker gate.** A seeker becomes `Active` on completing the profile builder. There is no admin approval step for seekers.

### 3.2 Rules that apply everywhere

| Rule | Implementation |
|---|---|
| Every POST is CSRF-verified | Base `Controller` constructor — already built, keep |
| Every protected route calls an `AuthGuard` tier | Already built, keep |
| Every resource fetch by ID is ownership-scoped | JOIN pattern in `getRankedApplicantsForJob()` — extend to all |
| Every state change writes to `audit_logs` | **NEW — resolves C-36** |
| Every DB write uses PDO prepared statements | Already true, keep |
| Failures return proper HTTP status, never `die()` | **CHANGE — resolves C-44** |
| One onboarding path only | `/build-profile`. `/onboarding` purged (C-26…C-29) |

---

## 4. The resume reader — locked decision

### 4.1 What was measured

The adviser's `train_model.py` pipeline was reproduced against the supplied `dataset.csv` (82 rows, balanced 41/41, reduced to 76 after the length filter).

| Metric | Result |
|---|---|
| 5-fold cross-validated accuracy | **52.83%** |
| Held-out accuracy, averaged over 30 random splits | **46.88%** (range 31%–69%) |
| Majority-class baseline | 51.32% |
| Chance | 50.00% |

The model does not outperform guessing.

### 4.2 Root cause

`clean_text()` applies `re.sub(r'[^a-z\s]', ' ', text)`, which removes every digit. The labels are determined by years of experience, internship count, and CGPA — all numeric. After cleaning, every row collapses to the form `"candidate with <skills> years experience internships cgpa"`. The features that determine the label are deleted before vectorization. The classifier is asked to infer experience level from a skill list that does not encode it.

Secondary issues: the corpus is synthetic template text averaging 15 words per record, not real resumes; the resulting vocabulary is 89 n-grams drawn from a fixed 10-token skill list; and `resume_reader_app.py`'s own training mode requires ≥30 words, which every row in the shipped dataset fails.

### 4.3 Fairness defect

`is_non_it()` returns REJECT for any document lacking `python`, `java`, `sql`, `machine learning`, `data`, `programming`, `developer`, or `api`. `penalize_non_it()` deducts score for `customer service`, `waiter`, `receptionist`, `hospitality`, `tourism`, `cashier`.

S.I.K.A.P. Hub serves a municipal PESO whose database defines Healthcare, Education, Retail & E-Commerce, Manufacturing, and Finance skill categories. Under this logic a nurse, a teacher, a store clerk, or a beautician receives an automatic rejection. This directly contradicts the SDG 10 alignment asserted in Chapter I and the stated aim of reducing unfair hiring.

### 4.4 What is retained

| Component | Verdict |
|---|---|
| `extract_text()` — PyPDF2 / python-docx | **KEEP.** Genuinely useful, no accuracy claim attached. |
| TF-IDF + Logistic Regression classifier | **REJECT from live path.** Retain as documented research. |
| `boost_score()` keyword list | **REJECT.** Six of 24 entries are permanently unmatchable (`HTML`, `CSS`, `Figma`, `Cisco` never match lowercased text; `c++` is destroyed by the regex; `javascritp` is a typo). Replaced by `master_skills` lookup. |
| `is_non_it()` / `penalize_non_it()` | **REJECT.** Fairness defect above. |
| ACCEPT / REVIEW / REJECT gating | **REJECT.** Automated rejection is out of scope for a PESO referral platform. |

### 4.5 How this becomes an asset

Write it up in **Chapter III, System Development** and reference it in **Chapter V**:

> An adviser-supplied TF-IDF + Logistic Regression resume screening model was evaluated for integration. Five-fold cross-validated accuracy was 52.83% against a 51.32% majority-class baseline; held-out accuracy averaged 46.88% across 30 randomized splits. Diagnosis attributed the failure to the preprocessing stage, which removes numeric tokens — the features (years of experience, internship count, CGPA) that determine the target label. An additional domain filter was found to automatically reject non-IT applicants, which is incompatible with a municipal employment platform serving multiple industries. The model was therefore excluded from the matching pipeline, and a deterministic weighted matching algorithm was adopted instead. Resume text extraction was retained for profile pre-population with user confirmation.

A measured, diagnosed, documented negative result demonstrates engineering judgment. It is stronger than an unexplained black box, and it converts a liability into a Chapter III contribution.

---

## 5. Job Seeker journey

### 5.1 Registration → Active

```
/register  →  users row (role='jobseeker', status='Pending')
                        │
/build-profile  ←───────┘   AuthGuard::requireLogin() traps here until complete
        │
        ├─ PATH A: upload resume (PDF/DOCX)
        │       └→ POST /api/v1/extract-skills
        │              └→ returns SUGGESTED skill_ids + confidence
        │                     └→ form pre-populated, tags marked "suggested"
        │
        └─ PATH B: manual entry (Tom Select tags)
                        │
        ┌───────────────┘
        ▼
  Seeker CONFIRMS every field  ← mandatory in both paths
        │
        ▼
  saveCompleteSeekerProfile() [single transaction]
        │
        ▼
  users.account_status = 'Active'
        │
        ▼
  TRIGGER T1: batch recompute this seeker × all open jobs
        │
        ▼
  Redirect → /dashboard
```

**Path A detail.** The uploaded file is stored via the existing `FileUpload::secureUpload()` (magic-byte validation, randomized filename — keep as-is). Text is extracted, lowercased, and matched against `master_skills.skill_name` where `status='approved'`. Matches become **suggested tags**, visually distinct from confirmed ones. Nothing is saved to `jobseeker_skills` until the seeker confirms.

**Why confirmation is mandatory.** It makes the feature defensible without any accuracy claim, keeps a human in the loop, and means a missed or wrong extraction degrades to manual entry rather than to bad data. Path A is a convenience layer over Path B, never a replacement.

**Both paths converge.** The profile is the digital resume. A seeker who used Path B has an equally complete profile.

### 5.2 Dashboard — recommendations

```
GET /dashboard
    │
    ├─ AuthGuard::requireActiveProfile()
    │
    ├─ READ cached scores from job_match_scores   ← NO live AI call
    │
    ├─ ORDER BY  (verified_status = 'Verified') DESC,
    │            final_score DESC (NULLs last),
    │            summed proficiency ordinal over matched Mandatory skills DESC,
    │            date_posted DESC, job_id ASC
    │
    └─ Render ranked job cards with match %
```

The ORDER BY is the §6.6 dual key and its tiebreakers (decision **D-14**,
which supersedes the earlier single-key form here). `weighted_score` was a
stale name for `final_score` — there is one score column and it is
`final_score`. §6.6 keys 4 and 5 (`profile_completeness`, `jobseeker_id`) are
constant across a single seeker's feed and cannot reorder it, so the query
omits them; they are inert, not dropped. An open job with no score row is
kept (LEFT JOIN), sorted last, and labelled "Match pending" — never 0%
(**D-15**).

**This resolves C-35.** The dashboard performs zero HTTP calls to the AI service. Scores are precomputed by triggers T1 and T2 and read from cache. Page load is one round trip whose result set is bounded by the number of open vacancies.

Cards show: job title, employer, municipality, employment type, match percentage, matched/unmatched skill tags, and an Apply button. If a seeker's profile lacks a home municipality, an inline prompt to complete it is shown — **not a score penalty** (see 6.3).

### 5.3 Apply

```
POST /apply  (CSRF-verified)
    │
    ├─ Duplicate check via unique_application constraint
    │
    ├─ TRIGGER T4: authoritative single recompute (live call, not cache)
    │
    ├─ INSERT applications (…, ai_match_score = authoritative value)
    │
    ├─ audit_logs write
    │
    └─ Redirect → /my-applications
```

The score stored on the application is a **point-in-time capture**. It never changes afterward, even if the seeker later adds skills. This is what an employer sees, and it is what makes the ATS ranking auditable.

### 5.4 Track applications

`GET /my-applications` — reads `applications` joined to `job_postings`. Shows status (Pending / Reviewed / Accepted / Rejected), the captured match score, application date, and employer feedback when present. Read-only.

---

## 6. Matching model — locked

### 6.1 Formula

```
skill_score = (2 × mandatory_met + 1 × preferred_met)
              ─────────────────────────────────────────
              (2 × |mandatory|  + 1 × |preferred|)

final_score = skill_score × geo_multiplier          ∈ [0.0, 1.0]

display     = round(final_score × 100)              ∈ [0, 100]
```

**The `× 0.40` constant is removed (C-19).** A perfect match now scores 1.0 and displays as 100%, which is what your wireframes promise and what a user expects.

**The enum is `Mandatory` / `Preferred` (C-20)** — read from the database contract, not from a code comment. `Optional` does not exist anywhere in this system.

### 6.2 Justification you must be able to give

The 2:1 weighting is a design choice, so state it as one and defend it:

> Mandatory requirements are weighted twice preferred requirements because an unmet mandatory requirement disqualifies a candidate in practice, whereas an unmet preferred requirement reduces suitability without disqualifying. A 2:1 ratio was selected as the minimum weighting that preserves strict ordering — a candidate meeting all mandatory and no preferred requirements always outranks one meeting all preferred and no mandatory requirements, for any job with at least one of each.

Verify that claim holds for your data and report a short sensitivity check (scores at 1.5:1, 2:1, 3:1) in Chapter III. That converts an arbitrary constant into a justified parameter — exactly what the panel will probe.

### 6.3 Geographic multiplier

Four tiers (decision **D-12**). The province tier is what justifies carrying the
full PSGC hierarchy; PHP resolves municipality → province from
`lib_municipalities` before the call (DFD M4). Precedence is top to bottom.

| Condition | Multiplier | `geo_tier` |
|---|---|---|
| Job municipality = seeker's home municipality | **1.00** | `home_municipality` |
| Job municipality ∈ seeker's preferred municipalities | **0.90** | `preferred_municipality` |
| Job province = seeker's home province (different municipality) | **0.75** | `same_province` |
| Different province | **0.50** | `different_province` |
| **Seeker has no home location at all** | **1.00 (neutral)** | `no_location_data` |

**The last row resolves C-21.** Missing data is not evidence of poor fit.
Penalising an incomplete profile produces a misleading score and pushes the
user away from the exact action that would fix it. The engine takes an
explicit `home_municipality_id is None` branch — it never falls through to a
penalty. The response carries `diagnostics.location_data_present = false`; the
UI prompts the seeker to complete the profile, and profile-completeness is
reported in Chapter IV.

### 6.4 Pending skills

Skills with `master_skills.status='pending'` are **stored but excluded from matching** until a PESO admin approves them (C-42). The seeker sees an "awaiting approval" badge. This gives the admin approval workflow real consequence and prevents unvetted vocabulary from affecting rankings.

### 6.5 Jaccard

`raw_jaccard` is retained as a **reported diagnostic**, not a score component (C-23). It is returned in diagnostics, stored in the cache table, and used in Chapter IV to compare the naive set-overlap baseline against the weighted model. That comparison is a genuine result worth having.

### 6.6 Profile completeness — the fourth feed-ordering tiebreaker

The seeker feed orders by `employers.verified_status` first, then `final_score`,
then the summed proficiency ordinal over matched mandatory skills, then
**`job_seekers.profile_completeness`**, then `jobseeker_id`. The fourth key is
only defensible if it is a documented function, not a magic number.

This five-key chain is the general ranking rule; keys 4 and 5
(`profile_completeness`, `jobseeker_id`) are constant across a single seeker's
own feed and cannot reorder it, so the dashboard query in §5.2 omits them as
inert per **D-14** — they remain in force wherever seekers are ranked against
each other.

`profile_completeness` is an integer 0–100, written by
`Profile::saveCompleteSeekerProfile()` inside the save transaction:

```
profile_completeness = round( signals_met / 7 × 100 )
```

The seven signals, equally weighted:

| # | Signal | Met when |
|---|---|---|
| 1 | Name | `first_name` and `last_name` both non-empty |
| 2 | Home municipality | `home_municipality_id` is set |
| 3 | Preferred work location | ≥ 1 row in `preferred_work_locations` |
| 4 | Education | ≥ 1 row in `education` |
| 5 | Work experience resolved | ≥ 1 row in `work_experience` **or** the seeker declared "no experience" |
| 6 | Skills | ≥ 1 row in `jobseeker_skills` |
| 7 | Desired job type | `job_preferences.desired_job_type` is set |

**Profile photo is deliberately excluded.** A photo has no bearing on
employability, and letting it lift feed rank would be a fairness defect in a
system whose Chapter I cites SDG 10. Signal 3 (preferred work location) takes
its place because that field actually feeds the §6.3 geographic multiplier.

The signal set is defined once as `Profile::COMPLETENESS_SIGNALS` and
`Profile::computeCompleteness()`; changing the weighting means changing both
this table and that method together.

---

## 7. AI service contract

### 7.1 Trigger points

| ID | Event | Call | Writes to |
|---|---|---|---|
| **T1** | Seeker completes or updates profile | `POST /api/v1/compute-batch` — this seeker × all open jobs | `job_match_scores` |
| **T2** | Employer publishes or updates a job | `POST /api/v1/compute-batch` — this job × all active seekers | `job_match_scores` |
| **T3** | Seeker opens dashboard | **none** — cache read | — |
| **T4** | Seeker submits an application | `POST /api/v1/compute-match` — authoritative single | `applications.ai_match_score` |
| **T5** | Employer opens applicant ranking | **none** — reads stored point-in-time scores | — |
| **T6** | Seeker uploads a resume (Path A) | `POST /api/v1/extract-skills` | nothing — returns suggestions only |
| **T7** | Admin approves a pending skill | `POST /api/v1/compute-batch` for affected seekers | `job_match_scores` |

Only T4 is on a user's blocking path, and it is one request. Everything a user waits for reads from cache.

### 7.2 New table — resolves C-35

The live table (authoritative — this block matches the migration):

```sql
CREATE TABLE job_match_scores (
  job_id            INT NOT NULL,
  jobseeker_id      INT NOT NULL,
  skill_score       DECIMAL(6,4) NOT NULL,   -- (2·mandatory_met + preferred_met) / (2·mandatory_total + preferred_total)
  geo_multiplier    DECIMAL(3,2) NOT NULL,   -- 1.00 / 0.90 / 0.75 / 0.50, or 1.00 neutral
  final_score       DECIMAL(6,4) NOT NULL,   -- skill_score × geo_multiplier
  raw_jaccard       DECIMAL(6,4) NOT NULL,   -- diagnostic only
  mandatory_met     SMALLINT UNSIGNED NOT NULL,
  mandatory_total   SMALLINT UNSIGNED NOT NULL,
  preferred_met     SMALLINT UNSIGNED NOT NULL,
  preferred_total   SMALLINT UNSIGNED NOT NULL,
  engine_version    VARCHAR(20) NOT NULL,    -- the value emitted by the engine, e.g. "2.0.0"
  computed_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                      ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (job_id, jobseeker_id),
  KEY idx_seeker_rank (jobseeker_id, final_score),
  KEY idx_job_rank    (job_id, final_score),
  FOREIGN KEY (job_id) REFERENCES job_postings(job_id) ON DELETE CASCADE,
  FOREIGN KEY (jobseeker_id) REFERENCES job_seekers(jobseeker_id) ON DELETE CASCADE
);
```

`DECIMAL(6,4)` matches the engine's four-decimal output and resolves C-34.
`applications.ai_match_score` is `DECIMAL(6,4)` for the same reason.
`engine_version` is written verbatim from the engine's response so a scoring
change (formula, weighting, or geo tiers) makes old rows identifiable —
`engine_version LIKE '1.%'` is the pre-rebuild engine.

### 7.3 Endpoints

**`POST /api/v1/compute-match`** — one authoritative pair (T4). **`POST /api/v1/compute-batch`** — a JSON array of the same request object, returning a JSON array of results in the same order (T1, T2, T7). Both require `x-signature` (HMAC-SHA256 of the raw body under `HMAC_SECRET`, lowercase hex) and `Authorization: Bearer <API_KEY>`; either missing or wrong → **401**, nothing computed. Malformed input → **422**.

**Request** (one pair):

```json
{
  "job_id": 42,
  "jobseeker_id": 15,
  "job_required_skills": [
    {"skill_id": 3, "requirement_type": "Mandatory"},
    {"skill_id": 7, "requirement_type": "Preferred"}
  ],
  "seeker_skill_ids": [3, 9, 12],
  "job_municipality_id": 14,
  "job_province_id": 1,
  "seeker_home_municipality_id": 14,
  "seeker_home_province_id": 1,
  "seeker_preferred_municipality_ids": [14, 2]
}
```

- All `skill_id`s on both sides are pre-filtered by PHP to `master_skills.status = 'approved'` (C-42). `requirement_type` is `Mandatory` or `Preferred` — `Optional` does not exist (C-20).
- `job_municipality_id` / `job_province_id` are always present; PHP resolves the province from `lib_municipalities` (DFD M4).
- `seeker_home_municipality_id` and `seeker_home_province_id` are **both set or both null**. Both null → the neutral geo tier (C-21).
- `proficiency_level` is **not** in the payload — it is a PHP feed-ordering tiebreaker, not an engine input (C-24).

**Response** (one pair; each `compute-batch` element has the same shape):

```json
{
  "job_id": 42, "jobseeker_id": 15,
  "skill_score": 0.6000,
  "geo_multiplier": 1.00,
  "final_score": 0.6000,
  "raw_jaccard": 0.4000,
  "engine_version": "2.0.0",
  "diagnostics": {
    "mandatory_met": 1, "mandatory_total": 2,
    "preferred_met": 1, "preferred_total": 1,
    "location_data_present": true,
    "geo_tier": "home_municipality"
  }
}
```

`final_score = skill_score × geo_multiplier`, range 0.0000–1.0000, **no cap** (C-19). `raw_jaccard` is a returned diagnostic and is never a term in `final_score` (C-23).

**`compute-batch` per-element error.** A pair that cannot be scored (e.g. every required skill is still `pending`, so nothing scorable remains after the approved filter) is returned as `{ "job_id": …, "jobseeker_id": …, "error": "…" }` — HTTP stays 200, and PHP writes no `job_match_scores` row for it. It is never a fabricated score (C-22, UC-05 BR-7). Auth and structural failures fail the whole request with 401/422/500.

**`GET /health`** — liveness only: `{ "status": "ok", "version": "2.0.0" }`. No auth, no state.

**`POST /api/v1/extract-skills`** — resume parser task, not built here.

### 7.4 Non-negotiables for the rebuild

1. **HMAC is verified for real.** Recompute over the raw body, compare with `hmac.compare_digest`, return **401** on mismatch. PHP already signs correctly (C-17).
2. **Failures return 4xx/5xx.** No fabricated success payloads, no hardcoded `.get()` defaults (C-22). A visible error beats an invisible wrong answer.
3. **No hardcoded scoring constants** that cannot be justified in a defense.
4. **`database.py` is deleted** — the service is stateless; PHP owns all data access (C-25).

---

## 8. Employer journey

```
/register (role=employer) → users 'Pending'
        ▼
/build-profile → employers row + business permit upload
        ▼
account_status='Active', verified_status='Pending'
        ▼
   ┌────────────────────────────────────┐
   │  Can log in. Can draft jobs.       │
   │  CANNOT publish. Banner explains.  │
   └────────────────────────────────────┘
        ▼  PESO admin sets Verified
/post-job → job_postings (job_status='Open')
        ▼  TRIGGER T2
/employer/dashboard → own jobs + applicant counts
        ▼
/employer/review-candidate?application_id=N
        │  ownership-scoped JOIN (existing IDOR defence — keep)
        ▼
  Ranked applicants, stored scores, matched-skill breakdown
        ▼
  Set status (Reviewed/Accepted/Rejected) + feedback → audit_logs
```

**Job posting form fields (locked):** title, description, `required_experience` **as INT years** (C-38 — replaces the free-text varchar), salary range (display-only), employment type, **work arrangement** (new enum: On-site / Remote / Hybrid — C-39), municipality, and the skills builder producing `job_required_skills` rows tagged Mandatory or Preferred.

The employer wireframe's "Work Arrangement" control now has a column behind it. In v1 it displays as a badge and is matched against the seeker's `preferred_work_setup` for display only — not scored.

---

## 9. PESO Admin journey

```
/admin/dashboard
    ├─ Metrics: seekers, verified employers, open jobs, pending verifications
    ├─ Seekers by municipality (lib_municipalities — fix the "barangay" labels, C-48)
    └─ Top in-demand skills
/admin/employers        → verify | reject  → audit_logs  → notify employer
/admin/view-document    → permit viewer (must be ownership/role-scoped)
/admin/seekers          → read-only roster
/admin/jobs             → oversight, suspend if needed
/admin/skills           → approve pending skills → TRIGGER T7
/admin/audit-logs       → NOW POPULATED (C-36)
/admin/export           → employment report → PDF (dompdf, already installed)
```

**Admin identity (C-37).** Seed one `peso_admins` row for the existing admin user and enforce `access_level`. SuperAdmin may promote users; Moderator may verify and approve; Viewer is read-only. Currently the table is empty and the role is set by hand in phpMyAdmin — which is not defensible under the Security and Trust construct.

**Audit logging** captures: employer verification and rejection, skill approval, job suspension, account status changes, admin logins, and failed logins. `audit_logs.action_type` already has the right enum.

---

## 10. Sign-off required

| # | Decision | Recommendation |
|---|---|---|
| **D-01** | Google OAuth deferred? | **Yes.** Costs days you need for the survey. |
| **D-02** | ML classifier excluded from the live path, retained as a documented finding? | **Yes.** Section 4. |
| **D-03** | Path A becomes extraction + confirmation, not classification? | **Yes.** |
| **D-04** | Remove `× 0.40`; max score becomes 100%? | **Yes.** |
| **D-05** | Missing location scores neutral (1.0), not penalized? | **Yes.** |
| **D-06** | Add `job_match_scores` cache table? | **Yes.** Only clean fix for the N+1. |
| **D-07** | `required_experience` → INT; add `work_arrangement` enum? | **Yes.** |
| **D-08** | Pending skills excluded from matching until approved? | **Yes.** |
| **D-09** | Drop `barangays` table entirely? | **Yes** — after confirming no unmigrated data. |
| **D-10** | Keep the title without "AI", or revise it? | Your adviser's call. If kept, Chapter I must define "smart matching" precisely. |

---

## 11. Diagram traceability

Every required deliverable maps to a section here. Build them in this order.

| # | Deliverable | Source section |
|---|---|---|
| 1 | Normalization 0NF→3NF | §7.2 + revised schema |
| 2 | ERD (17 tables + `job_match_scores` = 17 after dropping `barangays`) | §7.2, §8, §9 |
| 3 | Data Dictionary | Generated from the live schema post-migration |
| 4 | Context Diagram | §2 — AI service as internal subsystem |
| 5 | DFD Level 1 | §5, §8, §9 |
| 6 | DFD Low-Level (matching) | §6, §7.1 |
| 7 | Use Case Diagram | §5, §8, §9 — every case maps to a route |
| 8 | Wireframes | §5.2, §8 — realistic percentages, no orphan controls |
| 9 | Gantt (Aug 2026 →) | §12 |
| 10 | System Manual | §5, §8, §9 |

---

## 12. Schedule reality check

Target: **late September 2026.** Today: **21 August**. That is roughly five weeks.

The survey is the immovable constraint. Sixty respondents across four groups, in person, in Guimba, takes real calendar time you cannot compress.

**The survey must be distributed by approximately 8–12 September.** Working backwards:

| Window | Work |
|---|---|
| Aug 22–28 | Sign off M2. Engine rebuild. Purge legacy onboarding. Location migration. Cache table. |
| Aug 29 – Sep 5 | End-to-end stabilization. Audit logging. Admin seeding. Internal testing with real profiles. |
| Sep 8–12 | **Survey out.** Diagrams and Ch I/III rewrite begin in parallel. |
| Sep 13–22 | Survey collection. Diagrams finished. Ch I/III finished. |
| Sep 23–30 | Chapters IV & V from collected data. Consolidation. |

Two cautions, stated plainly:

**Do not plan around a billing cycle.** A subscription is renewable; a defense date and an OJT departure are not. Let the work set the schedule.

**If the system is not stable by 12 September, cut scope, not testing.** The QuIDTech-CaPE evaluation is the entire basis of your three research objectives. A narrower system that was genuinely evaluated beats a broader one that was not.

---

## 13. Carried-forward risk

**Chapter II citations are unverified.** The reference list contains entries I could not confirm — *OECD Research Group (2023)*, *World Bank Group (2023)*, *Kim, H., et al. (2025)*, *Santos & Ramos (2025)*. Given that the chapter was drafted separately without technical coordination, every citation should be verified against a real, retrievable source before submission. An unverifiable reference is a more serious finding than any bug in this register. Verify these before you write another chapter.
