# MCC Cricket Club — Implementation Gate

Status date: 2026-08-29
Current gate: `READY-FOR-IMPLEMENTATION` for the first vertical slice only; implementation state is `PARTIALLY_IMPLEMENTED`; other Phase 1 capabilities retain their existing blockers.
Sprint 1 hardening status: `PARTIALLY_IMPLEMENTED`; database-backed authentication/session/filter/audit tests, recovery endpoint/token lifecycle tests, approved database-backed account/IP recovery rate-limit tests, controlled SMTP-boundary tests, and local application log-sink regression checks are verified. SEC-14 policy enforcement has no exposed implementation defect, but true multi-process concurrency evidence is `UNVERIFIED` because the one focused probe stalled in the approved local test environment. Real SMTP deployment evidence, native web-SAPI session-ID evidence, approved OPS-01 append/read protection evidence, production log review, and broader release evidence remain incomplete; see `docs/SPRINT-1-HARDENING.md`.
Scope: Remaining unresolved decisions that directly affect guardian/player relationships, scheduling, attendance, and Phase 1 document handling. The CRM lead, approved Admissions lifecycle/conversion, and approved Player identity/profile slices are verified; later modules remain separate.
Authority: `docs/DECISIONS.md`; recommendations below are `PROPOSED` only, except where explicitly marked `APPROVED`.

## Required remaining decisions

### RBAC and branch isolation

No unresolved decision in this area is currently identified. `SEC-01`–`SEC-04` and `BR-01`–`BR-07` are `APPROVED`. Implementation still requires server-side authorization and branch-isolation tests; approval does not constitute implementation evidence.

### CRM → Admission → Player lifecycle

| Decision ID | Why it blocks implementation | Current status | Recommended baseline | Required approver | Dependencies |
|---|---|---|---|---|---|
| DAT-01 | Approved; it no longer blocks the first vertical slice. | APPROVED | Named module owners plus a central data steward and audited correction authority. | Project Owner (name not provided) | REQ-CRM-001, REQ-PLAYER-001; LD/PL decisions are approved. |

### Player placement assignments

| Decision ID | Why it blocks implementation | Current status | Scope |
|---|---|---|---|
| PA-01 | Batch/group cardinality, assignment authority, effective dates, replacement, capacity, and conflict behavior are defined and implemented. | APPROVED | Player-to-batch/group assignment |
| PA-02 | Coach assignment cardinality, assignment authority, effective dates, replacement, availability, capacity, and conflict behavior are defined and implemented. | APPROVED | Player-to-coach assignment |

### Guardian/player relationships

| Decision ID | Why it blocks implementation | Current status | Recommended baseline | Required approver | Dependencies |
|---|---|---|---|---|---|
| REL-01 | Approved; it no longer blocks the first vertical slice. | APPROVED | Explicit relationship records supporting multiple guardians and players. | Project Owner (name not provided) | REQ-PLAYER-001; SEC-09, SEC-13, and SEC-14 are approved. |
| REL-02 | Approved; it no longer blocks the first vertical slice. | APPROVED | Separate guardian, emergency-contact, and primary-contact types. | Project Owner (name not provided) | REQ-PLAYER-001; REL-01. |

### Session scheduling and resource conflicts

| Decision ID | Why it blocks implementation | Current status | Recommended baseline | Required approver | Dependencies |
|---|---|---|---|---|---|
| SCH-01 | Session scope and recurrence are defined. | APPROVED | One-time sessions only; recurrence deferred. | Client | REQ-SESSION-001; BR-04 approved. |
| SCH-02 | Scheduling authority, ownership, and eligibility are defined. | APPROVED | Dedicated permissions and persisted-assignment eligibility. | Client | REQ-SESSION-001; SCH-01. |
| SCH-03 | Capacity and conflict behavior are defined. | APPROVED | Enforce approved limits; reject conflicts; no invented capacity. | Client | REQ-SESSION-001; BR-04. |
| SCH-04 | Cancellation and rescheduling history are defined. | APPROVED | Preserve cancelled originals; create linked replacements. | Client | REQ-SESSION-001, REQ-ATT-001; AT-04. |
| SCH-05 | Coach availability and leave handling are defined. | APPROVED | Check availability; require explicit reassignment. | Client | REQ-SESSION-001; BR-03 approved. |
| OPS-09 | MCC timezone and stored/presented timestamp semantics are defined. | APPROVED | `Asia/Kolkata` presentation; UTC persistence and audit; explicit timezone input. | Client | NFR-07; REQ-SESSION-001. |

### Attendance rules and calculations

| Decision ID | Why it blocks implementation | Current status | Recommended baseline | Required approver | Dependencies |
|---|---|---|---|---|---|
| AT-01 | Attendance event grain, roster eligibility, and denominator membership are undefined. | NEEDS_DECISION | PROPOSED: one unique player/session event from the persisted session roster; cancelled sessions excluded; denominator requires explicit approval. | Authorized product owner and academy operations approver | REQ-ATT-001; SCH-01–SCH-05; PA-01/PA-02. |
| AT-02 | Status vocabulary and leave/medical treatment are undefined. | NEEDS_DECISION | PROPOSED options: four statuses, presence plus late flag, or separate approved absence taxonomy; no medical detail in Attendance. | Authorized product owner and safeguarding/operations approver | REQ-ATT-001; SEC-05 approved. |
| AT-03 | Recording authority, timing, cutoff, and late treatment are undefined. | NEEDS_DECISION | PROPOSED: dedicated record/correct permissions, approved timing window, OPS-09 UTC storage and Asia/Kolkata presentation; late semantics require selection. | Authorized product owner and academy operations approver | REQ-ATT-001; OPS-09. |
| AT-04 | Roster snapshot and cancellation eligibility boundary are undefined. | NEEDS_DECISION | PROPOSED: cancelled sessions create no attendance; persisted scheduled roster is authoritative pending an approved amendment rule. | Authorized product owner and academy operations approver | REQ-SESSION-001, REQ-ATT-001; SCH-04. |
| AT-05 | Correction authority, immutable prior values, stale-write behavior, and absence thresholds are defined for the approved source-event scope; derived thresholds remain deferred. | APPROVED / IMPLEMENTATION DEFERRED | Dedicated correction permission, reason, append-only history, stale-write rejection; defer thresholds and alerts. | Authorized product owner and academy operations approver | REQ-ATT-001; OPS-01 approved. |
| AT-06 | Continuous-absence formulas, reporting scope, thresholds, recipients, and alerts are undefined. | NEEDS_DECISION | PROPOSED: defer calculations/alerts until formula, scope, timezone, missing-data, recipient, and version rules are approved. | Authorized product owner and academy operations approver | REQ-ATT-001; REP/DASH and notification decisions remain unresolved. |

Attendance decision update (2026-08-29): AT-01–AT-06 are approved using the documented baselines. The Attendance source-event slice is authorized; AT-01 denominator behavior and AT-06 thresholds, formulas, reporting, alerts, and recipients remain explicitly deferred.

### Document categories, access, verification, and retention

| Decision ID | Why it blocks implementation | Current status | Recommended baseline | Required approver | Dependencies |
|---|---|---|---|---|---|
| DOC-01 | Upload authorization cannot be enforced without document-type ownership. | NEEDS_DECISION | PROPOSED: type-specific role policy enforced server-side. | Authorized product owner and security/privacy approver | REQ-DOC-001; SEC-06 and SEC-12 approved. |
| DOC-02 | Document trust and workflow state depend on an approved verification model. | NEEDS_DECISION | PROPOSED: pending, verified, rejected, expired, and retired states with verifier history. | Authorized product owner and security/privacy approver | REQ-DOC-002; SEC-07 approved. |
| DOC-03 | Unverified-document exposure cannot be authorized without a visibility rule. | NEEDS_DECISION | PROPOSED: restrict unverified documents to authorized verification users. | Authorized product owner and security/privacy approver | REQ-DOC-002; DOC-01, DOC-02. |
| DOC-05 | Medical-document access requires explicit least-privilege policy. | NEEDS_DECISION | PROPOSED: explicit role-based medical-detail policy. | Authorized product owner and security/privacy approver | REQ-DOC-002; SEC-05 approved. |
| DOC-06 | Storage deployment and recovery depend on encryption and key-ownership decisions. | NEEDS_DECISION | PROPOSED: encrypted private storage with managed key ownership. | Authorized product owner and security/operations approver | REQ-DOC-001; OPS-03 remains unresolved. |
| DOC-07 | Retention and deletion behavior cannot be implemented without periods and retirement semantics. | NEEDS_DECISION | PROPOSED: entity/legal-need retention with logical retirement first. | Authorized product owner and privacy approver | REQ-DOC-002; OPS-10 remains unresolved. |
| DOC-08 | Replacement and media handling require history and consent-boundary decisions. | NEEDS_DECISION | PROPOSED: immutable replacement history; media policy remains separate. | Authorized product owner and security/privacy approver | REQ-DOC-002; SEC-10 approved. |

## First Phase 1 vertical slice

`READY-FOR-IMPLEMENTATION`

The smallest proposed slice is authentication/authorization → branch-scoped CRM lead → admission → player conversion. Authorization, lifecycle, data ownership, and required player relationship decisions are approved. Remaining scheduling, attendance, document, reporting, dashboard, portal, and future decisions do not block this slice.

The CRM lead, approved Admissions lifecycle/conversion, approved Player identity/profile/branch-placement portions, approved one-time Scheduling slice, and approved Attendance source-event slice are implemented and tested. Attendance denominator and AT-06 derived behavior, documents, reports, dashboards, portals, and other modules remain separate; unresolved deployment-only evidence is not treated as a precondition for continuing approved business development. Sprint 1 hardening remains incomplete until its separately recorded evidence and decisions are resolved.

Implementation may begin for this slice only through the approved requirements, with server-side authorization, branch isolation, validation, audit, idempotency, and acceptance-test evidence still required.

Attendance deployment-readiness update (2026-08-29): `mcc_cricket_club_test` contains the applied Attendance schema and verified constraints. Focused smoke and regression tests pass. The local front controller returned HTTP 200. No production database, production runtime, or production logs were accessed; these remain deployment-only evidence items.

Attendance MVC update (2026-08-29): the approved source-event slice has a server-rendered session list and roster page under the existing auth/permission filters. Forms use CSRF fields and delegate all recording/correction rules to `AttendanceService`; the MVC feature is covered by database feature tests. Deployment and production evidence remain separate and are not inferred from these tests.

Attendance quality update (2026-08-29): final server-rendered UI accessibility, browser-security, database-integrity, and MVC review completed. Focused Attendance coverage is 27 tests / 133 assertions; the complete PHPUnit suite is 128 tests / 610 assertions; PHP syntax is clean for 114 files. Evidence is local/test-environment evidence only. Reports/exports and dashboards remain unavailable pending REP-01--REP-03 and DASH-01--DASH-03 approvals.

Browser MVC update (2026-08-29): authenticated staff navigation and server-rendered Views now cover the already-implemented CRM, Admissions, Players, Scheduling, and Attendance workflows. This is local application evidence only and does not approve deployment or production verification. No blocked module or unresolved business decision was implemented.

Administration implementation evidence (2026-08-29): the existing approved `rbac.manage` boundary now protects CI4 MVC user, role, permission-assignment, effective-access, enablement, and authorized-branch workflows. Focused tests verify deny-by-default access, CSRF protection, password hashing, secret exclusion from audit data, self-privilege-change rejection, and branch authorization. This does not approve new business permissions or alter the decision register.

CRM configuration implementation evidence (2026-08-30): approved LD-01/LD-04 behavior is implemented with database-backed ordered active/inactive Lead Source and Lead Status administration under `rbac.manage`; existing lead text values remain preserved, and new lead creation resolves only active configured values. Safe browser and configuration tests pass; the legacy destructive CRM suite remains unexecuted.

Branch creation implementation evidence (2026-08-30): the approved BR-08 Phase 1 slice uses the existing `rbac.manage` permission, accepts only server-validated name/code fields, creates active branches, enforces database uniqueness for name and code, and records successful creation audit metadata. This evidence does not approve branch edit/delete/closure/transfer/merge workflows.

Branch management update (2026-08-30): branch list, details, name/code/address update, and approved non-destructive deactivate/reactivate transitions are implemented under the same `rbac.manage` boundary with audited successful changes. Physical deletion is prohibited; lifecycle transitions preserve existing branch relationships and history.

Staff dashboard UI evidence (2026-08-29): the authenticated `/` page renders the shared MCC staff shell, permission-aware navigation, source-backed branch-scoped summaries, recent-work tables, and responsive empty states. Focused dashboard coverage is 2 tests / 13 assertions; the full PHPUnit suite is 140 tests / 668 assertions; PHP syntax is clean for 136 application files. This does not implement or approve the deferred Operational Dashboard KPI module and is local application evidence only.

## Exact decisions needed to unlock the slice

No further decision approval is required to unlock the first vertical slice.

After approval, update `DECISIONS.md`, `REQUIREMENTS.md`, and `MODULE-STATUS.md`, then derive the implementation backlog and tests from those decision IDs. No other listed decision is required for this first slice, but scheduling, attendance, and document decisions remain blockers for their respective Phase 1 modules.
