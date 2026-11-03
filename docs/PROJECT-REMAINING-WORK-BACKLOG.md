# Project Remaining Work Backlog

**Prepared:** 2026-10-01  
**Scope:** Current repository implementation and documented decisions.  
**Rule:** This report does not approve unresolved requirements, infer client approval from code, or modify `docs/SRS-original.md`.

## Classification and evidence

Reviewed `docs/MODULE-STATUS.md`, `PRIORITIZED-COMPLETION-BACKLOG.md`, `REQUIREMENTS.md`, `REQUIREMENTS-TRACEABILITY.md`, `DECISIONS.md`, `ARCHITECTURE.md`, `DATABASE.md`, `RBAC.md`, `UI-ARCHITECTURE.md`, `IMPLEMENTATION-GATE.md`, the module audit/decision briefs, current `app/Modules`, `app/Services`, migrations, and tests.

`Implemented and verified` means bounded source behavior has focused evidence; deployment or client acceptance may still be separate. `Implemented but needing tests` means code exists but current safe evidence is incomplete. `Partially implemented` means a bounded slice exists while other defined work is absent or unresolved. `Blocked by NEEDS_DECISION` means work would require an unrecorded rule, formula, permission, or visibility decision. `Deferred or out of scope` must not be started.

## Module coverage index

| Module | Classification | Backlog entry |
|---|---|---|
| Auth | Implemented but needing tests | Authentication, accounts, sessions |
| Admin/RBAC | Implemented and verified for bounded management; expansion blocked | Admin, RBAC, audit, branches |
| Branches | Implemented and verified | Admin, RBAC, audit, branches |
| CRM/Leads | Implemented and verified for recorded scope | CRM Leads and Trials |
| Trials | Implemented and verified for recorded scope | CRM Leads and Trials |
| Admissions | Partially implemented | Admissions |
| Players | Partially implemented | Players, guardians, contacts, Player 360 |
| Player 360 | Implemented but needing tests | Players, guardians, contacts, Player 360 |
| Programs | Partially implemented | Programs and Batches |
| Batches | Partially implemented | Programs and Batches |
| Scheduling/Sessions | Implemented and verified for bounded one-time scope | Scheduling Sessions |
| Attendance | Partially implemented | Attendance |
| Coaches | Partially implemented | Coaches |
| Grounds | Implemented but needing tests | Grounds |
| Documents | Partially implemented | Documents |
| Reports | Partially implemented; policy expansion blocked | Reports, Dashboard, Marketing Dashboard |
| Dashboard | Partially implemented; KPI expansion blocked | Reports, Dashboard, Marketing Dashboard |
| Marketing Dashboard | Partially implemented; formulas blocked | Reports, Dashboard, Marketing Dashboard |
| Notifications | Implemented but needing tests | Notifications |
| Matches | Partially implemented | Matches |
| Tournaments | Implemented and verified for CRUD only; T-01–T-20 blocked | Tournaments |
| Performance | Partially implemented | Performance and Performance Improvement Index |
| Fitness | Deferred or out of scope | Fitness, High Performance, Parent Portal |
| High Performance Program | Deferred or out of scope | Fitness, High Performance, Parent Portal |
| Parent Portal | Blocked by NEEDS_DECISION | Fitness, High Performance, Parent Portal |
| Development | Implemented but needing tests | Development (within Player Development) |
| Equipment | Partially implemented; maintenance scope blocked | Equipment |

## Priority order

1. Close safe test, migration, route, CSRF, branch-scope, and deployment evidence for approved slices.
2. Verify bounded CRM, Admissions, Players, Branches, Programs/Batches, Scheduling, Attendance source events, Coaches, Grounds, Documents, Reports, Notifications, Matches, and Tournament CRUD.
3. Reconcile stale documentation and operational evidence without widening behavior.
4. Implement decision-gated formulas, lifecycle, visibility, exports, portals, competition, and external delivery only after explicit approval.

## Module backlog

### Authentication, accounts, sessions — **Implemented but needing tests**

- **Evidence:** `app/Modules/Auth`, `AuthService`, recovery migrations, `AuthControllerTest`, recovery/rate-limit integration tests, and `AUTH-DESIGN-DECISION-BRIEF.md`.
- **Remaining:** Verify native web-session rotation, HTTPS/cookie deployment, SMTP delivery/failure evidence, and unresolved lockout/reset-token policy.
- **Security/permissions:** Session invalidation, recovery enumeration resistance, secret redaction, and centralized RBAC are release gates; final role matrix is unresolved.
- **Data/tests/dependencies:** No new migration; verify existing migration order. Add safe native HTTP/deployment evidence. Depends on approved auth baseline and SMTP owner.
- **Acceptance/order:** Recovery is single-use/expiring/rate-limited, sessions invalidate, logs are redacted, and no production secret enters tests. P0.

### Admin, RBAC, audit, branches — **Bounded management implemented and verified; expansion blocked**

- **Evidence:** `AdminService`, authorization filters, Branch Management routes/service, audit logger/models/migration, `BranchManagementSafeTest`, `AdminRbacIntegrationTest`, `Ops01AuditPolicyTest`.
- **Remaining:** Obtain final role/permission/branch matrix, Super Admin scope, audit read/retention policy, and deployment evidence. Re-run only safe tests.
- **Security/permissions:** All endpoints require centralized permissions and branch queries; actor, branch, action, entity, before/after, and reason must remain auditable. No new grants.
- **Data/tests/dependencies:** Verify existing migrations; no schema change. Depends on client RBAC decisions.
- **Acceptance/order:** Authorized lifecycle works with CSRF, uniqueness, audit, preserved relationships, and no physical deletion. P0.

### CRM Leads and Trials — **Implemented and verified for recorded scope**

- **Evidence:** CRM/Trials modules, `CrmLeadSafeWorkflowTest`, `CrmFollowUpSafeTest`, lead configuration and Trial integration tests, `LD-01`–`LD-13`, `TRIAL-01`, `DAT-01`.
- **Remaining:** Re-run safe suites and reconcile stale wording; broader campaign/counselor fields are not authorized.
- **Security/permissions:** Branch-scoped lead/trial/status access, duplicate checks, transitions, CSRF, history, and audit remain mandatory; use existing `lead.*`/`trial.*` grants.
- **Data/tests/dependencies:** No migration; verify lead-status configuration and fixtures. Depends on RBAC and CRM records.
- **Acceptance/order:** Approved fields, one-future-booking rule, manual attendance, errors, branch checks, and audit pass. P1.

### Admissions — **Partially implemented**

- **Evidence:** Admissions service/module, lifecycle/reopening/conversion code, browser/intake/conversion/document tests, `AD-01`, `PL-01`–`PL-06`.
- **Remaining:** Safe verification of lifecycle, idempotent conversion, guardian/contact transfer, browser behavior, and deployment evidence. Keep `REL-03`–`REL-05` and `DAT-02` blocked.
- **Security/permissions:** Admission permissions, branch scope, checklist preservation, `REJECTED → IN_REVIEW` reason, conversion lineage, history/audit, and sensitive contacts must remain enforced.
- **Data/tests/dependencies:** No unapproved backfill/migration. Destructive legacy tests remain unsuitable. Depends on Players, contacts, Documents, and RBAC.
- **Acceptance/order:** Only approved transitions and one Player lineage are created on retry; unauthorized/cross-branch operations fail. P1.

### Players, guardians, contacts, Player 360 — **Partially implemented**

- **Evidence:** Players/Player360 modules, conversion/contact workflows, `PlayerBrowserSecuritySafeTest`, `PlayerWriteWorkflowSafeTest`, Player360 tests, `REL-01`/`REL-02`.
- **Remaining:** Verify profile/lifecycle/assignment/contact behavior safely; resolve guardian visibility/revocation and `DAT-02`; fill browser/production evidence.
- **Security/permissions:** Sensitive guardian/medical/contact data needs branch and relationship authorization; assignments and transfers need audit/optimistic conflict protection. Do not broaden `player.*`/contact permissions.
- **Data/tests/dependencies:** No contact duplication or backfill; migration waits on decisions. Depends on Admissions and RBAC.
- **Acceptance/order:** Authorized users see only permitted data, no-guardian states are clear, conversion is idempotent, and histories are preserved. P1.

### Development — **Implemented but needing tests**

- **Evidence:** `app/Modules/Development`, development/lineage services, `PlayerDevelopmentLineageSafeTest.php`, integration and contract tests.
- **Remaining:** Verify source lineage, branch scope, correction/audit, and UI against current records. Do not infer progression scores or development formulas from existing code.
- **Security/permissions/data/tests:** Keep development records branch-scoped and sensitive; use existing permissions only; no migration without approved fields/lifecycle. Depends on Players and Performance decisions.
- **Acceptance/order:** Source lineage is preserved, authorized updates are audited, and no unapproved score/progression formula is exposed. P1 verification.

### Programs and Batches — **Partially implemented**

- **Evidence:** Program/Batch services/modules, nullable `batches.program_id` migration, `ProgramBatchModuleContractTest`, `ProgramBatchRoutesTest`, safe database coverage.
- **Remaining:** Re-run create/list/edit evidence and resolve Program ownership, lifecycle/archive/delete, dependent-record behavior, and remaining edit scope. Do not add capacity, coaches, recurrence, attendance, or fees.
- **Security/permissions:** Branch Admin uses validated active branch; Super Admin uses authorized active branches; all IDs are revalidated. Existing narrow management permissions only.
- **Data/tests/dependencies:** No backfill/non-null migration without approval. Add isolated edit/audit coverage if absent. Depends on Branches, Players, Sessions.
- **Acceptance/order:** Required names/relationships, filtered dropdowns, forged/cross-branch rejection, value preservation, and audit pass. P1.

### Scheduling Sessions — **Implemented and verified for bounded one-time scope**

- **Evidence:** Scheduling service/routes/views, safe workflow/regression tests, session detail/history/attendance integration, `SCH-01`–`SCH-05`, `OPS-09`.
- **Remaining:** Re-run safe one-time/UI verification and record deployment evidence. Do not add recurrence, capacity, or unresolved scheduling policy.
- **Security/permissions:** Revalidate Branch/Program/Batch/Coach/Ground/Player/roster IDs; preserve attendance snapshots, leave checks, CSRF, audit, and permission-scoped actions.
- **Data/tests/dependencies:** No new migration expected. Depends on authorized fixtures, Coaches, Grounds, Attendance, and timezone configuration.
- **Acceptance/order:** Local date/time round-trips to UTC, errors remain on form, snapshot/attendance/completion/reassignment rules hold, and no overflow. P1.

### Attendance — **Partially implemented**

- **Evidence:** Attendance service/routes/views, snapshot/history models, `AttendanceSafeWorkflowTest`, contract tests, `AT-01`–`AT-05` source-event evidence.
- **Remaining:** Verify Session Details integration, recording, correction, idempotency, and audit safely. Do not implement percentages, denominators, thresholds, alerts, or recipients while unresolved.
- **Security/permissions:** Seven statuses only; reject `EXCUSED`; immutable snapshot, correction reason, branch permission, CSRF, transaction rollback, and audit are mandatory.
- **Data/tests/dependencies:** No new migration for source events; legacy cleanup tests are not safe for persistent DB. Depends on Scheduling/Coach fixtures.
- **Acceptance/order:** Snapshot/session/player matching, read-only behavior, errors, summary counts, correction history, and audit pass. P1.

### Coaches — **Partially implemented**

- **Evidence:** Coach service/module, multi-branch assignment, leave/availability/feedback, KPI/PII code, Coach Unit/Feature/database suites, user-direction records.
- **Remaining:** Re-run behavioral coverage and close evidenced UI/route gaps. Keep correction windows, KPI governance/source policy, and external alerts decision-gated.
- **Security/permissions:** `coach.view/create/update/assign`, `coach.kpi.view`, `session.update`, attendance/feedback permissions remain branch-scoped; audit actor/branch/old/new/reason is required.
- **Data/tests/dependencies:** Existing Coach/availability/leave/feedback/PII migrations must be applied only after test checks. Depends on Players, Sessions, Attendance, Performance, RBAC.
- **Acceptance/order:** Authorized assignments/leave/feedback/PII calculations work, forged/cross-branch IDs fail, and missing KPIs show `Not yet available`. P1.

### Grounds — **Implemented but needing tests**

- **Evidence:** Grounds module and `GroundManagementContractTest`, safe/integration tests.
- **Remaining:** Safe verification of active/branch filtering, CRUD, scheduling references, and UI. Maintenance edit/cancel/delete remains blocked.
- **Security/permissions:** Active Ground IDs must belong to authorized branch; history must survive changes. Existing Ground permissions only.
- **Data/tests/dependencies:** No migration unless maintenance policy is approved; avoid cleanup-based integration tests. Depends on Branches/Scheduling.
- **Acceptance/order:** Authorized CRUD/selectors, CSRF, audit, active-state and cross-branch checks pass. P1.

### Documents — **Partially implemented**

- **Evidence:** Private storage/document service, policy foundation migration, secure download/preview, document contract/routes/integration tests, `DOCUMENTS-*` briefs.
- **Remaining:** Verify foundation safely and obtain deployment-owner evidence for scanner/quarantine, private storage, encryption keys, retention, expiry, verification authority, version replacement, medical and parent/player visibility.
- **Security/permissions:** Safe generated filenames, MIME/size/error validation, generic not-found, branch authorization, expired/retired exclusion, and sensitive redaction remain mandatory. Existing document permissions only.
- **Data/tests/dependencies:** `AddDocumentPolicyFoundation` is forward-only but must be applied only after approved test pre-checks; no production application here. Depends on deployment owner.
- **Acceptance/order:** Unauthorized/missing/retired/expired access is indistinguishable; approved verification/replacement/audit works; absent scanner fails closed. P1/P2.

### Reports, Dashboard, Marketing Dashboard — **Partially implemented; formulas/policy blocked**

- **Evidence:** Report/Dashboard/Marketing modules, contract and branch tests, CSV/date fixes, dashboard label fixes, Marketing timezone integration and approval/audit briefs.
- **Remaining:** Verify existing branch/date/filter behavior. Do not add PDF/Excel, new columns, attribution, source vocabulary, conversion/cost/ROI/targets, KPI catalogue, role variants, freshness, or denominator rules until `REP-*`, `DASH-*`, and marketing decisions are reconciled.
- **Security/permissions:** Server-side branch scope, field allowlists, permission checks, and safe unavailable states are required; final export/KPI role matrix unresolved.
- **Data/tests/dependencies:** No current migration. Future summaries require approved source/freshness schema. Depends on CRM/Admissions/Attendance definitions and RBAC.
- **Acceptance/order:** Existing filters use local date boundaries and branch scope; no fabricated metric appears; unresolved exports/formulas remain unavailable. P1 for verification, P3 for expansion.

### Notifications — **Implemented but needing tests**

- **Evidence:** In-app listing/read-state module, CSRF/filter-state fixes, contract and integration tests.
- **Remaining:** Verify recipient, branch, read/unread and error behavior. Do not add triggers, channels, retries, retention, opt-out, or external delivery (`NOT-01`–`NOT-03`).
- **Security/permissions:** Recipient and branch scope must be server-side; use existing notification permissions only.
- **Data/tests/dependencies:** No migration for current foundation; safe fixture isolation may be needed. Depends on source modules only if triggers are approved.
- **Acceptance/order:** State-changing forms have CSRF, filters survive navigation, and unauthorized recipients cannot view records. P1.

### Matches — **Partially implemented**

- **Evidence:** Match CRUD/detail/completion, btSelect squad/Playing XI, date/time fixes, contract/Feature/database tests, `MATCHES-*` documents.
- **Remaining:** Verify CRUD/detail/completion and fix only concrete defects. Competition ownership, scorecards, innings, selection governance, results, standings, performance, exports, and tournament integration are blocked.
- **Security/permissions:** Branch, Ground, Coach, Squad, Playing XI and status IDs require active/authorized server checks; completion remains CSRF-protected, audited, idempotent, and branch-scoped.
- **Data/tests/dependencies:** No new CRUD migration; depends on Players/Coaches/Grounds/Scheduling and future Tournament decisions.
- **Acceptance/order:** Separate local date/time, authorized dependencies, preserved errors, safe detail actions, and no unresolved workflow enabled. P1.

### Tournaments — **Implemented and verified for CRUD only; T-01–T-20 blocked**

- **Evidence:** `TOURNAMENTS-CRUD-COMPLETION-REPORT.md`, routes/service/model/views/migration, Tournament Unit/Feature tests; recorded result 13 tests/85 assertions passed.
- **Remaining:** Preserve CRUD evidence and, only with safe fixtures, add non-destructive database coverage. Do not implement registration, teams, fixtures, rounds, scorecards, standings, performance, attendance, notifications, exports, new statuses, or permissions.
- **Security/permissions:** Existing branch checks, active Ground/Player validation, CSRF, transactions, audit and escaped output remain mandatory; use `tournament.view/create/update` only.
- **Data/tests/dependencies:** Existing migration must follow Branch/User/Role/Permission/Player/Ground/Match dependencies. Destructive database integration is intentionally not run. Depends on all T-01–T-20 decisions.
- **Acceptance/order:** CRUD remains branch-isolated and auditable; every T-01–T-20 remains `NEEDS_DECISION`. P1 verification, P3 competition work.

### Performance and Performance Improvement Index — **Partially implemented**

- **Evidence:** Performance module, approval-marker migration, PII service/view, safe/contract/integration tests, data-source brief.
- **Remaining:** Verify approved baseline/latest selection, incomparable/zero/missing handling, negative results, branch scope, `coach.kpi.view`, audit and recalculation. Do not add other metrics.
- **Security/permissions:** Approved Player records and Coach KPI output are sensitive and branch-scoped; corrections are permissioned and append-only.
- **Data/tests/dependencies:** Apply `AddPerformanceApprovalMarkers` only after test pre-checks; unmarked records remain excluded. Depends on Players/Coach fixtures.
- **Acceptance/order:** Versioned formula is applied only to comparable approved records; no valid data shows `Not yet available`; source correction recalculates. P1.

### Fitness, High Performance, Parent Portal — **Deferred or blocked by NEEDS_DECISION**

- **Evidence:** Fitness, Development, HPP and ParentPortal modules/tests exist, while `FIT-*`, `HP-*`, and `REL-03`–`REL-05`/portal decisions remain unresolved.
- **Remaining:** Do not add units, thresholds, benchmarks, readiness, portal visibility, guardian verification, consent/revocation, or medical/document exposure.
- **Security/permissions/data/tests:** Medical and relationship data require explicit role, branch, consent, retention, and audit rules; no migration or permission grant is authorized. Existing tests are evidence only.
- **Acceptance/order:** Client records exact fields, formulas, visibility, correction, retention and relationship rules before implementation resumes. P3.

### Equipment — **Partially implemented; maintenance scope blocked**

- **Evidence:** Equipment module, CRUD service/views, safe/Feature tests, and equipment decision brief.
- **Remaining:** Safe CRUD verification only. Categories, assignments, maintenance, availability, restore/delete, and role precedence remain unresolved.
- **Security/permissions/data/tests:** Existing branch-owned `equipment.*` permissions; no new migration. Avoid cleanup tests. Depends on RBAC/Ground/Scheduling decisions.
- **Acceptance/order:** Current name/quantity/branch CRUD is authorized, audited, non-destructive, and branch-isolated. P1 verification.

## Confirmed defects that can proceed without new policy

Only fix defects evidenced by source or a focused test: route/service mismatches; demonstrable branch-scope leaks; missing CSRF on an existing POST; known date conversion/validation mismatch under the configured timezone; hidden/misplaced existing authorized controls; or stale expectations caused by an already-approved status. Each fix needs a focused regression test and must not infer a new workflow, permission, formula, lifecycle, or retention rule.

## Verification and migration rules

Use only `CI_ENVIRONMENT=development/testing` with the approved local test database, normally `mcc_cricket_club_test`. Inspect migration order and forward-only safety before applying anything. Do not run tests that call `emptyTable()`, truncate, disable foreign keys, delete persistent fixtures, or target production. Use guarded insert-only fixtures with unique identifiers and report exact commands, counts/assertions, failures, migration status, and blockers.

## Backlog closure

An item closes only when an approved requirement/decision ID, implementation evidence, server-side security behavior, migration status, focused tests, acceptance criteria, and deployment/client evidence are recorded. Code presence never converts a `NEEDS_DECISION` item into approval.

## Source records

`docs/MODULE-STATUS.md`; `docs/PRIORITIZED-COMPLETION-BACKLOG.md`; `docs/REQUIREMENTS.md`; `docs/REQUIREMENTS-TRACEABILITY.md`; `docs/DECISIONS.md`; `docs/ARCHITECTURE.md`; `docs/DATABASE.md`; `docs/RBAC.md`; `docs/UI-ARCHITECTURE.md`; `docs/IMPLEMENTATION-GATE.md`; module audit/decision briefs; current `app/Modules`, `app/Services`, migrations, and tests.
