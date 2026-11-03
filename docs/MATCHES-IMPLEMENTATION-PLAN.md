# Matches Implementation Plan

**Prepared:** 2026-09-30  
**Source documents:**

- docs/MATCHES-READ-ONLY-AUDIT.md
- docs/MATCHES-REQUIREMENTS-APPROVAL-REQUEST.md

**Status:** Planning only. No implementation authorization is granted for unresolved items. No application code, tests, migrations, seeders, database records, permissions, existing decision records, or docs/SRS-original.md were modified. No migrations or destructive commands were run.

## Planning and authorization rules

- Existing code is implementation evidence, not approval.
- A policy marked NEEDS_DECISION remains blocked until an approver explicitly resolves it and the resolution is reconciled into the project requirements and decision records.
- This plan does not infer approval from routes, migrations, role grants, tests, or traceability status.
- Existing CRUD behavior is described as a current baseline. Extending or changing that behavior still requires the applicable Match decisions.
- No item is currently marked REJECTED in the approval request.

## Current Match CRUD baseline

**Current status:** APPROVED only as an inventory of existing implementation; no new scope is authorized.

### Evidence

- Explicit authenticated routes exist for list, create, edit, detail, and POST save.
- Match records are branch-scoped and include branch, optional ground, optional Coach, opponent, match type, status, local/UTC-converted start/end values, result, score summary, Coach remarks, creator, and timestamps.
- match_players stores squad and Playing XI membership. Playing XI must be a subset of squad and cannot exceed 11.
- MatchManagementService applies branch authorization, ground checks, Coach assignment and SchedulingService conflict checks, transactions, resource locks, and central audit persistence.
- Current tests are MatchManagementContractTest and MatchManagementIntegrationTest. No Match Feature test exists.

### Existing files

- Routes: app/Modules/Matches/Config/Routes.php
- Controller: app/Modules/Matches/Controllers/Browser.php
- Service: app/Services/MatchManagementService.php
- Models: app/Modules/Matches/Models/MatchModel.php; app/Modules/Matches/Models/MatchPlayerModel.php
- Views: app/Modules/Matches/Views/index.php, form.php, show.php
- Migration: app/Database/Migrations/20260904000300_CreateMatchManagement.php
- Role catalog/restore evidence: app/Database/Seeds/AuthorizationRestoreSeeder.php
- Navigation: app/Services/NavigationService.php
- Related reads: app/Services/Player360Service.php, ReportService.php, DashboardService.php, TournamentManagementService.php
- Tests: tests/Unit/MatchManagementContractTest.php; tests/database/MatchManagementIntegrationTest.php

### Existing baseline dependencies

- Branches, user branches, roles, permissions, users, Grounds, Players, Coach assignments, SchedulingService availability/leave/conflict data, central audit persistence, session authentication, and CSRF configuration.
- Match migration must precede the Tournament migration because tournament_fixtures.match_id references matches.

### Existing baseline security and audit

- Route permission filters: match.view, match.create, match.update.
- Service-level branch authorization on every read/write.
- Ground and Player branch predicates; Coach role and branch assignment checks.
- Transaction and resource-lock protection during writes.
- Central match_created and match_updated audit records.
- Escaped Match view output and branch-filtered audit history.

### Existing baseline gaps to resolve before hardening

- Player options and writes do not explicitly enforce active/eligible state.
- Coach options and writes do not explicitly enforce active/enabled state.
- Form uses combined datetime text fields and explicit timezone suffixes rather than the current shared separate local date/time convention.
- Validation is mostly generic and does not provide field-level errors.
- Status/result/selection edits have no approved transition, correction, lock, or reason policy.
- Match Feature coverage is missing.

### Baseline acceptance criteria

- Existing CRUD remains route-compatible and branch-isolated.
- No unresolved policy is implemented implicitly.
- Any approved hardening slice adds server-side validation, CSRF/permission/branch tests, and audit coverage without changing unrelated modules.

---

# Decision-gated implementation items

Each section below is blocked until its status changes from NEEDS_DECISION. The listed files are a planning map, not authorization to edit them.

## MT-01 — Match scope and competition types

**Current status:** NEEDS_DECISION

### Evidence

The service currently allows friendly, practice, league, and knockout. Matches have no tournament relationship. The requirements review describes a broader Match scope and leaves standalone versus tournament ownership unresolved.

### Planned impact after approval

- Routes/controllers: Match create/edit/detail routes and Browser methods for controlled competition/parent fields.
- Service: MatchManagementService validation and save workflow; TournamentManagementService relationship checks.
- Models: MatchModel and any approved competition relationship model.
- Views: Matches form, list filters, detail, and navigation links.
- Migration: Possible competition type catalog, tournament_id, or relationship table.
- Permissions: Existing match permissions unless the approved decision requires narrow competition permissions.
- Tests: Unit option contracts; Feature route/form behavior; database parent/standalone persistence and authorization.

### Dependencies and branch scope

Tournament model and hierarchy decisions; branch/shared competition decision; authorized branches and any participating-branch relation.

### Security/audit

Validate controlled types server-side, authorize parent competition access by branch/shared scope, and audit changes to competition ownership.

### Acceptance criteria

Only approved competition types and parent relationships are accepted; rejected/unknown values fail; branch and history rules match the reconciled decision.

## MT-02 — Match lifecycle and status transitions

**Current status:** NEEDS_DECISION

### Evidence

scheduled, completed, and cancelled are directly editable under match.update. No transition matrix, timing, cancellation reason, completion rule, lock, or idempotency workflow exists.

### Planned impact after approval

- Routes: Add explicit transition endpoints only if approved; preserve POST save route compatibility.
- Controller: Thin transition handlers with CSRF, permission, and return handling.
- Service: Transition matrix, timing, reason, terminal/idempotent checks.
- Models/views: Status history fields and action controls.
- Migration: Status history/transition metadata, lock, and reason fields if needed.
- Permissions: Existing match.update or approved narrow transition permissions.
- Tests: Unit transition matrix; Feature authorized/denied routes; database idempotency, audit, and rollback.

### Dependencies and branch scope

MT-04 locking/correction; MT-12 exceptional outcomes; current authorized branch.

### Security/audit

Server-side status authorization and branch checks; actor, timestamp, branch, previous/new status, reason, Match ID; transactional writes.

### Acceptance criteria

Only approved transitions succeed, invalid transitions fail safely, repeated requests are idempotent, and audit history is complete.

## MT-03 — Branch ownership and multi-branch access

**Current status:** NEEDS_DECISION

### Evidence

matches has one branch_id and all reads/writes are branch-filtered. BR-05 allows Matches to support participating branches, but no participating-branches relation exists and MT-02 in the decision index remains unresolved.

### Planned impact after approval

- Routes/controllers/services: Scope resolution for list/detail/form/save.
- Models/migrations: Participating branch relation if shared access is approved.
- Views: Branch/participants display and filters.
- Permissions: Branch-scoped match permissions, possibly shared-competition permission.
- Tests: Unit scope contracts; Feature cross-branch access; database participant scope and historical visibility.

### Dependencies and branch scope

BR-04/BR-05/BR-06, authorization service, user branch assignments, Tournament hierarchy.

### Security/audit

Never trust submitted branch IDs or participant lists; resolve current authorization server-side; audit participation changes and preserve historical branch lineage.

### Acceptance criteria

Single-branch or shared behavior exactly matches the reconciled model; unauthorized branches are excluded from every read and write.

## MT-04 — Opponent/team representation

**Current status:** NEEDS_DECISION

### Evidence

opponent is required free text up to 150 characters; no Team entity exists.

### Planned impact after approval

- Routes/controller/service: Controlled team/opponent validation and option loading.
- Models/migrations: Team or external participant schema if approved.
- Views: Form dropdown/text combination and detail/list presentation.
- Permissions: Team/participant access if needed.
- Tests: Unit field rules; Feature form behavior; database relation/uniqueness and branch checks.

### Dependencies and branch scope

MT-01 competition scope; MT-03 participation model.

### Security/audit

Validate names/IDs and branch scope; audit participant changes; escape all display values.

### Acceptance criteria

The approved representation is enforced; invalid, duplicate, inactive, or unauthorized participants are rejected.

## MT-05 — Player selection and eligibility

**Current status:** NEEDS_DECISION

### Evidence

Squad and Playing XI are stored; XI must be a squad subset and max 11. Current checks only Player branch equality. Selection submission deletes and recreates match_players rows.

### Planned impact after approval

- Service/model: Eligibility query, selection workflow, snapshots/history, correction rules.
- Views/controller: Authorized selection controls and errors.
- Migration: Eligibility snapshot, selection history, approval/lock metadata if required.
- Permissions: Existing match.update or approved selection permissions.
- Tests: Unit subset/capacity; Feature selection visibility/CSRF; database active/eligible/branch/duplicate/correction behavior.

### Dependencies and branch scope

Player lifecycle, batch/Coach assignment, MT-03, MT-15, PF-01–PF-04.

### Security/audit

Revalidate every Player ID, reject forged/cross-branch/inactive/ineligible IDs, preserve history if required, and audit actor/branch/old/new selection/reason.

### Acceptance criteria

Only approved Players can be selected; XI rules hold; corrections and retries meet the approved history/idempotency policy.

## MT-06 — Coach and official assignments

**Current status:** NEEDS_DECISION

### Evidence

Optional Coach assignment checks role and branch and calls SchedulingService conflict validation. Active user state and officials are not modeled.

### Planned impact after approval

- Routes/controller/service: Assignment/reassignment actions and option loading.
- Models/migrations: Official/assignment records if required.
- Views: Assignment forms and history.
- Permissions: coach/session permissions or approved Match assignment permissions.
- Tests: Unit role/scope checks; Feature actions; database active Coach, branch, availability, leave, conflict, reassignment audit.

### Dependencies and branch scope

CM-02, CM-03, CM-06; MT-03; SchedulingService.

### Security/audit

Only active authorized assignees; preserve branch/session history; require reasons for approved corrections; central append-only audit.

### Acceptance criteria

Assignments obey approved roles, branch, availability/leave/conflict rules, and history requirements.

## MT-07 — Venue and ground rules

**Current status:** NEEDS_DECISION

### Evidence

Optional active branch Grounds are loaded. Ground unavailability and session/match overlap are checked; external venues and overrides do not exist.

### Planned impact after approval

- Service/controller/views for external/shared venue options and override actions.
- Ground/Match models and migration if external venue metadata is approved.
- Permissions for override/booking only if required.
- Tests for options, conflict, override, branch scope, and audit.

### Dependencies and branch scope

Ground management/booking, MT-03, scheduling conflict policy.

### Security/audit

Revalidate ground/venue ownership and active state; audit overrides and changes.

### Acceptance criteria

Venue selection and conflict behavior exactly match the approved decision and never bypass branch isolation.

## MT-08 — Match date/time and timezone behavior

**Current status:** NEEDS_DECISION

### Evidence

The service requires explicit Z/offset values and stores UTC DATETIME; calendar filtering uses hard-coded Asia/Kolkata. The form uses combined Start/End text fields.

### Planned impact after approval

- Views/JavaScript: Shared separate datepicker/date and time fields.
- Controller/service: Local input normalization, field-specific validation, UTC persistence/display.
- Models/migrations: Usually none; data repair only if approved.
- Tests: Unit parser; Feature form round trip; database boundary/filter/timezone checks.

### Dependencies and branch scope

OPS-09; shared datepicker; application timezone configuration.

### Security/audit

Do not trust client timezone text; validate date/time server-side; retain UTC audit timestamps and configured local presentation.

### Acceptance criteria

Separate local inputs round-trip deterministically, preserve invalid submissions, and use local calendar boundaries consistently.

## MT-09 — Scorecard fields and innings structure

**Current status:** NEEDS_DECISION

### Evidence

Only result and free-text score_summary exist; no scorecard/innings tables.

### Planned impact after approval

- Routes/controllers/services: Draft entry, verification, lock, correction.
- Models/migrations: Scorecard, innings, delivery/summary, and version tables.
- Views: Scorecard editor/detail/history.
- Permissions: Scorecard entry/review/correction permissions if approved.
- Tests: Unit format validation; Feature authorization/CSRF; database persistence, lock, correction, audit.

### Dependencies and branch scope

MT-02, MT-04, MT-10, MT-11, MT-15, PF-01–PF-04.

### Security/audit

No scorecard bypass; lock/verification server-side; sensitive values escaped; complete audit.

### Acceptance criteria

Approved scorecard structure, innings rules, lock point, correction process, and audit are enforced.

## MT-10 — Batting, bowling, fielding, and extras data

**Current status:** NEEDS_DECISION

### Evidence

No metric storage or formulas exist.

### Planned impact after approval

- Services/models/migrations: Metric source records, units, validation, approval markers, versions.
- Views/routes: Authorized metric entry/review.
- Permissions: Performance/scorecard permissions per decision.
- Tests: Unit formulas/edge cases; Feature visibility; database source/correction/recalculation.

### Dependencies and branch scope

MT-09, MT-13, PF-01–PF-04, branch/Player scope.

### Security/audit

Reject invalid/incomparable values; corrections require reason and append-only audit; no fabricated metrics.

### Acceptance criteria

Approved metrics and formulas are versioned, reproducible, branch-scoped, and corrected safely.

## MT-11 — Result and winner calculation

**Current status:** NEEDS_DECISION

### Evidence

Result allowlist is won/lost/draw/no_result; no winner calculation or scorecard verification exists.

### Planned impact after approval

- Service/result calculator and transition validation.
- Models/migrations for verification/derived result if needed.
- Views for result and winner presentation.
- Tests for calculations, edge cases, correction, and authorization.

### Dependencies and branch scope

MT-09, MT-10, MT-12, MT-15.

### Security/audit

Only approved source data may determine winners; manual override requires authority, reason, and audit.

### Acceptance criteria

Result/winner values match approved scorecard inputs and all exceptional outcomes are handled.

## MT-12 — Tie, abandoned, rain-cancelled, and no-result handling

**Current status:** NEEDS_DECISION

### Evidence

no_result exists but exceptional outcome workflow does not. cancelled is directly editable.

### Planned impact after approval

- Lifecycle routes/service/status history.
- Schema fields for outcome/reason/weather/event time if required.
- Views/badges/alerts.
- Tests for each outcome, downstream attendance/performance/report impact.

### Dependencies and branch scope

MT-02, MT-09–MT-11, MT-14.

### Security/audit

Outcome changes require authorized branch action, reason where approved, transaction, and audit.

### Acceptance criteria

Exceptional outcomes cannot be misreported as wins/losses and have approved downstream effects.

## MT-13 — Player and Coach performance integration

**Current status:** NEEDS_DECISION

### Evidence

Match stores no performance records; Player 360 lists participation; performance formulas/source grain remain unresolved.

### Planned impact after approval

- Performance source service/model and Match linkage.
- Coach KPI service/profile views.
- Approval markers, permissions, branch queries, and migrations if required.
- Unit formula tests, Feature visibility tests, database source/correction/recalculation tests.

### Dependencies and branch scope

PF-01–PF-06, PII-v1, Coach KPI decisions, MT-09/10.

### Security/audit

Only approved source records; branch and KPI permission checks; corrections recalculate and audit.

### Acceptance criteria

No KPI is fabricated; source approval, visibility, formula versioning, and correction propagation are verifiable.

## MT-14 — Attendance integration

**Current status:** NEEDS_DECISION

### Evidence

match_players stores selection only. Attendance snapshots and AttendanceService currently apply to Sessions.

### Planned impact after approval

- Attendance routes/service integration or a separate Match attendance workflow.
- Snapshot/attendance models and migrations if required.
- Match detail and attendance views.
- Permissions and tests for roster, statuses, correction, branch scope, CSRF, audit.

### Dependencies and branch scope

AT-01–AT-06, MT-03, MT-05, Session Attendance boundaries.

### Security/audit

Immutable roster boundary if approved; no reuse without confirming entity/session semantics; preserve existing Session behavior.

### Acceptance criteria

Match attendance is isolated or explicitly shared according to the decision; status, correction, denominator, and audit rules are complete.

## MT-15 — Corrections, approval, locking, and audit history

**Current status:** NEEDS_DECISION

### Evidence

All edits use match.update; central audit stores before/after summaries; no Match correction permission or lock exists.

### Planned impact after approval

- Transition/correction routes and thin controllers.
- Service transaction/version/lock logic.
- Models/migrations for state/history.
- Permission catalog and grants only if approved.
- Unit authorization/transition tests; Feature CSRF/visibility tests; database rollback/idempotency/audit tests.

### Dependencies and branch scope

MT-02, MT-05, MT-09–MT-14, OPS-01.

### Security/audit

Enforce server-side branch and permission checks; record actor, timestamp, branch, old/new values, reason, and Match ID; never delete audit history.

### Acceptance criteria

Approved records lock correctly, corrections are reasoned and append-only, retries are safe, and every action is auditable.

## MT-16 — Reports, exports, and visibility

**Current status:** NEEDS_DECISION

### Evidence

ReportService has a basic branch-filtered Matches query; no Match-specific export route or policy exists. Dashboard exposes scheduled-match activity/count.

### Planned impact after approval

- ReportService, report routes/controllers/views, export response layer.
- Optional persisted summary migration only if approved.
- Permission catalog for report/export visibility if needed.
- Unit query/filter contracts; Feature authorization/export tests; database branch/filter correctness.

### Dependencies and branch scope

MT-02, MT-13, MT-15, OPS-06, DASH-01–DASH-03.

### Security/audit

Exports must use identical server-side branch scope and approved columns; sensitive fields need explicit policy; audit exports if approved.

### Acceptance criteria

Report rows, filters, exports, role visibility, and branch scope match the decision exactly.

## MT-17 — Permissions and role access

**Current status:** NEEDS_DECISION

### Evidence

Match migration and AuthorizationRestoreSeeder grant match.view, match.create, and match.update to Super Admin, Branch Admin, and Coach. No narrow permissions exist for selection, scorecards, correction, completion, cancellation, or exports.

### Planned impact after approval

- Permission catalog/admin UI and migration/seeder.
- Routes and service checks for each action.
- Navigation/view flags.
- Unit permission contracts; Feature authorized/denied routes; database multi-role/branch tests.

### Dependencies and branch scope

RBAC-SA-01; SEC-02/09/13/14; MT-03/05/15/16.

### Security/audit

Centralized permissions are mandatory; hidden buttons are not authorization; current assignments must be revalidated on every request.

### Acceptance criteria

Every approved action has a narrow permission and branch rule; unauthorized, cross-branch, and multi-role behavior is tested.

## MT-18 — Retention and historical recalculation

**Current status:** NEEDS_DECISION

### Evidence

Match timestamps and central audit exist; no Match retention, archive, version, lock, or recalculation policy exists.

### Planned impact after approval

- Services/jobs or explicit synchronous recalculation workflows.
- Version/archive fields/tables and migrations if required.
- Reports/KPI source queries and history views.
- Unit recalculation rules; Feature authorization; database retention/version/reproducibility tests.

### Dependencies and branch scope

OPS-01/02/10; PF-04/PF-06; MT-04, MT-13, MT-15.

### Security/audit

Preserve historical source and audit lineage; branch scope applies to current and historical reads; no destructive deletion without approved policy.

### Acceptance criteria

Approved retention is enforced, historical calculations are reproducible by version, and corrections follow the approved recalculation policy.

---

## Recommended implementation sequence after approval

1. Reconcile MT-01 through MT-18 into REQUIREMENTS.md, DECISIONS.md, ARCHITECTURE.md, DATABASE.md, and RBAC.md. Record only explicit approvals.
2. Confirm migration order and deployment ownership for the existing Match migration and any new schema.
3. Harden the existing CRUD slice first: active/eligible option filtering, configured separate date/time handling, field-level validation, safe error redirects, and Feature coverage.
4. Implement branch/participant and lifecycle foundations only after MT-02/MT-03/MT-05/MT-15 are approved.
5. Add scorecard and metric storage only after MT-09/MT-10/MT-11/MT-12 are approved.
6. Integrate performance, attendance, reports, and exports only after MT-13/MT-14/MT-16 are approved.
7. Apply retention and recalculation only after MT-18 is approved.

No step authorizes implementation while its prerequisite remains NEEDS_DECISION.

## Global acceptance and deployment evidence

Before any approved slice is released:

- Explicit routes remain in use; auto-routing is not enabled.
- Controllers remain thin and workflows live in services.
- Every write has CSRF protection, centralized permission checks, branch isolation, server-side validation, transaction behavior where related writes must be atomic, and audit records.
- No sensitive data is exposed in list/detail/export output without an approved visibility rule.
- Unit, Feature, and database tests cover authorized and denied paths, forged IDs, cross-branch access, validation preservation, rollback, and audit fields.
- Migration status, role grants, required seed fixtures, application timezone configuration, Scheduling dependencies, and deployment-owner evidence are verified before release.
- No migration, seed, destructive cleanup, production change, or database modification is implied by this planning document.
