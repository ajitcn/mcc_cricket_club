# Tournament Implementation Plan

**Prepared:** 2026-10-01  
**Status:** Gated planning document. T-01 through T-04 have bounded `USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL`; T-05 through T-20 remain gated.  
**Sources:**

- docs/TOURNAMENTS-READ-ONLY-AUDIT.md
- docs/TOURNAMENTS-REQUIREMENTS-APPROVAL-REQUEST.md

## Planning rules

- Existing code, routes, migrations, role grants, and tests are evidence, not approval.
- T-05 through T-20 are currently NEEDS_DECISION. T-01 through T-04 are bounded user direction and are not client approval.
- NEEDS_DECISION and REJECTED items must not be implemented.
- This plan does not modify code, tests, migrations, seeders, permissions, data, existing decisions, or docs/SRS-original.md.
- All future work must retain explicit routes, thin controllers, service-layer transactions, centralized RBAC, server-side branch isolation, CSRF protection, escaped output, and append-oriented audit history.
- docs/SRS-original.md is absent from the checkout; docs/SRS.md and the reviewed requirements/decision documents are the available evidence.

## Existing CRUD baseline — evidence only

**Current implementation:** The module has branch-scoped Tournament list, create, edit, detail, participant persistence, optional Match-linked fixture creation, simple standings, audit reads, and transactional writes.

**Current source locations:**

- Routes: app/Modules/Tournaments/Config/Routes.php
- Controller: app/Modules/Tournaments/Controllers/Browser.php
- Service: app/Services/TournamentManagementService.php
- Model: app/Modules/Tournaments/Models/TournamentModel.php
- Views: app/Modules/Tournaments/Views/index.php, form.php, show.php
- Schema: app/Database/Migrations/20260904000400_CreateTournamentManagement.php
- Tests: tests/Unit/TournamentManagementContractTest.php and the destructive tests/database/TournamentManagementIntegrationTest.php

**Baseline limitations:** Fixture POST exists without a rendered fixture form; Ground and Player option queries omit active/eligibility filters; validation errors are generic; fixture dates are not bounded by Tournament dates; participant saves delete and recreate rows; status/result/selection corrections have no approved transition or lock policy; no safe persistent Tournament test fixture exists.

**User-provided direction (2026-10-03; not client approval):** The bounded fixture and standings slice is authorized for implementation. It covers manual fixtures, same-Tournament team validation, branch-matched active Grounds, separate MCC-local date/time input converted to existing UTC storage, Scheduled/Completed/Cancelled/Postponed statuses, reasoned status changes, audited completed-fixture corrections, and completed-fixture standings using existing Match results. Scorecards, innings, net run rate, registration, selection, notifications, exports, and other T-01–T-20 items remain blocked.

This baseline is not a claim that the current broader behavior is approved. Before any CRUD hardening, replace destructive database coverage with insert-only unique fixtures and obtain the applicable policy decision where the change would alter behavior.

## Shared implementation gates

Every approved item must specify:

1. Exact reconciled decision and effective date.
2. Affected routes, controller handlers, service methods, models, views, migrations, permissions, and tests.
3. Branch-scope query predicates for every read and write.
4. CSRF and server-side validation behavior.
5. Audit fields: actor, timestamp, branch, previous value, new value, reason, and source lineage where applicable.
6. Migration/backfill plan, rollback or forward-only policy, and deployment owner.
7. Unit, Feature, and persistent database acceptance tests.
8. Operational evidence for permissions, timezone, locks, backups, retention, notifications, and external integrations.

---

## T-01 — Tournament scope and competition types

**Status:** USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL (bounded implementation)

**Evidence:** TournamentManagementService has a fixed type allowlist and no parent competition or hierarchy relation.

**Blocked implementation:** Do not change type options, add parent competitions, or decide standalone versus Tournament-owned Match.

**Potential impact after approval:**

- Routes/controller/service: controlled type validation and parent-scope authorization.
- Models/migrations: option registry and optional parent relation.
- Views: type and parent selectors.
- Permissions: competition-scope permissions if approved.
- Tests: type rejection, parent lineage, branch/shared authorization, CSRF, audit.
- Dependencies: Matches and Tournament hierarchy.
- Branch isolation: resolve scope from authorized branch/competition records; never trust submitted parent IDs.
- Migration: possible controlled-option or hierarchy migration.
- Deployment evidence: approved option catalogue and role matrix.

**Acceptance criteria:** Only approved types and parent relationships are accepted; unknown, rejected, forged, or cross-scope values fail safely.

---

## T-02 — Tournament lifecycle and status transitions

**Status:** USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL (bounded implementation)

**Evidence:** planned, active, completed, and cancelled are directly accepted on create/update; no transition matrix, reason, lock, or status history exists.

**Blocked implementation:** Do not add transition endpoints, completion/cancellation rules, reopening, locks, or reason requirements.

**Potential impact after approval:**

- Routes: explicit POST transition endpoints if approved.
- Controller/service: transition validation, timing, idempotency, and safe return.
- Models/migrations: status history, reason, lock, and transition metadata.
- Views: permission-aware actions and confirmation forms.
- Permissions: existing tournament.update or approved narrow permissions.
- Tests: matrix, invalid transitions, repeated requests, audit, rollback.
- Dependencies: fixture completion, scorecard locking, results.
- Branch isolation: transition only within authorized Tournament branch/scope.
- Migration/deployment: forward-only status history and backfill policy; operational owner confirms lock behavior.

**Acceptance criteria:** Only approved transitions succeed and every transition is auditable and idempotent.

---

## T-03 — Branch ownership and multi-branch access

**Status:** USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL (bounded implementation)

**Evidence:** tournaments has one branch_id; all current queries are branch-scoped; no participating-branches table exists. BR-05 describes a broader shared model.

**Blocked implementation:** Do not add global/shared Tournament access or branch-transfer behavior.

**Potential impact after approval:**

- Routes/controller/service: scope resolution for list, detail, edit, fixture, and reports.
- Models/migrations: participating branch relation and historical scope.
- Views: branch participation and visibility indicators.
- Permissions: shared-competition permission if needed.
- Tests: authorized, cross-branch, shared, historical, forged branch IDs.
- Dependencies: BR-04/05/06, Matches, Grounds, Players, Coaches.
- Branch isolation: every joined Match, Ground, Player, Coach, fixture, audit, and export row must use approved scope.
- Migration/deployment: relation backfill and authorization rollout evidence.

**Acceptance criteria:** Current and historical visibility exactly matches the reconciled single/shared model.

---

## T-04 — Tournament dates and timezone handling

**Status:** USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL (bounded implementation)

**Evidence:** Tournament dates are DATE fields and datepicker inputs; fixture scheduled_at requires an explicit timezone suffix; service uses hard-coded Asia/Kolkata behavior.

**Blocked implementation:** Do not change date formats or fixture date boundaries until policy is approved.

**Potential impact after approval:**

- Views/JavaScript: shared datepicker and separate date/time fields.
- Controller/service: configured-timezone parsing, UTC persistence, local display.
- Models/migrations: usually none; normalization only if approved.
- Tests: local round trip, invalid/reversed dates, DST/timezone boundaries, fixture range.
- Dependencies: Scheduling/Match timezone conventions.
- Branch isolation: date-filtered results remain branch-scoped.
- Deployment evidence: configured application timezone and stored timestamp convention.

**Acceptance criteria:** Local values round-trip correctly and fixture dates obey approved inclusive boundaries.

---

## T-05 — Registration and entry rules

**Status:** NEEDS_DECISION

**Evidence:** No registration entity, entry window, approval workflow, or registration route exists.

**Blocked implementation:** Do not add registration or entry data.

**Potential impact after approval:**

- Routes/controller/service: registration, approve/reject, withdraw.
- Models/migrations: entries, statuses, approvals, documents/consents if approved.
- Views: forms, status, empty/error states.
- Permissions: registration and approval permissions.
- Tests: windows, limits, duplicates, branch scope, authorization, audit.
- Dependencies: Players, Documents, Payments if later approved, Notifications.
- Branch isolation: entry records must inherit approved Tournament/branch scope.
- Migration/deployment: data backfill, consent, retention, and operational owner evidence.

**Acceptance criteria:** Entries enforce approved timing, limits, duplicate, consent, and approval rules.

---

## T-06 — Player eligibility and age/category rules

**Status:** NEEDS_DECISION

**Evidence:** Participant writes check Player branch equality only; no active, age, batch, category, or immutable eligibility snapshot exists.

**Blocked implementation:** Do not add age cutoffs, category rules, exceptions, or selection snapshots.

**Potential impact after approval:**

- Service/model: eligibility resolver and snapshot persistence.
- Views: filtered selectors and reasoned errors.
- Migrations: category/snapshot/approval fields if required.
- Permissions: selection/approval permissions.
- Tests: active, branch, date boundary, age/category, forged IDs, correction.
- Dependencies: Players, Programs, Batches, Admissions lineage.
- Branch isolation: Player branch and authorized Tournament scope must match.
- Deployment evidence: birth-date source, timezone, category configuration.

**Acceptance criteria:** Only approved eligible Players can register/select, with auditable exceptions.

---

## T-07 — Coach and official assignments

**Status:** NEEDS_DECISION

**Evidence:** No Coach or official assignment fields/tables exist; fixtures only link Match/Ground.

**Blocked implementation:** Do not add assignment workflows or infer Coach authority from current Tournament grants.

**Potential impact after approval:**

- Routes/controller/service: assign/reassign/correct actions.
- Models/migrations: assignment tables, role/type fields, effective dates.
- Views: assignment selectors and history.
- Permissions: coach.assign or a Tournament-specific permission.
- Tests: active role, branch, conflict, reassignment reason, audit.
- Dependencies: Coaches, Sessions, Matches, availability/leave.
- Branch isolation: assignment branch must match approved Tournament or participating branch.
- Deployment evidence: role catalogue and operational conflict checks.

**Acceptance criteria:** Only approved active assignees are accepted and all changes preserve history.

---

## T-08 — Team and squad structure

**Status:** NEEDS_DECISION

**Evidence:** Participants store free-text teams or Player IDs; no Team, squad, XI, reserve, or selection history schema exists.

**Blocked implementation:** Do not replace free text or add squad limits/Playing XI approval.

**Potential impact after approval:**

- Routes/views: team/squad management and review.
- Service/model: duplicate and membership validation.
- Migrations: Team, squad, selection, and snapshot tables.
- Permissions: selection and approval actions.
- Tests: limits, duplicates, branch/eligibility, subset rules, correction.
- Dependencies: Players, Matches, Performance.
- Branch isolation: every Player/team membership must be within approved scope.
- Deployment evidence: selection owner, locking and correction process.

**Acceptance criteria:** Approved team and squad structure is enforced server-side and auditable.

---

## T-09 — Match scheduling and fixture generation

**Status:** NEEDS_DECISION

**Evidence:** Fixture service accepts manual number, teams, optional Match/Ground, scheduled time, status, and points; no fixture form or generation workflow is rendered.

**Blocked implementation:** Do not add fixture UI, generation, rescheduling, or lifecycle behavior yet.

**Potential impact after approval:**

- Routes/controller/service: fixture create/edit/cancel/generate.
- Views: fixture form, filters, empty states, action controls.
- Models/migrations: generation metadata, sequence, reschedule/correction history.
- Permissions: tournament.update or approved fixture permissions.
- Tests: duplicate numbers, date range, Ground/Match conflict, CSRF, branch, transaction.
- Dependencies: Matches, Scheduling, Grounds, Coaches.
- Branch isolation: linked Match/Ground/Coach must obey Tournament scope.
- Deployment evidence: scheduling lock/concurrency and timezone configuration.

**Acceptance criteria:** Manual/generated fixtures satisfy approved scope, dates, resource conflicts, and audit rules.

---

## T-10 — Tournament rounds and progression

**Status:** NEEDS_DECISION

**Evidence:** No rounds, groups, brackets, progression, or hierarchy tables exist.

**Blocked implementation:** Do not add round or bracket records.

**Potential impact after approval:**

- Routes/services/models/views: round creation, progression, qualification, reseeding.
- Migrations: round/group/bracket/progression schema.
- Permissions: round administration and approval.
- Tests: ordering, byes, ties, incomplete rounds, correction, locking, branch visibility.
- Dependencies: lifecycle, fixtures, results, standings.
- Branch isolation: progression scope follows approved Tournament/participating branches.
- Deployment evidence: recalculation and operational recovery process.

**Acceptance criteria:** Round and progression state is deterministic, auditable, and protected against unauthorized changes.

---

## T-11 — Scorecards and result integration

**Status:** NEEDS_DECISION

**Evidence:** Fixtures store optional Match linkage and points; Matches store free-text result/score summary; no scorecard/verification tables exist.

**Blocked implementation:** Do not add innings, delivery, scorecard, or result-verification workflows.

**Potential impact after approval:**

- Routes/views: scorecard entry/review/lock.
- Services/models: score calculation and Match/Tournament result integration.
- Migrations: scorecard, innings, metric, verification, lock/version tables.
- Permissions: score entry, verify, correct.
- Tests: format rules, data validation, branch, locking, correction, rollback.
- Dependencies: Matches, Player Performance, Coach KPIs.
- Branch isolation: scorecard participants and linked Match must be in approved scope.
- Deployment evidence: scorecard concurrency, backup, and lock recovery.

**Acceptance criteria:** Results are sourced, verified, locked, corrected, and audited according to the approved policy.

---

## T-12 — Points table and standings formulas

**Status:** NEEDS_DECISION

**Evidence:** standings counts completed linked-Match results and adds stored home/away points; no formula/version metadata exists.

**Blocked implementation:** Do not change points or add NRR/bonus formulas.

**Potential impact after approval:**

- Service: versioned calculation and recomputation.
- Views/reports: formula/version/freshness presentation if approved.
- Models/migrations: configuration/version or materialized results if required.
- Permissions: standings visibility and correction.
- Tests: approved examples, rounding, missing data, recalculation, branch scope.
- Dependencies: result and exceptional-outcome decisions, scorecards.
- Branch isolation: standings aggregate only approved Tournament scope.
- Deployment evidence: formula version, timezone, recomputation and monitoring.

**Acceptance criteria:** Approved standings examples reproduce exactly and corrections recalculate affected rows.

---

## T-13 — Tie, abandoned, rain-cancelled, and no-result handling

**Status:** NEEDS_DECISION

**Evidence:** no_result is accepted by Match result logic; Tournament has no tie, abandonment, weather, or exceptional-outcome workflow.

**Blocked implementation:** Do not add status/result values or downstream effects.

**Potential impact after approval:**

- Routes/services/views: outcome actions and evidence.
- Migrations: outcome reason, weather/evidence, event date, history.
- Permissions: authorized correction/approval.
- Tests: points, progression, attendance, notifications, audit.
- Dependencies: lifecycle, standings, scorecard, Attendance, Notifications.
- Branch isolation: outcome records follow Tournament/Match scope.
- Deployment evidence: weather/evidence source and notification policy.

**Acceptance criteria:** Exceptional outcomes are distinct from wins/losses and produce approved downstream effects.

---

## T-14 — Player and Coach performance integration

**Status:** NEEDS_DECISION

**Evidence:** Tournament stores no performance rows; Player 360 can show Match participation; Coach KPI and Performance Improvement Index are separately gated.

**Blocked implementation:** Do not feed Tournament data into Performance or Coach KPIs.

**Potential impact after approval:**

- Services: source selection, formula, branch and permission checks.
- Models/migrations: source links, approval markers, versioning.
- Views: KPI/performance presentation.
- Permissions: performance.view/update and coach.kpi.view boundaries.
- Tests: source approval, formula, negative/missing data, correction, recalculation.
- Dependencies: PF-01–PF-06, PII-v1, CM-05/06, Match scorecard.
- Branch isolation: source Player/Coach/Tournament branch scope must be consistent.
- Deployment evidence: formula version and recalculation monitoring.

**Acceptance criteria:** Only approved source rows feed calculations and unavailable data remains Not yet available.

---

## T-15 — Attendance integration

**Status:** NEEDS_DECISION

**Evidence:** Tournament has no attendance relation, roster snapshot, or attendance route; current Attendance is session/snapshot-based.

**Blocked implementation:** Do not add Tournament attendance or alter existing Attendance rules.

**Potential impact after approval:**

- Routes/services: roster and attendance actions.
- Models/migrations: Tournament roster/attendance relation or source mapping.
- Views: summary and correction UI.
- Permissions: attendance.view/record/correct or approved Tournament-specific permissions.
- Tests: snapshot, status, denominator, branch, correction, audit.
- Dependencies: Attendance AT-01–AT-06, Sessions, Players.
- Branch isolation: attendance scope must follow approved Tournament/branch model.
- Deployment evidence: denominator, timezone, and correction ownership.

**Acceptance criteria:** Attendance source, statuses, snapshots, calculations, and corrections match the reconciled policy.

---

## T-16 — Corrections, approval, locking, and audit history

**Status:** NEEDS_DECISION

**Evidence:** Tournament and fixture writes use central audit records, but participant saves delete/recreate rows and no status, selection, scorecard, or lock history exists.

**Blocked implementation:** Do not change participant persistence or add correction/lock actions.

**Potential impact after approval:**

- Routes/controller/service: approve, lock, correct, cancel, and reason flows.
- Models/migrations: append-only history/version/approval/lock tables.
- Views: history, read-only, correction forms.
- Permissions: update/correct/approve/audit permissions.
- Tests: actor, branch, timestamp, old/new values, reason, rollback, idempotency.
- Dependencies: lifecycle, selection, scorecard, retention.
- Branch isolation: audit records and correction targets must be branch-scoped.
- Deployment evidence: append-only storage, backups, lock recovery, audit review.

**Acceptance criteria:** No unauthorized overwrite/delete occurs; all approved corrections preserve prior values and audit metadata.

---

## T-17 — Permissions and role visibility

**Status:** NEEDS_DECISION

**Evidence:** tournament.view/create/update are route/service gates; migration grants all three to Super Admin, Branch Admin, and Coach. No verify/correct/export permission exists.

**Blocked implementation:** Do not broaden or narrow role grants.

**Potential impact after approval:**

- Routes/filters/services/views: role-aware access for every action.
- Migrations: narrow permission grants if required.
- Tests: route filters, service denial, branch scope, hidden/read-only controls, session refresh.
- Dependencies: all Tournament decisions and RBAC.
- Branch isolation: permissions never replace branch authorization.
- Deployment evidence: permission migration status and sign-out/sign-in requirement.

**Acceptance criteria:** Each action is protected by the approved permission and branch scope; UI visibility mirrors server authorization.

---

## T-18 — Reports and exports

**Status:** NEEDS_DECISION

**Evidence:** No Tournament report/export route, service, view, or test exists.

**Blocked implementation:** Do not add CSV/PDF/Excel exports or report columns.

**Potential impact after approval:**

- Routes/services/views: filtered reports/export endpoints.
- Permissions: report/export permissions.
- Models/migrations: usually none; export audit/materialized summaries may be needed.
- Tests: filter preservation, branch scope, field policy, escaping, audit.
- Dependencies: standings/results, role visibility, retention.
- Branch isolation: exports use the same server-side scope as screens.
- Deployment evidence: file handling, export size, audit, storage, and retention.

**Acceptance criteria:** Exports match visible filters, scope, approved columns, permissions, and audit policy.

---

## T-19 — Retention and historical recalculation

**Status:** NEEDS_DECISION

**Evidence:** Foreign keys preserve some lineage; no Tournament retirement, retention, versioning, or recalculation policy exists.

**Blocked implementation:** Do not add retention jobs, retirement, deletion, or historical recalculation.

**Potential impact after approval:**

- Services/jobs: retention, retirement, recalculation.
- Models/migrations: retired/versioned rows and correction events.
- Permissions/views: audit and read-only historical states.
- Tests: retention boundaries, immutable history, correction propagation, branch scope.
- Dependencies: standings, KPIs, exports, audit, Documents.
- Branch isolation: historical visibility follows approved scope.
- Deployment evidence: scheduler, backups, restore, retention owner, monitoring.

**Acceptance criteria:** Approved records remain auditable, unauthorized deletion is impossible, and source corrections recalculate only authorized dependent results.

---

## T-20 — Notifications and external delivery

**Status:** NEEDS_DECISION

**Evidence:** No Tournament notification trigger, recipient, channel, retry, failure, or delivery audit exists.

**Blocked implementation:** Do not add email, WhatsApp, SMS, push, or external delivery.

**Potential impact after approval:**

- Services/routes: event creation, recipient resolution, delivery boundaries.
- Models/migrations: notification event/delivery state, retry, failure, consent.
- Permissions/views: notification visibility and administration.
- Tests: recipient scope, consent, branch isolation, retries, failures, duplicate delivery, audit.
- Dependencies: lifecycle, registration, fixtures, results, Attendance, Notifications policies.
- Branch isolation: recipients and event records must be scoped to approved branches/relationships.
- Deployment evidence: provider configuration, secrets, consent, retry monitoring, retention, and failure handling.

**Acceptance criteria:** No external delivery occurs without approved consent, channel, recipient, retry, and audit policy.

---

## Gated implementation sequence

1. Reconcile T-05 through T-20; leave unanswered or conflicting items blocked. Keep T-01 through T-04 limited to the recorded user-provided direction.
2. Replace TournamentManagementIntegrationTest destructive cleanup with an insert-only unique-fixture strategy before using the persistent test database.
3. If the existing CRUD baseline remains authorized, address only confirmed baseline defects that do not decide policy: active/eligible option filtering, field-level error preservation, configured-timezone parsing, fixture date bounds, and a visible fixture entry point.
4. Reconcile participant replacement and audit semantics before changing persistence.
5. Implement only the approved Tournament/Match branch and hierarchy model.
6. Implement approved lifecycle, registration, eligibility, assignment, fixture, locking, and correction workflows in dependency order.
7. Implement scorecards, standings, performance, and Attendance only after their source/formula/visibility decisions are Approved.
8. Implement reports, exports, notifications, retention, and external operations only after deployment evidence and policy approval are recorded.

## Cross-cutting acceptance and deployment evidence

Before any approved slice is released, require:

- PHP/JavaScript syntax checks and focused Unit, Feature, and database tests.
- Persistent test fixtures that never truncate, delete, disable foreign keys, or depend on empty tables.
- Migration review proving forward-only, non-destructive behavior.
- Permission migration status and session refresh instructions.
- Branch-scope evidence for every list, detail, write, export, and audit query.
- CSRF, transaction rollback, lock/concurrency, and audit verification.
- Configured timezone and date-boundary evidence.
- Backup/restore, retention, scanner/storage, notification-provider, or external-delivery evidence where applicable.
- No changes to docs/SRS-original.md.

No NEEDS_DECISION item is authorized by this plan.


## Tournament Phase 1 reconciliation — T-01 through T-04

**Recorded:** 2026-10-04  
**Status:** USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.  

The user selected the recommended secure defaults for the first four Tournament decision areas. These records authorize only the bounded implementation described below and do not approve T-05 through T-20.

- **T-01:** Keep the existing Tournament type allowlist; standalone branch-owned Tournaments; explicitly authorized Match links only; no parent competition hierarchy.
- **T-02:** Allow `planned → active → completed`, `planned → cancelled`, and `active → cancelled`; completed and cancelled are terminal; no reopening; exceptional changes require a reason; record transitions in append-only audit history.
- **T-03:** One owning branch; no implicit cross-branch access; Grounds, Teams, Players, Coaches, Fixtures, Matches, and reports must match the owning branch; no branch transfer workflow.
- **T-04:** Separate MCC-local date/time inputs; service-layer conversion; UTC storage where timestamps are required; no visible timezone suffix; fixtures must fall within Tournament start/end boundaries; use existing Match/Scheduling conventions.

**Approver identity:** User-provided direction; client approver not recorded.  
**Approval date:** Not client-approved.  
**Effective date:** 2026-10-04 for the bounded implementation only.
