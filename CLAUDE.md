# S.I.K.A.P. Hub — Project Instructions

Job matching portal for PESO Guimba, Nueva Ecija. BSIT capstone.
PHP MVC web app + Python FastAPI matching service + MySQL/MariaDB.

**The architecture is locked.** Specs in `/docs/` are authoritative. If a
request contradicts them, say so before writing code.

---

## Conventions that differ from defaults

- **Table and column names are lowercase_snake_case, always.** Production is
  Linux; `Job_Seekers` and `job_seekers` are different tables there. A
  PascalCase query is a production bug even if it works on XAMPP.
- **No ORM.** Native PDO with prepared statements. Never build SQL by
  concatenation, not even for an integer.
- **No password anywhere.** Authentication is Google OAuth or emailed OTP.
  If you find yourself writing `password_hash()` for a login flow, stop —
  that function is used only for hashing OTP codes.
- **The matching enum is `Mandatory` / `Preferred`.** `Optional` does not
  exist in this system. It caused a scoring bug that hid for months.
- **Scores range 0.0000–1.0000** and display as 0–100%. There is no
  multiplier capping the maximum.

## Security non-negotiables

Every one of these must be defensible out loud, not just present in code.

- CSRF verified on every POST, in the base `Controller` constructor.
- Every resource fetch by ID is ownership-scoped via JOIN, never by trusting
  a form field. See `Employer::getRankedApplicantsForJob()` for the pattern.
- File uploads validated by magic bytes with `finfo`, never by extension.
  Stored under randomized filenames.
- Session cookie: `HttpOnly`, `SameSite=Lax`, `Secure` only when the request
  is over HTTPS. **Never `SameSite=Strict`** — the browser withholds the
  cookie on the OAuth callback navigation and login silently fails.
- `session_regenerate_id(true)` after authentication and after role selection.
- Session fingerprint uses the User-Agent hash only. Do not hard-match on IP:
  Philippine mobile carriers rotate client addresses mid-session.
- OAuth callback verifies `state`, `nonce`, and the ID token signature
  against Google's public keys, plus `iss` / `aud` / `exp`. Never decode the
  JWT payload and trust it.
- HMAC-SHA256 on every call to the matching service, verified on the Python
  side with `hmac.compare_digest`, returning 401 on mismatch.

## Failure handling — the rule that matters most

**Never return a fabricated success.** The previous matching service wrapped
everything in `try/except` and returned a hardcoded score on any error. A
database migration broke scoring and nobody noticed for months, because a
broken engine and a working engine produced identical output.

Return real HTTP status codes. Log the failure. Show the user an error. A
visible error is always better than a plausible wrong number. This applies
equally to SMTP: a failed send writes `email_log.send_status = 'failed'`
with the error text.

## Do not reintroduce

These were deleted deliberately. If you see them, they are regressions.

- `OnboardingController.php`, `Onboarding.php`, `auth/onboarding.php`,
  and any `/onboarding` route — legacy barangay-era flow
- `/test-ai` — unauthenticated endpoint exposing the matching service
- `display_errors = 1` in `public/index.php`
- `barangays` table — replaced by the four-level PSGC hierarchy
- `database.py` in the Python service — it is stateless; PHP owns data access
- Any per-job HTTP loop on the seeker dashboard. The dashboard reads
  `job_match_scores` and makes **zero** calls to the matching service.

## Scoring rules

```
skill_score = (2 x mandatory_met + 1 x preferred_met)
              / (2 x mandatory_total + 1 x preferred_total)

geo_multiplier: 1.00 same municipality
                0.90 in seeker's preferred municipalities
                0.75 same province
                0.50 different province
                1.00 when the seeker has no location data  <- neutral, never
                                                              a penalty

final_score = skill_score x geo_multiplier
```

Feed ordering is **two keys**: employer `verified_status = 'Verified'`
descending first, then `final_score` descending. Ties break by summed
proficiency ordinal (Beginner 1, Intermediate 2, Expert 3), then
`profile_completeness`, then `jobseeker_id` ascending. Every card shows its
match percentage regardless of badge state.

`applications.ai_match_score` is captured once at application time and
**never recalculated**. It is what the employer evaluated.

## Commands

```
# Regenerate the data dictionary after any schema change
php generate_data_dictionary.php > docs/DATA_DICTIONARY.md

# Matching service
cd ai_engine && uvicorn main:app --reload --port 8000
```

## Specifications

@docs/M2_locked_system_flow_specification.md
@docs/PROJECT_STATE.md

Read on demand, not imported (large):
`docs/USE_CASE_MODEL_and_SPECIFICATIONS.md`,
`docs/DFD_SPECIFICATION_context_level0_lowlevel.md`,
`docs/AUDIT_PASS3-4_conflict_register_and_verdicts.md`,
`docs/DATA_DICTIONARY.md`

Conflict IDs (C-01 … C-49) refer to the audit register. When a change closes
one, say which.

## Working style

- Plan before editing. Show the plan; wait for approval.
- Small, reviewable commits. One conflict ID or one use case per change.
- When a spec is ambiguous, ask. Do not invent behaviour and proceed.
- Say when you are inferring rather than reading from a spec.
