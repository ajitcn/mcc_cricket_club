# Individual Coaching Session Skill Taxonomy Decision Request

**Date:** 2026-10-11  
**Status:** All items `NEEDS_DECISION`  
**Direction record:** User reference intent only; not client approval

## Evidence boundary

The client reference analysis identifies skill/shot selection for Individual Coaching Sessions. Current Scheduling already supports branch, Coach, Player, Batch, Ground, separate MCC-local date/time fields, roster, attendance, feedback, history, audit, CSRF, and transactions. The repository does not contain an approved Individual Session type, skill taxonomy, skill assessment table, or session-media linkage. Match Player Performance has batting, bowling, and fielding data, but it is Match-scoped and must not be silently reused for coaching skills.

No code, route, permission, migration, database, or `docs/SRS-original.md` change is authorized by this request.

## Decision fields

Each decision below requires all of these fields. A recommended default is guidance only and does not constitute approval.

- **Resolution:** `NEEDS_DECISION`
- **Selected option:** ______________________________
- **Comments:** _____________________________________
- **Approver:** _____________________________________
- **Approval date:** _________________________________
- **Effective date:** ________________________________

## Decision areas

### SKILL-01 — Skill areas

**Current evidence:** No skill-area field or taxonomy exists in Scheduling.  
**Recommended secure default:** Use a controlled catalog with exactly these initial areas: batting, bowling, fielding, wicketkeeping, fitness, and game awareness. Do not infer an area from Match Performance fields.  
**Choices:**

1. Controlled six-area catalog listed above.
2. A smaller catalog approved by the client.
3. Free-text areas (not recommended; inconsistent reporting and authorization risk).

**Permissions/branch:** Reuse centralized session permissions; catalog visibility must be branch-authorized or explicitly global only after approval.  
**Migration:** New catalog or enum migration only after approval; no change for a read-only decision.  
**Tests:** Allowed areas, rejected unknown areas, branch filtering, authorization, and stable labels.

### SKILL-02 — Skill and shot options

**Current evidence:** No approved batting shots, bowling skills, fielding drills, wicketkeeping skills, fitness activities, or game-awareness options exist.  
**Recommended secure default:** Use a versioned, centrally managed option catalog; do not embed options in views or infer them from notes.  
**Choices:**

1. Central catalog maintained by authorized administrators.
2. Branch-scoped catalogs managed by authorized branch staff.
3. No predefined options; Coaches use notes only until a catalog is approved.

**Permissions/branch:** Separate catalog management permission only if approved; session users may select only active options visible in their branch scope.  
**Migration:** Skill and option tables, active/version fields, and indexes may be required.  
**Tests:** Option visibility, inactive-option rejection, branch isolation, submitted-value preservation, and historical label stability.

### SKILL-03 — Custom Coach-created skills

**Current evidence:** No custom-skill workflow exists.  
**Recommended secure default:** Coaches cannot create custom taxonomy values; they may enter bounded notes while the catalog is unresolved.  
**Choices:**

1. No custom values.
2. Coach proposal requiring administrator approval before use.
3. Coach-created branch-local values.

**Permissions/branch:** No new permission under the recommended default.  
**Migration:** None for no custom values; proposal workflow would require new records and audit fields.  
**Tests:** Rejection of unknown values and preservation of notes.

### SKILL-04 — Skill scope: global or branch-scoped

**Current evidence:** Branch isolation is centralized, but no skill catalog scope is defined.  
**Recommended secure default:** Global option definitions with branch-authorized usage; branch-specific additions require explicit approval.  
**Choices:**

1. Global catalog, branch-authorized usage.
2. Branch-scoped catalogs.
3. Global catalog with branch overrides.

**Permissions/branch:** Every session and selected option must match the authorized Session branch.  
**Migration:** Add branch nullable/required according to the approved scope; indexes and uniqueness follow the decision.  
**Tests:** Cross-branch option rejection and Super Admin scope behavior.

### SKILL-05 — Whether skill selection is required

**Current evidence:** Existing Scheduling does not require a skill field.  
**Recommended secure default:** Skill selection is optional until the taxonomy and UX are approved; session notes remain available.  
**Choices:**

1. Optional skill selection.
2. At least one skill required for an Individual Session.
3. Exactly one primary skill plus optional secondary skills.

**Permissions/branch:** Existing session create/update authorization.  
**Migration:** Nullable skill relationship if optional; required relationship only after approval.  
**Tests:** Create/update with no skill, valid selections, and invalid selections.

### SKILL-06 — Effect on Performance and Development

**Current evidence:** Match Player Performance and Development are separate workflows. No approved linkage from coaching skills exists.  
**Recommended secure default:** Skill selection does not create or modify Performance or Development records.  
**Choices:**

1. No automatic linkage.
2. Read-only source link for reporting.
3. Automatic Development update.
4. Automatic Performance update.

**Permissions/branch:** No cross-module write without explicit permission and approval.  
**Migration:** Link tables or source references only if approved.  
**Tests:** Confirm session saves do not mutate Performance/Development; authorized read-only links if later approved.

### SKILL-07 — Notes and feedback limits

**Current evidence:** Session notes and attendance-first Coach feedback already exist with validation, correction reasons, and audit history.  
**Recommended secure default:** Keep existing note/remark fields and limits; do not add a second unrestricted notes field.  
**Choices:**

1. Reuse existing bounded notes and feedback.
2. Add a separate bounded skill-observation note.
3. Rich text/free-form notes.

**Permissions/branch:** Existing session/feedback permissions and branch checks.  
**Migration:** None for reuse; new bounded field requires a forward-only migration.  
**Tests:** Length, required/optional rules, XSS-safe output, correction reason, audit before/after values.

### SKILL-08 — Media and document support

**Current evidence:** Documents use private storage, authorization, scanning/quarantine, expiry, and audit foundations; Scheduling has no approved media linkage.  
**Recommended secure default:** No media/document upload in Individual Sessions until storage, scanning, retention, and visibility are approved.  
**Choices:**

1. No media support.
2. Private Document-service linkage with scanning and expiry.
3. External media link with controlled authorization.

**Permissions/branch:** Existing document permissions plus Session branch authorization; never public URLs.  
**Migration:** Link table and document metadata may be required.  
**Tests:** Private access, quarantine, branch denial, safe filenames, expiry, and audit events.

### SKILL-09 — Player and Parent visibility

**Current evidence:** Portal authentication and relationship checks exist, but Individual Session skill visibility is not defined.  
**Recommended secure default:** Staff-only visibility until explicit portal fields, approval state, and privacy rules are decided.  
**Choices:**

1. Staff only.
2. Published/approved summary visible to the Player.
3. Published/approved summary visible to authorized Parents and the Player.
4. Full notes and skill history visible after approval.

**Permissions/branch:** Portal relationship and branch checks must be server-side.  
**Migration:** Visibility/status fields may be required.  
**Tests:** Portal separation, relationship denial, branch denial, and exclusion of internal notes.

### SKILL-10 — Correction and audit rules

**Current evidence:** Scheduling and feedback use transactions, append-oriented audit records, correction reasons, and optimistic concurrency where applicable.  
**Recommended secure default:** Reuse existing update/correction rules; require a reason for corrections after an existing record is saved and retain before/after values.  
**Choices:**

1. Existing Session correction rules.
2. Coach edits until Session completion; staff correction afterward.
3. Immutable skill records with a separate correction record.

**Permissions/branch:** Existing session update permission; separate correction permission only if approved.  
**Migration:** Version/reason fields only if existing records cannot support the rule.  
**Tests:** CSRF, stale version rejection, reason validation, audit metadata, and branch isolation.

### SKILL-11 — Reporting and dashboard impact

**Current evidence:** Reports and Director Dashboard formulas are permission-aware; no approved skill KPI exists.  
**Recommended secure default:** Do not include skill metrics in reports or dashboards until formulas and taxonomy are approved.  
**Choices:**

1. No reporting initially.
2. Counts by approved skill option.
3. Progress/KPI trends using approved assessments.

**Permissions/branch:** Existing report/dashboard permissions and branch filters.  
**Migration:** Reporting indexes or aggregates only if approved.  
**Tests:** Source-only calculations, branch scope, empty states, and no fabricated values.

### SKILL-12 — Permissions and branch isolation

**Current evidence:** Centralized RBAC and branch-scoped Scheduling/Attendance permissions exist.  
**Recommended secure default:** Reuse `session.view`, `session.create`, and `session.update`; do not add taxonomy permissions until a catalog workflow is approved.  
**Choices:**

1. Reuse existing Session permissions.
2. Add `session.skill.view` and `session.skill.update`.
3. Add separate catalog-management permissions.

**Migration:** Permission rows only after approval and role mapping review.  
**Tests:** Role visibility, forged option/session rejection, branch isolation, and portal separation.

### SKILL-13 — Retention and archival

**Current evidence:** General audit and document retention decisions exist, but no skill-record retention policy is defined.  
**Recommended secure default:** Retain skill records with the Session audit history; do not delete records automatically.  
**Choices:**

1. Retain with Session history; manual archival only.
2. Time-limited retention with documented archival.
3. Anonymized reporting after a defined period.

**Permissions/branch:** Archive access remains authorized and branch-scoped.  
**Migration:** Retention/archival fields or jobs may be required.  
**Tests:** No destructive deletion, audit preservation, authorization, and archival idempotency.

## Approval summary

All items `SKILL-01` through `SKILL-13` remain `NEEDS_DECISION` until the selected option, resolution, approver, approval date, and effective date are completed. Recommended defaults are not client approval.

## Recorded secure defaults — USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL

The following secure defaults are recorded for planning only. They do not authorize implementation. Every item remains `NEEDS_DECISION`; approver, approval date, and effective date remain blank.

| ID | Selected secure default | Resolution | Comments | Approver | Approval date | Effective date |
|---|---|---|---|---|---|---|
| SKILL-01 | Controlled six-area catalog: batting, bowling, fielding, wicketkeeping, fitness, game awareness | NEEDS_DECISION | User-provided direction; not client approval |  |  |  |
| SKILL-02 | Versioned centrally managed skill/shot option catalog | NEEDS_DECISION | User-provided direction; not client approval |  |  |  |
| SKILL-03 | Coaches cannot create custom taxonomy values; use bounded notes | NEEDS_DECISION | User-provided direction; not client approval |  |  |  |
| SKILL-04 | Global definitions with branch-authorized usage | NEEDS_DECISION | User-provided direction; not client approval |  |  |  |
| SKILL-05 | Skill selection remains optional | NEEDS_DECISION | User-provided direction; not client approval |  |  |  |
| SKILL-06 | Skill selections do not automatically mutate Performance or Development | NEEDS_DECISION | User-provided direction; not client approval |  |  |  |
| SKILL-07 | Reuse existing bounded Session notes and feedback | NEEDS_DECISION | User-provided direction; not client approval |  |  |  |
| SKILL-08 | No media/document upload in Individual Sessions | NEEDS_DECISION | User-provided direction; not client approval |  |  |  |
| SKILL-09 | Staff-only visibility | NEEDS_DECISION | User-provided direction; not client approval |  |  |  |
| SKILL-10 | Reuse existing correction rules; require a reason and append before/after audit values | NEEDS_DECISION | User-provided direction; not client approval |  |  |  |
| SKILL-11 | No skill metrics in reports or dashboards | NEEDS_DECISION | User-provided direction; not client approval |  |  |  |
| SKILL-12 | Reuse existing Session permissions; no taxonomy permissions yet | NEEDS_DECISION | User-provided direction; not client approval |  |  |  |
| SKILL-13 | Retain skill records with Session history; no automatic deletion | NEEDS_DECISION | User-provided direction; not client approval |  |  |  |

## SKILL-02 concrete option catalog — USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL

The following catalog is supplied for decision review. It is not client approval. Every option is active by default and uses the display order shown. Coaches may not add custom options. Options are global definitions used only in authorized branch Sessions. Authorized administrators may retire an option for future use; retirement does not alter historical records. Existing Sessions retain the original option identifier and label after a catalog change.

### Batting

1. Front-foot drive — active by default
2. Back-foot defence — active by default
3. Pull shot — active by default
4. Cut shot — active by default
5. Sweep shot — active by default
6. Running between wickets — active by default

### Bowling

1. Line and length — active by default
2. Seam movement — active by default
3. Swing control — active by default
4. Spin control — active by default
5. Yorker — active by default
6. Bouncer — active by default

### Fielding

1. Ground fielding — active by default
2. Catching — active by default
3. Throwing accuracy — active by default
4. High catches — active by default
5. Boundary fielding — active by default
6. Direct hit — active by default

### Wicketkeeping

1. Stance and footwork — active by default
2. Taking behind the stumps — active by default
3. Glovework — active by default
4. Standing up to the stumps — active by default
5. Standing back — active by default
6. Stumping technique — active by default

### Fitness

1. Speed and agility — active by default
2. Strength — active by default
3. Endurance — active by default
4. Mobility — active by default
5. Core stability — active by default
6. Recovery — active by default

### Game awareness

1. Shot selection — active by default
2. Strike rotation — active by default
3. Field placement — active by default
4. Bowling plans — active by default
5. Match awareness — active by default
6. Decision-making — active by default

**Catalog rules:** Coaches may not create custom options; definitions are global and branch-authorized at use time; retired options are unavailable for new Sessions but remain readable on existing Sessions; historical Sessions retain their original option reference and label. **Resolution:** `NEEDS_DECISION`. **Approver:** ______. **Approval date:** ______. **Effective date:** ______.
