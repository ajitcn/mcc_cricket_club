# Coach Management — Remaining Decisions Request

**Prepared:** 2026-09-25  
**Status:** `USER_PROVIDED_DIRECTION — IMPLEMENTATION_AUTHORIZED_FOR_SCOPED_WORK`  
**Purpose:** Obtain the decisions required before the remaining Coach Management workflows and unresolved KPIs can be implemented or expanded.  
**Scope:** Coach branch assignments, session assignment changes, leave/availability workflows, audit/history, feedback rating, performance improvement index, and KPI governance.  
**Approval meaning:** A response approves only the exact item and scope recorded by the authorized approver. It does not approve unrelated Coach, Attendance, Scheduling, Player, or reporting behavior.

## Evidence and current boundary

The current Coach implementation provides:

- Profile fields in `coach_profiles`: `experience_years`, `qualification`, `certifications`, `availability`, `specialization`, and `is_active`.
- Current branch assignments from `user_branches`.
- Assigned-session reads from `sessions`.
- Upcoming leave/unavailability reads from `coach_unavailability`.
- Source-backed KPIs for sessions conducted, attendance compliance, and assigned players in `CoachManagementService::kpis()`.
- Permission checks using `coach.view`, `coach.create`, `coach.update`, `coach.assign`, and `coach.kpi.view`.

The Coach module does not currently provide Coach-specific branch assignment writes, session assignment/reassignment writes, or leave/availability writes. Player-to-Coach assignment remains in the approved `player.coach.assign` workflow, and session Coach assignment remains in the Scheduling workflow.

The existing records below are evidence and boundaries; they do not resolve the decisions requested here:

- `docs/COACH-ATTENDANCE-REQUIREMENTS-DECISION-BRIEF.md` — CM-02 through CM-06.
- `docs/COACH-ATTENDANCE-FIRST-SLICE-APPROVAL-REQUEST.md` — Coach field, branch, and KPI questions.
- `docs/DECISIONS.md` — BR-03, BR-04, PA-02, SCH-01 through SCH-05, and the 2026-09-25 Coach/Attendance user-direction reconciliation.
- `docs/REQUIREMENTS.md` — Coach/Attendance user-direction amendment and KPI policy boundary.
- `app/Services/CoachManagementService.php` — current queries, validation, branch checks, and KPI sources.
- `app/Modules/Coaches/Config/Routes.php` and `app/Modules/Coaches/Controllers/Browser.php` — current Coach routes and profile workflow.

## Instructions for the authorized approver

Record only an explicit decision for each item. If the rule is not ready, select `NEEDS_DECISION` and leave the comments explaining what remains open.

**Approver:** User-provided direction; authorized approver identity not recorded  
**Decision date:** 2026-09-25 (user direction; not client approval)

## Reconciled user-provided direction

The completed direction below is recorded exactly as supplied by the user. It authorizes only the listed Coach Management scope and does not claim client approval of the wider requirements baseline. Any behavior not stated here remains `NEEDS_DECISION`.

---

## CM-02 — Coach branch-assignment management

**Current status:** `USER_PROVIDED_DIRECTION — IMPLEMENTATION_AUTHORIZED_FOR_SCOPED_WORK`  
**Sources:** CM-02; BR-03; BR-04; BR-06; `user_branches`; `CoachManagementService::eligibleUsers()` and `find()`; `docs/RBAC.md`.

The current profile reads authorized branch assignments but does not provide Coach branch-assignment management. Decide:

- Who may add, remove, or replace a Coach’s branch assignment.
- Whether a Coach may have one or multiple active branches. BR-03 records that multiple authorized branches are allowed, but does not define the write workflow.
- Whether Coach profile data is global or branch-specific.
- How inactive or closed branches affect existing Coach assignments.
- Whether assignment changes require effective dates, reasons, optimistic locking, history, and audit.
- Which existing centralized permission controls the operation, and whether `coach.assign` is the intended permission.

**Recorded direction:** A Coach may have multiple active branches. Super Admins may assign only within their authorized branches; Branch Admins may assign only within their authorized branches. Removal is allowed only when historical records remain valid. Sessions, attendance, and KPI history retain their original branch. Assignment changes record actor, timestamp, previous branches, new branches, and reason in audit history.  
**Decision status:** User-provided direction; not client approval.  
**Exact resolution:**

____________________________________________________________________

**Comments:**

____________________________________________________________________

**Approver:** ______________________________  **Date:** ______________

---

## CM-03 — Coach session assignment and reassignment

**Current status:** `USER_PROVIDED_DIRECTION — IMPLEMENTATION_AUTHORIZED_FOR_SCOPED_WORK`  
**Sources:** CM-03; SCH-01–SCH-05; PA-02; `SchedulingService`; `CoachManagementService::sessions()`; `sessions.coach_user_id`.

The Scheduling workflow already selects and validates a Coach for a session. The Coach module has no separate assignment or reassignment workflow. Decide:

- Whether session assignment is managed only through session create/update or also from Coach screens.
- Who may assign or reassign a Coach.
- Whether one or multiple Coaches may be assigned to a session.
- How reassignment affects existing rosters, attendance, conflicts, availability, leave, history, and audit.
- Whether the existing scheduling permissions remain authoritative or a documented existing Coach permission applies.
- Whether assignment changes require a reason, effective timestamp, optimistic locking, and transaction boundaries.

**Recorded direction:** Super Admins and authorized Branch Admins may assign or reassign Coaches only within permitted session branches. Reassignment is allowed before session start. After start, reassignment requires an authorized correction action and reason. Historical attendance and KPI records retain session and branch history. Every change is audited.  
**Decision status:** User-provided direction; not client approval.  
**Exact resolution:**

____________________________________________________________________

**Comments:**

____________________________________________________________________

**Approver:** ______________________________  **Date:** ______________

---

## CM-04 — Leave and availability workflow

**Current status:** `USER_PROVIDED_DIRECTION — IMPLEMENTATION_AUTHORIZED_FOR_SCOPED_WORK`  
**Sources:** CM-04; SCH-05; `coach_unavailability`; `SchedulingService::validateCoachAvailabilityAndConflicts()`; Coach profile `availability` field.

The current Coach profile stores availability notes and reads upcoming `coach_unavailability` rows. No Coach leave create, edit, cancel, or approval route exists. Decide:

- Whether availability remains free text, becomes structured availability, or uses another existing source.
- Required date/time fields, timezone, reason, status, and partial-day behavior.
- Who may create, approve, edit, cancel, or override leave.
- Whether leave is branch-scoped for multi-branch Coaches.
- Whether overlapping leave is allowed.
- Precedence between availability, leave, session assignment, and scheduled sessions.
- What happens to sessions already scheduled during leave.
- Which existing permission controls each operation.
- Required audit, history, correction, locking, and concurrency behavior.

**Recorded direction:** Coaches may view their own availability and leave. Authorized Branch Admins and Super Admins may create, edit, approve, cancel, and correct leave and availability for permitted branches. Leave requires start date, end date, and reason; overlapping leave is rejected; approved leave is retained and not physically deleted; changes are audited; historical attendance is unchanged.  
**Decision status:** User-provided direction; not client approval.  
**Exact resolution:**

____________________________________________________________________

**Comments:**

____________________________________________________________________

**Approver:** ______________________________  **Date:** ______________

---

## CM-06 — Coach audit, history, and correction policy

**Current status:** `USER_PROVIDED_DIRECTION — IMPLEMENTATION_AUTHORIZED_FOR_SCOPED_WORK`  
**Sources:** CM-06; OPS-01; PA-02; SCH-02; SCH-05; `AuditPersistenceService`; current Coach profile and assignment data.

Decide which Coach changes require append-only history and audit, including profile edits, activation changes, branch assignments, session assignments, leave, availability, KPI corrections, and rejected attempts. Define the required fields:

- Actor and authorization context
- UTC timestamp
- Branch
- Entity and record identifier
- Previous and new values or state
- Reason and source
- Optimistic version or stale-write behavior
- Retention and correction rules
- Sensitive-data exclusions

Also decide whether KPI history is immutable, recalculated, or versioned when source attendance or assignment data changes.

**Recorded direction:** Coach assignments, session assignments, leave, availability, KPI corrections, and status changes use append-only audit history. Corrections require a reason and record actor, timestamp, previous value, new value, and branch. Only authorized users with the existing Coach or relevant update permission may correct records in permitted branches. Audit history is never deleted or overwritten.  
**Decision status:** User-provided direction; not client approval.  
**Exact resolution:**

____________________________________________________________________

**Comments:**

____________________________________________________________________

**Approver:** ______________________________  **Date:** ______________

---

## Player feedback rating

**Current status:** `USER_PROVIDED_DIRECTION — IMPLEMENTATION_AUTHORIZED_FOR_SCOPED_WORK`  
**Sources:** CM-05; PF-05; `CoachManagementService::kpis()` currently returns `null`; no approved feedback source was found.

Decide:

- The authoritative source record and relationship to Coach, Player, Session, or branch.
- Who may submit feedback and whether one or multiple responses are allowed.
- Rating scale, permitted values, required fields, and validation.
- Formula, aggregation period, weighting, rounding, and missing-data behavior.
- Who may view the rating, including Coach self-visibility and cross-branch behavior.
- Correction, locking, history, audit, and recalculation rules.
- Whether comments or other feedback content are stored and who may see them.

**Recorded direction:** Ratings use a 1–5 scale and link to a completed Coach session and relevant Player. Authorized branch users may view them. The Coach KPI is the average of approved ratings in the selected reporting period. With no approved ratings, display `Not yet available`. Corrections require a reason and audit record; no anonymous external delivery or public visibility is allowed.  
**Decision status:** User-provided direction; not client approval.  
**Exact resolution:**

____________________________________________________________________

**Comments:**

____________________________________________________________________

**Approver:** ______________________________  **Date:** ______________

---

## Performance improvement index

**Current status:** `USER_PROVIDED_DIRECTION — IMPLEMENTATION_AUTHORIZED_FOR_SCOPED_WORK`  
**Sources:** CM-05; PF-01–PF-06; `CoachManagementService::kpis()` currently returns `null`; no approved performance source or formula was found.

Decide:

- The authoritative performance source data and event grain.
- The baseline period, comparison period, and eligibility requirements.
- Formula, weighting, units, precision, rounding, and missing-data behavior.
- Treatment of corrections, late data, cancelled sessions, branch changes, and inactive Coaches.
- Who may view the index and whether visibility is branch-scoped or aggregated.
- Correction, locking, versioning, history, audit, and recalculation rules.
- Whether the index is advisory, report-ready, or an authoritative business measure.

**Recorded direction:** Use approved Player performance records. Compare the earliest approved baseline with the latest approved assessment for the same Player and metric, and average percentage improvement across eligible comparable metrics. Exclude missing, invalid, or incomparable metrics. Display `Not yet available` when data is insufficient. Visibility requires Coach KPI permission and branch scope. Corrections require a reason and audit record; no performance values may be invented.  
**Decision status:** User-provided direction; not client approval.  
**Exact resolution:**

____________________________________________________________________

**Comments:**

____________________________________________________________________

**Approver:** ______________________________  **Date:** ______________

---

## CM-05 — KPI governance and future source policy

**Current status:** `USER_PROVIDED_DIRECTION — IMPLEMENTATION_AUTHORIZED_FOR_SCOPED_WORK`  
**Sources:** CM-05; AT-01–AT-06; PF-01–PF-06; `docs/REQUIREMENTS.md` KPI policy; current `CoachManagementService::kpis()`.

For Sessions conducted, Attendance compliance, Assigned players, Player feedback rating, and Performance improvement index, decide:

- KPI formula versioning and effective dates.
- Source-data ownership and replacement policy.
- Visibility by role, branch, and multi-branch scope.
- Correction propagation and historical recalculation.
- Period boundaries, timezone, denominator, rounding, and zero-data display.
- Whether future approved source tables may be added and how their schema changes are governed.
- Required freshness, report metadata, audit, and failure behavior.

The existing source-backed KPIs may continue to display from their current sources. This request does not approve formulas or visibility for the two unavailable KPIs and does not authorize new source tables.

**Recorded direction:** KPI definitions are versioned in project documentation. Calculations use source records, remain branch-scoped and permission-protected, recalculate when source records are corrected, use the configured application timezone, add no external delivery, and display `Not yet available` rather than fabricated zeroes when source data is unavailable.  
**Decision status:** User-provided direction; not client approval.  
**Exact resolution:**

____________________________________________________________________

**Comments:**

____________________________________________________________________

**Approver:** ______________________________  **Date:** ______________

---

## Implementation gate after responses

No new Coach workflow, permission, migration, source table, KPI formula, or visibility rule should be implemented from this request alone. After responses are recorded, reconcile them into `docs/REQUIREMENTS.md`, `docs/DECISIONS.md`, `docs/RBAC.md`, `docs/ARCHITECTURE.md`, and `docs/DATABASE.md`. Preserve every unanswered or conflicting item as `NEEDS_DECISION` and define focused acceptance criteria before coding.
