# Coach Management and Attendance Requirements and Decision Brief

**Date:** 2026-09-25  
**Purpose:** Read-only requirements and implementation audit for the user-provided Coach Management and Attendance scope.  
**Direction status:** The supplied requirements are recorded here as user direction only. They are not client approval and do not authorize code, schema, permission, or migration changes.  
**Files changed by this brief:** This new file only. Existing code, tests, migrations, decisions, and `docs/SRS-original.md` were not modified.

## Source and governance notes

- `AGENTS.md` requires the relevant SRS and approved decisions to be reviewed before implementation, centralized authorization, server-side branch isolation, migrations for schema changes, audit logging, focused tests, and no invented business rules.
- `docs/SRS-original.md` is not present in the repository. No replacement SRS was reconstructed for this brief.
- `docs/REQUIREMENTS.md`, `docs/DECISIONS.md`, `docs/ARCHITECTURE.md`, `docs/DATABASE.md`, `docs/RBAC.md`, `docs/MODULE-STATUS.md`, and `docs/PROJECT-CODEBASE-AND-REQUIREMENTS-MAP.md` were inspected as working records. Historical status tables are treated as historical evidence, not approval.
- The repository contains existing Coach and Attendance implementations. Their existence does not approve any additional supplied field, formula, threshold, permission, visibility, or alert policy.
- No tests were run during this audit.

## Executive summary

### Coach Management

An existing Coach module supports branch-scoped listing, detail, create-profile, and edit-profile flows for users who already have the `Coach` role. Existing profile storage covers specialization, experience years, qualification, certifications, availability, and an active flag. Existing detail pages show assigned Players, assigned Sessions, upcoming leave/unavailability, and a KPI summary.

The current KPI implementation calculates sessions conducted, assigned players, and a roster-recording compliance percentage. Player feedback rating and performance improvement index are returned as `null`. Leave and session assignment are displayed from existing records but are not complete Coach-management workflows. Exact field semantics, lifecycle, permissions, and KPI formulas remain unresolved for the supplied scope.

### Attendance

An existing Attendance source-event workflow supports session lists, persisted roster snapshots, recording one event per eligible player/session, correction with a reason and optimistic version, append-only history, audit, CSRF-protected routes, branch checks, and UTC storage with MCC timezone presentation.

The current implementation accepts `PRESENT`, `ABSENT`, `LATE`, and `EXCUSED`. The supplied statuses `Leave`, `Medical`, `Rain Cancelled`, and `Coach Cancelled` are not implemented. The recorded AT-02 decision proposes the existing four-status vocabulary only, while AT-06 explicitly defers derived calculations, thresholds, reports, alerts, and recipients. The supplied percentages and alerts therefore require decision reconciliation before coding.

## Existing implementation inventory

### Coach Management evidence

| Capability | Current implementation and evidence | Current gaps or limitations | Status |
|---|---|---|---|
| Coach directory | `app/Modules/Coaches/Config/Routes.php` and `Controllers/Browser.php` provide `GET /coaches`; `CoachManagementService::list()` filters authorized branches and Coach-role users. `Views/index.php` renders filters, status, branch, assigned-player/session counts, and actions. | No client-approved supplied scope for additional directory fields, exports, or KPI reporting. | Partially implemented |
| Coach profile storage | Migration `app/Database/Migrations/20260903000100_CreateCoachProfiles.php` creates `coach_profiles` with `specialization`, `experience_years`, `qualification`, `certifications`, `availability`, `is_active`, timestamps, and a unique `user_id`. `CoachManagementService::saveProfile()` validates current text/integer constraints. | Field types and meanings in the supplied scope need approval. “Availability” and “Leave” are not defined as one model or separate rules. | Implemented for existing limited profile scope; supplied expansion NEEDS_DECISION |
| Create/edit profile | Explicit GET/POST routes and `CoachManagementService::saveProfile()` support create and update. Routes use `coach.create` and `coach.update`; service rechecks branch authorization and Coach-role eligibility. | The exact creator/updater roles, branch-selection semantics, profile lifecycle, and deactivation rules for the new scope are not fully recorded. | Implemented with policy gaps |
| Assigned Branch | `user_branches` is the existing branch assignment source. `CoachManagementService` lists only current authorized branches and displays `All Authorized Branches` for multi-branch users. BR-03 approves coaches assigned to multiple authorized branches; BR-04 makes branch-owned records the default. | Whether profile fields are global or branch-specific, who may add/remove branch assignments, and behavior when a branch closes or assignment changes need decisions. | Existing dependency; expansion NEEDS_DECISION |
| Assigned Sessions | `CoachManagementService::sessions()` reads branch-scoped `sessions` for the Coach. Scheduling uses `SCH-01`–`SCH-05`; `SCH-05` records availability/leave precedence for scheduling. | No separate Coach session-assignment workflow is present. Session ownership, reassignment, and Coach-specific visibility must not be inferred from the read-only list. | Read-only evidence only |
| Leave | `CoachManagementService::leave()` reads future `coach_unavailability` rows. `SCH-05` references availability/leave in scheduling. | No Coach leave create/edit/cancel route was found in the inspected Coach module. Reason, overlap, approval, timezone, and precedence rules need decisions for the supplied requirement. | Read-only evidence only |
| Coach KPIs | `CoachManagementService::kpis()` calculates sessions conducted, assigned players, and recorded-roster compliance. It returns `null` for `player_feedback_rating` and `performance_improvement_index`. | All supplied KPI definitions, source data, formulas, periods, rounding, missing-data rules, visibility, and correction/recalculation behavior are unresolved. | Partially implemented; two KPIs blocked |
| Permissions and branch checks | `AdminService` catalogs `coach.view`, `coach.create`, `coach.update`, `coach.assign`, and `coach.kpi.view`. Coach routes use permission filters; service methods recheck authorized branches. Player assignment uses `player.coach.assign`. | Exact role grants, separation of profile/branch/session/leave/KPI permissions, and visibility for Coach versus staff roles need confirmation. | Existing controls; scope expansion NEEDS_DECISION |
| Tests | `tests/Unit/CoachManagementModuleContractTest.php` and `tests/database/CoachManagementSafeTest.php` provide source/branch-scope evidence. Tests were not run for this audit. | Coverage for supplied field semantics, leave writes, session assignment changes, KPI formulas, and sensitive visibility is not established. | Needs verification |

### Attendance evidence

| Capability | Current implementation and evidence | Current gaps or limitations | Status |
|---|---|---|---|
| Session/roster list | `app/Modules/Attendance/Config/Routes.php`, `Controllers/Attendance.php`, `AttendanceService::sessionPage()`, and `Views/index.php` provide branch-scoped filters and session/roster counts. | No percentage dashboard or alert view is implemented. | Implemented for source-event list |
| Roster snapshot | `AttendanceService::captureRosterSnapshot()` and migrations `202609020001_CreateAttendanceRosterSnapshots.php` plus deployment variant create immutable snapshots and members with uniqueness and no-update/no-delete triggers. | Snapshot amendment behavior when assignments change remains governed by AT-01/AT-04 decisions and is not expanded by this brief. | Implemented for approved source scope |
| Record attendance | `AttendanceService::record()` requires `attendance.record`, authorized session branch, non-cancelled session, persisted roster eligibility, snapshot readiness, and unique `(session_id, player_id)`. | Current statuses are `PRESENT`, `ABSENT`, `LATE`, `EXCUSED`; supplied Leave/Medical/Rain Cancelled/Coach Cancelled are not available. | Implemented for existing status scope; supplied vocabulary conflicts |
| Correct attendance | `AttendanceService::correct()` requires `attendance.correct`, a reason, branch authorization, status validation, and expected version. `AttendanceHistoryModel` is append-only; audit records are written. | Correction windows, who may correct after lock, and treatment of new absence/leave statuses need decisions. | Implemented for existing status scope |
| Cancellation boundary | `SCH-04`, AT-01, and AT-04 require cancelled sessions to create no attendance records and be excluded from attendance denominators. | The supplied `Rain Cancelled` and `Coach Cancelled` are event/status labels, not currently session-level statuses. Their relationship to `sessions.status` needs an explicit decision. | Conflict / NEEDS_DECISION |
| Percentages | No daily, weekly, monthly, or yearly percentage calculation was found in `AttendanceService`, views, or the inspected requirements evidence. | Denominator membership, leave/medical/cancellation treatment, date boundaries, timezone, missing data, rounding, and historical recalculation are unresolved. | Not implemented; blocked |
| Alerts | No below-75%, continuous-absence, or late-arrival alert workflow, recipient policy, scheduler, or notification implementation was found. AT-06 defers this behavior. | Threshold period, continuous absence definition, late definition, recipients, channels, deduplication, acknowledgement, and audit are unresolved. | Not implemented; blocked |
| Permissions and branch checks | `attendance.view`, `attendance.record`, and `attendance.correct` are cataloged and checked in routes and service methods. Branch scope is derived server-side from the session and authorized branches. | Future KPI/report/alert permissions and role visibility need approval. | Existing source-event controls |
| Tests | `tests/Unit/AttendanceModuleContractTest.php`, `tests/database/AttendanceSafeWorkflowTest.php`, and legacy `AttendanceIntegrationTest.php` exist. `docs/REQUIREMENTS.md` records safe source-event coverage; tests were not run here. | No focused coverage exists for supplied statuses, calculations, alerts, recipients, or historical recalculation. Legacy destructive suites must not be used against persistent test data without review. | Needs verification / blocked scope |

## Supplied requirements recorded as user direction

The following are recorded exactly as requested, without interpreting them as approved policy:

- Coach data requested: Experience, Qualification, Certifications, Availability, Specialization, Leave, Assigned Branch, and Assigned Sessions.
- Coach KPI names requested: Sessions conducted, Attendance compliance, Player feedback rating, Assigned players, and Performance improvement index.
- Attendance status names requested: Present, Absent, Leave, Medical, Late, Rain Cancelled, and Coach Cancelled.
- Attendance percentages requested: daily, weekly, monthly, and yearly.
- Attendance alerts requested: below 75%, continuous absence, and late arrivals.

These names alone do not define storage types, formulas, permissions, transitions, privacy, date boundaries, or acceptance criteria.

## Decisions required before implementation

The IDs below are brief item identifiers only; they do not add records to `docs/DECISIONS.md`. Existing decision IDs are cited in each source column. Every item remains `NEEDS_DECISION` until an authorized approver records a decision in the project decision records.

### Coach Management decisions

#### CM-01 — Profile field semantics and validation (`NEEDS_DECISION`)

**Source:** User-provided Coach Management list; existing `coach_profiles` migration; `CoachManagementService::saveProfile()`; `PA-02`; `SCH-05`; `REQ-PLAYER-001`.

Decide the type, maximum length, structure, units, allowed values, required/optional status, escaping/search behavior, and update history for Experience, Qualification, Certifications, Availability, Specialization, and Leave. Decide whether Leave is a separate dated entity or a profile field, whether multiple records are allowed, whether overlapping leave is valid, and whether free text is permitted.

**Response:** Approved / Rejected / Needs discussion  
**Approver:** ____________________  **Date:** ____________________  
**Comments / exact resolution:**

____________________________________________________________________

#### CM-02 — Coach identity, role, profile lifecycle, and branch assignment (`NEEDS_DECISION`)

**Source:** BR-03, BR-04, BR-06; `CoachManagementService::eligibleUsers()`, `eligibleUser()`, `user_branches`, `coach.*` permissions; `RBAC.md`.

Decide who may create a Coach profile for an existing user, whether a Coach profile may exist without the Coach role, who may activate/deactivate it, and whether profile data is global or branch-specific. Decide who can add/remove assigned branches, whether multiple active branches remain allowed, behavior for inactive/closed branches, and whether a submitted branch must always be selected from current server-authorized assignments.

**Response:** Approved / Rejected / Needs discussion  
**Approver:** ____________________  **Date:** ____________________  
**Comments / exact resolution:**

____________________________________________________________________

#### CM-03 — Session assignment and scheduling relationship (`NEEDS_DECISION`)

**Source:** User-provided Assigned Sessions; `SCH-01`–`SCH-05`; `PA-02`; `CoachManagementService::sessions()`; `SchedulingService`.

Decide whether sessions are assigned to Coaches only through session creation/update, whether a separate Coach assignment workflow is required, who may assign/reassign, whether one or multiple Coaches are allowed, and how reassignment affects existing rosters, attendance, conflicts, history, and audit. Confirm that scheduling’s approved one-time session and cancellation/rescheduling rules remain authoritative.

**Response:** Approved / Rejected / Needs discussion  
**Approver:** ____________________  **Date:** ____________________  
**Comments / exact resolution:**

____________________________________________________________________

#### CM-04 — Leave and availability workflow (`NEEDS_DECISION`)

**Source:** User-provided Leave/Availability; `SCH-05`; `coach_unavailability` reads in `CoachManagementService`; `SchedulingService` availability checks.

Decide who may create, approve, edit, cancel, or override leave; required dates/times, timezone, reason, and status; overlap and partial-day behavior; precedence between leave, availability, and session assignment; and treatment of sessions already scheduled during leave. Decide whether leave is branch-scoped and how multi-branch Coaches are handled.

**Response:** Approved / Rejected / Needs discussion  
**Approver:** ____________________  **Date:** ____________________  
**Comments / exact resolution:**

____________________________________________________________________

#### CM-05 — Coach KPI formulas, sources, and visibility (`NEEDS_DECISION`)

**Source:** User-provided KPI list; `CoachManagementService::kpis()`; `REQ-PLAYER-001`; `REQ-SESSION-001`; `AT-01`–`AT-06`; `PF-01`–`PF-06`; `docs/REQUIREMENTS.md` calculation rule.

For each KPI, approve the formula, source tables/events, period, denominator, timezone/date boundary, missing-data behavior, precision/rounding, correction/recalculation behavior, and versioning:

- Sessions conducted
- Attendance compliance
- Player feedback rating
- Assigned players
- Performance improvement index

Specifically define the feedback instrument and scale, who may submit/correct feedback, the performance source and formula, and whether a KPI is branch-scoped, Coach-scoped, or multi-branch aggregated. Decide which roles may view each KPI and whether Coaches may view their own KPIs.

**Response:** Approved / Rejected / Needs discussion  
**Approver:** ____________________  **Date:** ____________________  
**Comments / exact resolution:**

____________________________________________________________________

#### CM-06 — Coach audit, correction, and history (`NEEDS_DECISION`)

**Source:** OPS-01; PA-02; existing `AuditPersistenceService`; Coach profile migration/service.

Decide which profile, branch, session, leave, availability, and KPI changes require append-only history; required actor, timestamp, previous/new values, reason, source, and branch fields; correction authority; optimistic locking; retention; and whether historical KPI values are immutable snapshots or recalculated under a versioned formula.

**Response:** Approved / Rejected / Needs discussion  
**Approver:** ____________________  **Date:** ____________________  
**Comments / exact resolution:**

____________________________________________________________________

### Attendance decisions

#### AT-R1 — Status vocabulary conflict and status transitions (`NEEDS_DECISION`)

**Conflict:** The supplied direction requests `PRESENT`, `ABSENT`, `LEAVE`, `MEDICAL`, `LATE`, `RAIN_CANCELLED`, and `COACH_CANCELLED`. Existing `AttendanceService`, views, and tests use `PRESENT`, `ABSENT`, `LATE`, and `EXCUSED`. Existing AT-02 records a proposed four-status baseline and says leave/medical treatment must not be collapsed without policy. The user list therefore conflicts with AT-02 and requires reconciliation.

**Source:** User-provided Attendance list; AT-02; `AttendanceService::STATUSES`; Attendance views; `REQ-ATT-001`.

Decide the canonical status codes and labels, whether Leave and Medical are attendance statuses or absence reasons, whether Rain Cancelled and Coach Cancelled belong to the Session status, whether transitions are allowed after recording, and who may create or correct each status. Decide whether medical details are stored (the current AT-02 baseline says no medical detail in Attendance).

**Response:** Approved / Rejected / Needs discussion  
**Approver:** ____________________  **Date:** ____________________  
**Comments / exact resolution:**

____________________________________________________________________

#### AT-R2 — Eligibility, denominator, and cancellation treatment (`NEEDS_DECISION`)

**Source:** AT-01 and AT-04; `SCH-04`; roster snapshot migrations; user-provided percentage and status requirements.

Decide whether the denominator is persisted roster membership, current assignment, or another approved event set. Decide how Leave, Medical, Rain Cancelled, Coach Cancelled, cancelled sessions, late arrivals, removed players, and players added after scheduling affect numerator and denominator. Define whether a zero-eligible-period result is `0%`, `N/A`, or excluded.

**Response:** Approved / Rejected / Needs discussion  
**Approver:** ____________________  **Date:** ____________________  
**Comments / exact resolution:**

____________________________________________________________________

#### AT-R3 — Recording, correction, lock, and history policy (`NEEDS_DECISION`)

**Source:** AT-03 and AT-05; `AttendanceService::record()`/`correct()`; `AttendanceHistoryModel`; `attendance.record` and `attendance.correct`.

Decide who may record attendance, when recording opens and closes, whether a Coach has authority separate from branch staff, whether corrections remain possible after lock, required correction reason, stale-write behavior, and all history/audit fields. Confirm whether status changes are append-only events, superseding corrections, or mutable current state with immutable history.

**Response:** Approved / Rejected / Needs discussion  
**Approver:** ____________________  **Date:** ____________________  
**Comments / exact resolution:**

____________________________________________________________________

#### AT-R4 — Daily, weekly, monthly, and yearly calculation boundaries (`NEEDS_DECISION`)

**Source:** User-provided percentage requirements; OPS-09; `docs/REQUIREMENTS.md` calculation rule; AT-01/AT-03/AT-06.

For each period, define calendar versus rolling windows, the timezone (the existing source-event contract presents MCC time and stores UTC), start/end inclusivity, week start day, month/year calendar, daylight-saving handling if relevant, branch-local date interpretation, missing sessions, and rounding/precision. Decide whether historical values are recalculated after corrections and which formula version is retained.

**Response:** Approved / Rejected / Needs discussion  
**Approver:** ____________________  **Date:** ____________________  
**Comments / exact resolution:**

____________________________________________________________________

#### AT-R5 — Below-75% alert (`NEEDS_DECISION`)

**Source:** User-provided alert requirement; AT-06; `NOT-01`–`NOT-03`; `REP-01`–`REP-03`; `DASH-01`–`DASH-03`.

Define the period evaluated, denominator and status treatment, whether the threshold is strictly below 75%, when evaluation occurs, deduplication/cooldown, recipients by role and branch, channel, content/privacy, acknowledgement/escalation, audit, and formula version.

**Response:** Approved / Rejected / Needs discussion  
**Approver:** ____________________  **Date:** ____________________  
**Comments / exact resolution:**

____________________________________________________________________

#### AT-R6 — Continuous absence (`NEEDS_DECISION`)

**Source:** User-provided alert requirement; AT-05 and AT-06.

Define “continuous absence,” including the minimum consecutive event count, calendar/session window, treatment of Leave and Medical, cancelled sessions, late arrivals, holidays/no sessions, and corrections. Define evaluation timing, recipients, delivery channel, duplicate suppression, escalation, audit, and historical recalculation.

**Response:** Approved / Rejected / Needs discussion  
**Approver:** ____________________  **Date:** ____________________  
**Comments / exact resolution:**

____________________________________________________________________

#### AT-R7 — Late-arrival definition and alert (`NEEDS_DECISION`)

**Source:** User-provided alert requirement; AT-02; `SCH-01`–`SCH-05`; OPS-09.

Define whether Late is manually recorded or derived from an arrival timestamp, the grace period and timezone, whether late counts as present, alert threshold and period, recipients/channels, correction rules, and audit/history requirements.

**Response:** Approved / Rejected / Needs discussion  
**Approver:** ____________________  **Date:** ____________________  
**Comments / exact resolution:**

____________________________________________________________________

#### AT-R8 — Attendance permissions, visibility, and branch scope (`NEEDS_DECISION`)

**Source:** Existing `attendance.view`, `attendance.record`, `attendance.correct`; BR-03/BR-04/BR-06; RBAC records; AT-03/AT-06.

Decide which roles may view source events, percentages, alerts, and historical calculations; whether Coaches see only assigned sessions/players or all authorized branch records; whether Branch Admins see all current branch records; whether Super Admin access is limited to explicitly authorized branches; and which future permissions are needed for reports, KPI views, alerts, acknowledgement, and overrides. Do not infer grants from navigation or existing session permissions.

**Response:** Approved / Rejected / Needs discussion  
**Approver:** ____________________  **Date:** ____________________  
**Comments / exact resolution:**

____________________________________________________________________

#### AT-R9 — Attendance audit, locking, and historical recalculation (`NEEDS_DECISION`)

**Source:** OPS-01; AT-01/AT-03/AT-05/AT-06; existing Attendance history/audit implementation.

Decide the lock boundary, correction and override audit requirements, formula/configuration versioning, whether past daily/weekly/monthly/yearly results are frozen or recalculated, how corrections propagate to alerts and Coach KPIs, and the required evidence for concurrency and deployment behavior.

**Response:** Approved / Rejected / Needs discussion  
**Approver:** ____________________  **Date:** ____________________  
**Comments / exact resolution:**

____________________________________________________________________

## Conflicts and reconciliation items

1. **Original SRS unavailable:** `AGENTS.md` identifies `docs/SRS-original.md` as authoritative, but the file is absent. This brief does not reconstruct it.
2. **AGENTS.md versus current status records:** `AGENTS.md` says no business module is approved for implementation until requirements and architecture are reviewed. Current `REQUIREMENTS.md` and `MODULE-STATUS.md` record bounded Coach, Scheduling, and Attendance implementations. The current source is implementation evidence, not proof that the new supplied scope is approved.
3. **Attendance statuses:** User direction conflicts with AT-02 and current code. `EXCUSED` versus Leave/Medical and session cancellation labels require an authorized resolution.

## User-direction implementation record (2026-09-25)

The supplied implementation direction is recorded here as user direction and is not client approval. It authorizes the defined Coach and Attendance slice: optional text-based Coach profile fields using the existing `coach_profiles` record; multiple existing authorized branch assignments; session access only within those assignments; the seven attendance statuses `PRESENT`, `ABSENT`, `LEAVE`, `MEDICAL`, `LATE`, `RAIN_CANCELLED`, and `COACH_CANCELLED`; a forward-only `EXCUSED` to `LEAVE` history mapping; local-calendar percentage calculations that exclude cancelled sessions and Leave/Medical from the denominator; and in-app/report-ready alerts for authorized branch staff.

Feedback-rating and performance-improvement source data, external alert delivery, detailed recipient matrices, correction propagation, and any broader lifecycle rules remain `NEEDS_DECISION`. The implementation must not change `docs/SRS-original.md` or imply client approval.
4. **Attendance calculations and alerts:** User direction conflicts with AT-06, which defers thresholds, denominators, formulas, reports, alerts, and recipients. No code may be added for these until the decisions above are recorded.
5. **Coach assignment records:** PA-02 is marked `APPROVED` and contains an “Approved baseline,” but also retains “Decision required” and “Options requiring approval” language. Client confirmation is needed before expanding Coach assignment, leave, or session rules.
6. **Historical status tables:** `MODULE-STATUS.md` and traceability documents contain dated implementation claims. They must remain labeled historical/current evidence and must not be used as approval of unresolved KPI, status, or alert policy.

## Recommended first safely implementable slice

Do not start the supplied KPI, alert, or new Attendance-status work. First obtain an authorized decision for **AT-R1** (status vocabulary/cancellation model) and **AT-R2** (eligibility and denominator), because they affect storage, validation, reports, corrections, and every percentage. In parallel, reconcile **CM-01** and **CM-02** for Coach field semantics, lifecycle, branch assignment, and role grants.

After those decisions are recorded, the smallest implementation slice should be one approved source-event change with its migration (if needed), centralized permission checks, branch-safe service validation, audit/history behavior, focused tests, and traceability update. Derived percentages, Coach feedback/performance KPIs, and alerts should remain deferred until AT-R4–AT-R9 and CM-05/CM-06 are approved.
