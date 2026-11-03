# MCC Cricket Club Management System — Decisions

**Status:** Draft
**Purpose:** Record decisions derived from `REQUIREMENTS-REVIEW.md`.
**Rule:** A PROPOSED decision must not be treated as an implementation requirement until approved.

## Decision Status

* **PROPOSED** — awaiting approval
* **APPROVED** — approved for implementation
* **USER_PROVIDED_DIRECTION** — the user explicitly authorized an exact implementation scope; this is not client approval and does not resolve wider requirements
* **NEEDS_DECISION** — client or owner decision is required
* **REJECTED** — explicitly rejected
* **DEFERRED** — intentionally postponed
* **SUPERSEDED** — replaced by a later decision while history is retained

## Approval Rules

* Every implementation decision must have an explicit status.
* Only **APPROVED** records represent client or owner approval. A `USER_PROVIDED_DIRECTION` may be implemented only for the exact scope the user explicitly authorized and must not be presented as client approval.
* **REJECTED** decisions must not be implemented.
* **DEFERRED** decisions are outside the current implementation scope.
* Approved decisions record the approver and approval date.

---

# Security & Identity

### EQUIP-01 — Limited Equipment inventory and branch selection

**Status:** USER_PROVIDED_DIRECTION — IMPLEMENTED_FOR_SCOPED_WORK (not client approval)

**Decision source:** User-provided direction dated 2026-09-21. Approver identity and client approval date are not recorded. This limited authorization does not resolve or replace the missing approved requirements baseline or wider RBAC matrix.

**Scope:** Equipment inventory records contain Name, Quantity, and Branch. Create, list, edit, and soft delete are in scope. `equipment.view`, `equipment.create`, `equipment.update`, and `equipment.delete` are granted to the existing `Super Admin` and `Branch Admin` roles for this module slice. The user confirmed that “Admin” maps to `Super Admin` and “Branch Manager” maps to `Branch Admin`.

**Branch rule:** Current database `user_branches` assignments are authoritative. Super Admin selects an active assigned branch on the form. Branch Admin with one active authorized branch uses it automatically; with multiple active authorized branches, selects an active branch through shared navigation and sees it as fixed on the Equipment form; with none, cannot create. The server stores the selected active branch in session after a CSRF-protected POST and validates it against current assignments at selection and each Equipment use. No hidden field, submitted Branch Admin `branch_id`, URL parameter, or stale session value grants access. Existing modules do not use this active context for data access as part of this decision.

**Data and behavior:** The user specified Name, Quantity, and Branch but did not specify Name length, whether zero quantity is valid, quantity precision/range, or duplicate-name behavior. The current implementation uses a required Name capped at 150 characters and a positive whole Quantity within the database unsigned-integer range as provisional technical constraints; these are not client-approved business rules. Alternative lengths, zero/fractional quantities, values beyond the schema range, and duplicate-name policy remain `NEEDS_DECISION`. Editing may change only Name and Quantity; the existing Branch remains fixed. Deletion uses a nullable `deleted_at` timestamp, excludes the row from active inventory lists, and records the change in audit history in the same database transaction. No hard-delete or restore interface is authorized; restore workflow and its permissions remain `NEEDS_DECISION`. No uniqueness rule for Name is introduced.

**Unresolved role overlap:** A user assigned both `Super Admin` and `Branch Admin` is denied Equipment access until role precedence is decided. `SEC-02` permits multiple roles but does not define precedence between the two branch-selection modes. This case remains `NEEDS_DECISION`.

**Explicit exclusions:** Hard deletion, a restore interface or workflow, branch reassignment, categories, assignment/reservation/checkout, availability rules, maintenance, Grounds or scheduling links, reports, and extra user-entered fields are not authorized by this decision and remain `NEEDS_DECISION` unless independently approved later.

**Related records:** `REQ-EQUIPMENT-001`; `SEC-03` and `BR-06` remain the existing approved general branch-assignment principles. `RBAC-MATRIX` and the replacement-baseline approval remain unresolved outside this narrow permission grant.

---

## Program and Batch management — limited user direction

### PROG-BATCH-01 — Create/list baseline

**Status:** `USER_PROVIDED_DIRECTION`; not client approval. Direction recorded 2026-09-22.

**Decision:** Implement only Program create/list with a required validated `name`, and Batch create/list with required `name`, existing Program, and Branch. Super Admin selects an authorized Branch. Branch Admin uses the server-validated active Branch; submitted Branch values are ignored for that role. Use centralized `program.view`, `program.create`, `batch.view`, and `batch.create` permissions for the existing Super Admin and Branch Admin roles.

**Schema:** Add `programs(name, created_by, created_at, updated_at)` and nullable `batches.program_id` with a foreign key to `programs`. New Batch creation requires a Program; existing rows are preserved until an approved backfill decision.

**Explicit exclusions:** Capacity, status, description, fees, duration, curriculum, edit/delete/archive, dependent-record behavior, coach assignment, scheduling, recurrence, attendance, and other workflows remain `NEEDS_DECISION`. Program branch ownership/sharing remains `NEEDS_DECISION`; this implementation treats Programs as global reference records only to support the requested field set.

**Related records:** `BR-04` and `PA-01` remain authoritative for branch-owned Batches and player assignment. `REQ-PLAYER-001` and `REQ-SESSION-001` do not authorize additional Program or Batch lifecycle behavior.

**Source:** `docs/BATCH-PROGRAM-MANAGEMENT-DECISION-BRIEF.md`, user-provided direction. No client approval or missing SRS requirement is inferred.

---

### SEC-01 — Authentication and account model

**Status:** APPROVED

**Decision:** One database-backed identity model for staff, parents, and players.

**Approved by:** Client / Architecture
**Approved on:** 2026-08-28

**Source:** `REQUIREMENTS-REVIEW.md / SEC-01`

### SEC-02 — Multiple roles

**Status:** APPROVED

**Decision:** A user may have multiple roles.

**Approved by:** Client / Architecture
**Approved on:** 2026-08-28

**Source:** `REQUIREMENTS-REVIEW.md / SEC-02`

### SEC-03 — Branch assignments

**Status:** APPROVED

**Decision:** A user may have multiple authorized branch assignments.

**Approved by:** Client / Architecture
**Approved on:** 2026-08-28

**Source:** `REQUIREMENTS-REVIEW.md / SEC-03`

### SEC-04 — RBAC changes

**Status:** APPROVED

**Decision:** Role and permission changes must be centrally controlled and audited.

**Approved by:** Client / Architecture
**Approved on:** 2026-08-28

**Source:** `REQUIREMENTS-REVIEW.md / SEC-04`

### SEC-05 — Medical information

**Status:** APPROVED

**Decision:** Medical information must have restricted role-based access.

**Approved by:** Client / Architecture
**Approved on:** 2026-08-28

**Source:** `REQUIREMENTS-REVIEW.md / SEC-05`

### SEC-06 — Document access

**Status:** APPROVED

**Decision:** Document access must be controlled by document type and authorization.

**Approved by:** Client / Architecture
**Approved on:** 2026-08-28

**Source:** `REQUIREMENTS-REVIEW.md / SEC-06`

### SEC-07 — Document verification

**Status:** APPROVED

**Decision:** Documents use explicit verification states and record the verifier.

**Approved by:** Client / Architecture
**Approved on:** 2026-08-28

**Source:** `REQUIREMENTS-REVIEW.md / SEC-07`

### SEC-08 — Document retention

**Status:** APPROVED

**Decision:** Documents use retention rules and logical retirement rather than unrestricted hard deletion.

**Approved by:** Client / Architecture
**Approved on:** 2026-08-28

**Source:** `REQUIREMENTS-REVIEW.md / SEC-08`

### SEC-09 — Minor accounts

**Status:** APPROVED

**Decision:** Guardian consent is required for eligible minor/player accounts.

**Approved by:** Client / Architecture
**Approved on:** 2026-08-28

**Source:** `REQUIREMENTS-REVIEW.md / SEC-09`

### SEC-10 — Media consent

**Status:** APPROVED

**Decision:** Photo/video consent must be explicitly tracked.

**Approved by:** Client / Architecture
**Approved on:** 2026-08-28

**Source:** `REQUIREMENTS-REVIEW.md / SEC-10`

### SEC-11 — Export authorization

**Status:** APPROVED

**Decision:** Export permissions are separate from normal screen/view permissions.

**Approved by:** Client / Architecture
**Approved on:** 2026-08-28

**Source:** `REQUIREMENTS-REVIEW.md / SEC-11`

---

# Player Lifecycle

### PL-01 — Lead/Trial versus Player status

**Status:** APPROVED

**Decision:** Lead and Trial remain CRM/admission stages. Player status begins at Selected/Admission Pending and continues through the academy lifecycle.

**Approved by:** Client / Architecture
**Approved on:** 2026-08-28

**Source:** `REQUIREMENTS-REVIEW.md / PL-01`

### PL-02 — Transition authorities

**Status:** APPROVED

**Decision:** Admission Team manages intake; Coach recommends selection; Branch Admin approves player activation.

**Approved by:** Client / Architecture
**Approved on:** 2026-08-28

**Source:** `REQUIREMENTS-REVIEW.md / PL-02`

### PL-03 — Transition prerequisites

**Status:** APPROVED

**Decision:** An approved admission checklist is required before activation. Payment is not assumed as a prerequisite until a financial requirement is separately approved.

**Approved by:** Client / Architecture
**Approved on:** 2026-08-28

**Source:** `REQUIREMENTS-REVIEW.md / PL-03`

### PL-04 — Transition reversal

**Status:** APPROVED

**Decision:** Controlled lifecycle corrections/reversals are allowed only for authorized users and must include a reason and audit record.

**Approved by:** Client / Architecture
**Approved on:** 2026-08-28

**Source:** `REQUIREMENTS-REVIEW.md / PL-04`

### PL-05 — Suspension rules

**Status:** APPROVED

**Decision:** Suspension requires a reason, acting user, start date, optional end date, and audit record. Branch Admin or Super Admin approval is required.

**Approved by:** Client / Architecture
**Approved on:** 2026-08-28

**Source:** `REQUIREMENTS-REVIEW.md / PL-05`

### PL-06 — Left Academy and Alumni

**Status:** APPROVED

**Decision:** Departure and Alumni classification are separate lifecycle events. Reactivation rules must be supported.

**Approved by:** Client / Architecture
**Approved on:** 2026-08-28

**Source:** `REQUIREMENTS-REVIEW.md / PL-06`

### AD-01 — Approved admission lifecycle baseline

**Status:** APPROVED

**Decision:** Phase 1 admissions use exactly these statuses: `INTAKE`, `IN_REVIEW`, `DOCUMENTS_PENDING`, `APPROVED`, `REJECTED`, `WITHDRAWN`, and `CONVERTED`.

Allowed transitions are:

| Current status | Allowed next statuses |
|---|---|
| `INTAKE` | `IN_REVIEW`, `DOCUMENTS_PENDING`, `APPROVED`, `REJECTED`, `WITHDRAWN` |
| `IN_REVIEW` | `DOCUMENTS_PENDING`, `APPROVED`, `REJECTED`, `WITHDRAWN` |
| `DOCUMENTS_PENDING` | `IN_REVIEW`, `APPROVED`, `REJECTED`, `WITHDRAWN` |
| `APPROVED` | `CONVERTED`, `WITHDRAWN` |
| `REJECTED` | None; terminal |
| `WITHDRAWN` | None; terminal |
| `CONVERTED` | None; terminal |

`REJECTED` and `WITHDRAWN` require a reason. Every transition records actor, timestamp, prior status, new status, and reason where applicable. Cross-branch users cannot change an admission.

**Approved by:** Project Owner instruction received 2026-08-29
**Approved on:** 2026-08-29

**Source:** Current approved implementation instruction.

#### AD-01 user-provided amendment — rejected admission reopening

**Status:** User-provided direction, implemented for this scoped change; **not client approval**. Client reconciliation remains `NEEDS_DECISION`.

For this implementation, the `REJECTED` row in the AD-01 transition matrix is amended only as follows: a `REJECTED` admission may transition to `IN_REVIEW`, and no other transition from `REJECTED` is permitted. Reopening requires a non-empty reason. The existing admission checklist is preserved. The existing `admission.update` permission and authorized branch access are required. The admission status history and audit record retain the actor, timestamp, previous status, new status, and reopening reason. After reopening, the admission follows the existing lifecycle and conversion safeguards; only `APPROVED` may transition to `CONVERTED`.

| Current status | Scoped amended allowed next statuses |
|---|---|
| `REJECTED` | `IN_REVIEW` only |

This amendment does not alter the other AD-01 transitions, terminal behavior for `WITHDRAWN` or `CONVERTED`, or any checklist or conversion rule. No approver identity or client approval date is recorded.

**Direction recorded on:** 2026-09-22

**Source:** User-provided implementation direction in the current task.

### AD-02 — Phase 1 admission checklist

**Status:** APPROVED

**Decision:** Before `APPROVED`, the admission checklist must record completion of applicant identity/basic profile information, required contact/guardian information where applicable, required admission information, confirmed branch assignment, and explicit admission approval. Passport, medical, fitness, and advanced document requirements are excluded from this checklist.

**Approved by:** Project Owner instruction received 2026-08-29
**Approved on:** 2026-08-29

**Source:** Current approved implementation instruction.

### AD-03 — Admission conversion

**Status:** APPROVED

**Decision:** Admissions may be created through either Lead → Admission or Direct Admission. Direct Admissions retain a nullable `lead_id` and their applicant identity is stored using the approved person identity field set. Only an `APPROVED` admission may transition to `CONVERTED`. Conversion creates exactly one player in one database transaction, preserves `admission_id` lineage and preserves `lead_id` when present, is idempotent on retry, prevents duplicate player creation under concurrency, preserves CRM/admission history, audits the conversion, and marks the admission `CONVERTED` only after player creation succeeds. No other admission status creates a player.

**Approved by:** Project Owner instruction received 2026-08-29
**Approved on:** 2026-08-29

**Source:** Current approved implementation instruction.

---

# Branch Management

### BR-08 — Branch creation

**Status:** APPROVED

**Decision:** Branch creation is a Phase 1 organization-level administration workflow. Only authenticated users with the existing `rbac.manage` permission may create branches; the creator's current branch assignment does not restrict creation. The client may submit only `name` and `code`. The server creates `is_active` as true and supplies timestamps. Both name and code are required, normalized using existing conventions, and unique; database constraints remain the final uniqueness enforcement. Successful creation records a `branch_created` audit event containing only non-sensitive branch metadata. Failed attempts do not record a creation event.

**Approved by:** Client / Architecture
**Approved on:** 2026-08-30

### BR-09 — Branch deletion and closure action

**Status:** APPROVED

**Decision:** Physical branch deletion is prohibited. Branches use the existing `is_active` lifecycle with `ACTIVE → INACTIVE` deactivation and `INACTIVE → ACTIVE` reactivation. Only authenticated users with `rbac.manage` may perform either transition through CSRF-protected non-GET actions with explicit confirmation for deactivation. The transition changes only branch lifecycle state, preserves the branch and all related/history records, performs no reassignment or cascade deletion, and records a non-sensitive `branch_deactivated` or `branch_reactivated` audit event. Inactive branches are unavailable for new operational activity only where an existing approved rule requires an active branch; historical access and relationships remain preserved.

**Approved by:** Client / Architecture
**Approved on:** 2026-08-30

**Scope boundary:** This decision covers creation only. Branch editing, deletion, closure workflow, transfer, merging, and reporting remain separate.

### BR-10 — Super Admin access to branches they create

**Status:** User-provided authorization for this limited implementation; not client approval.

**Direction date:** 2026-09-21. Approver identity and effective date were not supplied.

**Decision:** When an authenticated user in the existing Super Admin-only context creates a branch, the same user is assigned to that branch through `user_branches`. Create the branch, add the creator assignment, and record both the existing `branch_created` event and a `branch_assigned` event in the same transaction. For an existing branch, the same Super Admin may restore only their own missing assignment when exactly one matching `branch_created` audit event verifies them as its creator. This grants access only to a branch created by that Super Admin. It does not grant access to other unassigned branches, change the user's role or global permissions, or automatically assign Branch Admins or other roles. Existing branch list and detail checks remain restricted to authorized assignments.

**Existing records:** The `branch_created` audit events for branches `1` and `2` identify the same user, who currently has the Super Admin role without the Branch Admin role. That user had no assignment to either branch when checked in `mcc_cricket_club_test`; only those two verified creator assignments are authorized for backfill.

### BR-01 — Lead branch assignment

**Status:** APPROVED

**Decision:** A lead is assigned to the intake branch when created. Branch transfers must preserve history.

**Approved by:** Client / Architecture
**Approved on:** 2026-08-28

**Source:** `REQUIREMENTS-REVIEW.md / BR-01`

### BR-02 — Player transfer

**Status:** APPROVED

**Decision:** Player branch transfers require authorization, an effective date, and preserved historical branch ownership.

**Approved by:** Client / Architecture
**Approved on:** 2026-08-28

**Source:** `REQUIREMENTS-REVIEW.md / BR-02`

### BR-03 — Coach multi-branch assignment

**Status:** APPROVED

**Decision:** Coaches may be assigned to multiple authorized branches.

**Approved by:** Client / Architecture
**Approved on:** 2026-08-28

**Source:** `REQUIREMENTS-REVIEW.md / BR-03`

### BR-04 — Shared sessions, batches, and grounds

**Status:** APPROVED

**Decision:** Sessions, batches, and grounds are branch-owned by default. Explicit shared access may be introduced where required.

**Approved by:** Client / Architecture
**Approved on:** 2026-08-28

**Source:** `REQUIREMENTS-REVIEW.md / BR-04`

### BR-05 — Shared matches and tournaments

**Status:** APPROVED

**Decision:** Tournaments are global when shared across branches. Matches may support participating branches.

**Approved by:** Client / Architecture
**Approved on:** 2026-08-28

**Source:** `REQUIREMENTS-REVIEW.md / BR-05`

### BR-06 — Cross-branch historical visibility

**Status:** APPROVED

**Decision:** Branch Admin access is based on current authorized branch scope. Historical records remain preserved and are not reassigned retroactively.

**Approved by:** Client / Architecture
**Approved on:** 2026-08-28

**Source:** `REQUIREMENTS-REVIEW.md / BR-06`

### BR-07 — Branch closure

**Status:** APPROVED

**Decision:** Branches must not be hard-deleted when they have historical records. Closure preserves historical data and prevents new operational activity.

**Approved by:** Client / Architecture
**Approved on:** 2026-08-28

**Source:** `REQUIREMENTS-REVIEW.md / BR-07`

---

# Player Placement Decisions

### PA-01 — Player-to-batch/group assignment

**Status:** APPROVED

**Decision required:** Define who may assign or remove a player; whether a player may have one or multiple active groups; effective start/end dates; removal and replacement behavior; active/inactive semantics; capacity and exceeded-capacity behavior; group-transfer authorization; audit fields; and concurrency/conflict handling.

**Approved baseline:** Use dedicated branch-scoped `player.batch.assign` permission; one active batch/group per player; required effective start and optional effective end; replacement closes the current assignment before inserting the new one; preserve append-only history; enforce an approved batch capacity only when one exists and otherwise impose no limit; use optimistic locking; audit assignment, removal, replacement, and applicable rejected unauthorized/invalid attempts; and require player and batch to belong to the same authorized branch.

**Existing approved constraints:** Batches are branch-owned by default under BR-04. Player branch transfers require authorization, an effective date, and preserved historical ownership under BR-02. Historical visibility follows current authorized branch scope under BR-06.

**Options requiring approval:**

1. Authority: (A) Branch/Super Admin only; (B) approved `player.group.assign` permission; (C) separate assign/remove permissions.
2. Cardinality: (A) one active group; (B) multiple active groups; (C) one primary plus optional secondary groups.
3. Dates: (A) required start, optional end; (B) required start and end; (C) removal closes the assignment at removal time.
4. Replacement: (A) close prior assignment before replacement; (B) permit overlap; (C) prohibit replacement until prior assignment ends.
5. Activity/capacity: (A) active means effective now and no end date, with no capacity; (B) hard capacity with rejection when exceeded; (C) warning-only capacity.
6. Group transfer authorization: (A) normal assignment permission; (B) elevated admin permission; (C) elevated permission only across branches.
7. History/conflict: (A) append-only history with actor, timestamps, prior/new group, reason, audit, per-player serialization, and stale-write rejection; (B) mutable current row with audit snapshot; (C) another documented model.

**Context:** REQ-PLAYER-001 requires batch assignment, but no approved player-to-group cardinality, assignment lifecycle, removal, capacity, or transfer policy exists.
**Owner:** Project Owner (name not provided)
**Approver:** Client
**Approval date:** 2026-08-29
**Approval source:** Explicit client approval instruction received 2026-08-29.
**Related requirements:** REQ-PLAYER-001, REQ-SESSION-001.
**Related modules:** Players, Sessions/Batches.

### PA-02 — Player-to-coach assignment

**Status:** APPROVED

**Decision required:** Define who may assign or remove a coach; whether a player may have one or multiple active coaches; primary/secondary semantics; effective dates; replacement and history; availability/leave behavior; capacity; unavailable-coach handling; audit fields; and concurrency/conflict handling.

**Approved baseline:** Use dedicated branch-scoped `player.coach.assign` permission; one active primary coach per player; required effective start and optional effective end; replacement closes the current assignment before inserting the new one; preserve append-only history; do not automatically reassign unavailable coaches; require explicit reassignment; do not enforce coach capacity; use optimistic locking; audit assignment, removal, replacement, and applicable rejected unauthorized/invalid attempts; require the coach to be authorized for the player’s branch; and exclude secondary coaches.

**Existing approved constraints:** Coaches may be assigned to multiple authorized branches under BR-03. Batches are branch-owned by default under BR-04. BR-02 and BR-06 govern player branch transfer authorization and historical visibility. SCH-05 concerns scheduling availability but does not define player assignment.

**Options requiring approval:**

1. Authority: (A) Branch/Super Admin only; (B) approved `player.coach.assign` permission; (C) separate assign/remove permissions.
2. Cardinality: (A) one active coach; (B) multiple active coaches; (C) one primary plus optional secondary coaches.
3. Dates: (A) required start, optional end; (B) required start and end; (C) removal closes the assignment at removal time.
4. Replacement: (A) close prior assignment before replacement; (B) permit overlap; (C) prohibit replacement until prior assignment ends.
5. Availability/capacity: (A) assignment independent of scheduling availability/capacity; (B) reject when an approved limit is exceeded; (C) warning-only.
6. Unavailable coach: (A) preserve assignment pending explicit reassignment; (B) automatically end it; (C) mark inactive pending replacement.
7. History/conflict: (A) append-only history with actor, timestamps, prior/new coach, role, reason, audit, per-player serialization, and stale-write rejection; (B) mutable current row with audit snapshot; (C) another documented model.

**Context:** REQ-PLAYER-001 requires coach assignment, but BR-03 does not define player-to-coach cardinality, lifecycle, availability, capacity, replacement, or removal behavior.
**Owner:** Project Owner (name not provided)
**Approver:** Client
**Approval date:** 2026-08-29
**Approval source:** Explicit client approval instruction received 2026-08-29.
**Related requirements:** REQ-PLAYER-001, REQ-SESSION-001.
**Related modules:** Players, Sessions/Coaching.

---

# Scheduling Decisions

### SCH-01 — Session types and recurrence

**Status:** APPROVED

**Business question:** Which schedulable session types are in scope, and are sessions one-time, recurring, or both?

**Approved baseline:** Implement one-time sessions only. Every session has explicit start and end datetime. Recurrence is deferred. Organizational timezone policy is not invented; OPS-09 remains authoritative when resolved.
**Approved by:** Client instruction received 2026-08-29
**Approved on:** 2026-08-29

**Alternatives:** (A) one-time sessions only; (B) recurring series with generated occurrences; (C) both one-time sessions and recurring series from the first release.

**Consequences:** One-time sessions simplify conflict checks, cancellation history, and attendance linkage. Recurrence requires series identity, occurrence generation, exception handling, and series-level audit.

**Affected requirements/modules:** REQ-SESSION-001, REQ-ATT-001; Sessions, Batches, Coaching, Attendance.
**Authorization/branch:** Session creation and changes require a dedicated approved scheduling permission; sessions are branch-owned by default under BR-04, and shared access must be explicit.
**Audit/concurrency:** Create/update/cancel/reschedule actions require actor, branch, timestamp, and reason where applicable; conflicting edits require row locking or optimistic version checks.
**Acceptance implications:** Tests must cover each approved session type, datetime validation, branch denial, authorization, history, and concurrent conflicting edits.

### SCH-02 — Scheduling authority, ownership, and eligibility

**Status:** APPROVED

**Business question:** Who may create/change sessions, which branch owns them, how coach and batch associations work, and which players may enter a roster?

**Approved baseline:** Use `session.create`, `session.update`, and `session.cancel`; sessions are branch-owned; resolve player eligibility from persisted assignments; require coach authorization for the session branch; never trust submitted branch ownership or eligibility.
**Approved by:** Client instruction received 2026-08-29
**Approved on:** 2026-08-29

**Alternatives:** (A) Branch Admin/Super Admin only; (B) dedicated create/update/cancel permissions; (C) role-specific scheduling permissions for operations, coaches, and admins. For eligibility: (A) active batch membership only; (B) active batch plus coach assignment; (C) explicit per-session eligibility override.

**Consequences:** The selected authority determines RBAC scope and audit ownership. Eligibility based on current assignments avoids a second source of truth; overrides require additional reason and history rules.

**Affected requirements/modules:** REQ-SESSION-001, REQ-PLAYER-001, REQ-ATT-001; Sessions, Players, Batches, Coaching, Attendance.
**Authorization/branch:** Submitted branch IDs, player ownership, batch branch, and coach branch must never be trusted; all relationships must be resolved server-side. BR-03 permits coaches across authorized branches but does not grant session permission.
**Audit/concurrency:** Assignment changes and session roster changes require audit; session and roster writes require transactional conflict handling.
**Acceptance implications:** Tests must cover unauthorized create/update/cancel, cross-branch denial, missing/ineligible players, stale assignment/session writes, and authoritative relationship resolution.

### SCH-03 — Capacity and booking conflicts

**Status:** APPROVED

**Business question:** Are there capacities for sessions, batches, grounds, or coaches, and how are double bookings and resource conflicts handled?

**Approved baseline:** Enforce an approved capacity when one exists and do not invent capacity otherwise. Reject scheduling conflicts and double booking, including coach conflicts. Use transactions and concurrency protection; conflicting schedules must not be silently overwritten.
**Approved by:** Client instruction received 2026-08-29
**Approved on:** 2026-08-29

**Alternatives:** (A) hard rejection; (B) warning with authorized override; (C) allow overlap and report it. Capacity may be (A) session-level; (B) batch/ground/coach-level; (C) not enforced until separately approved.

**Consequences:** Hard rejection protects operational integrity but requires precise interval, timezone, resource, and override definitions. Warnings require override authority and audit. No-capacity behavior avoids invented limits but cannot guarantee utilization control.

**Affected requirements/modules:** REQ-SESSION-001, REQ-ATT-001; Sessions, Batches, Grounds, Coaching.
**Authorization/branch:** Overrides, if allowed, require a separate permission; branch-owned resources remain isolated under BR-04.
**Audit/concurrency:** Conflict checks and booking writes must occur in one transaction with locking or a database-backed conflict constraint; rejected conflicts and overrides must be audited.
**Acceptance implications:** Tests must cover boundary-touching intervals, overlapping intervals, concurrent bookings, capacity exceeded, authorized override if approved, and cross-branch resources.

### SCH-04 — Cancellation, rescheduling, and attendance boundary

**Status:** APPROVED

**Business question:** What records and reasons are required when a session is cancelled or moved, and how does that affect attendance?

**Approved baseline:** Preserve cancelled records; cancelled sessions cannot be silently reused. Rescheduling creates a new linked session while preserving the original and its history. Cancelled sessions do not create attendance records. Attendance remains outside this slice. Audit creation, modification, cancellation, and rescheduling.
**Approved by:** Client instruction received 2026-08-29
**Approved on:** 2026-08-29

**Alternatives:** (A) mutate the session with history; (B) immutable original plus linked replacement; (C) cancel and recreate as unrelated sessions. Attendance options: (A) cancelled sessions excluded; (B) retained as an explicit non-attendance event; (C) separate approved treatment.

**Consequences:** Immutable originals provide the strongest operational history but require linkage and UI semantics. Attendance treatment affects denominators, reports, corrections, and audit.

**Affected requirements/modules:** REQ-SESSION-001, REQ-ATT-001; Sessions, Attendance, Reports.
**Authorization/branch:** Cancellation/rescheduling require dedicated permissions and current branch authorization; historical visibility follows BR-06.
**Audit/concurrency:** Cancellation/rescheduling and linked replacement creation must be transactional; stale cancellation/reschedule attempts must be rejected; reasons and actor data are mandatory.
**Acceptance implications:** Tests must cover valid/invalid cancellation, required reason, linked reschedule history, retry/concurrency, no attendance for cancelled sessions, and branch-scoped history.

### SCH-05 — Coach availability and leave precedence

**Status:** APPROVED

**Business question:** How is coach availability represented, which leave/availability rule takes precedence, and what happens when a scheduled coach becomes unavailable?

**Approved baseline:** Check coach availability before scheduling. Approved leave/unavailability prevents booking. Do not automatically reassign unavailable coaches; explicit authorized reassignment is required and assignment history is preserved.
**Approved by:** Client instruction received 2026-08-29
**Approved on:** 2026-08-29

**Alternatives:** (A) availability is advisory; (B) unavailable coaches are hard-blocked; (C) hard-block with authorized override. Unavailability handling: (A) preserve session pending explicit action; (B) cancel; (C) reschedule; (D) replace coach through an explicit audited action.

**Consequences:** Hard blocking protects against invalid bookings but requires availability data, leave precedence, timezone semantics, and override policy. Advisory checks reduce policy but permit operational conflicts.

**Affected requirements/modules:** REQ-SESSION-001, REQ-PLAYER-001; Sessions, Coaching, Players.
**Authorization/branch:** Availability changes and overrides require dedicated permissions; coach branch authorization remains governed by BR-03 and session branch scope by BR-04.
**Audit/concurrency:** Availability changes, overrides, cancellations, and explicit reassignments require audit; booking must recheck availability inside the transaction to reject stale decisions.
**Acceptance implications:** Tests must cover available/unavailable coaches, leave precedence, booking races, override authorization if approved, explicit reassignment, and preserved history.

**Cross-cutting dependency:** OPS-09 is recorded separately below as `APPROVED`. AT-01–AT-06 remain `NEEDS_DECISION` for attendance eligibility, cancellation treatment, denominators, corrections, and alerts. This scheduling slice does not resolve or implement those separate decisions.

### OPS-09 — MCC timezone and timestamp semantics

**Status:** APPROVED

**Approved baseline:** MCC business timezone is `Asia/Kolkata`. Database timestamps and audit timestamps are stored in UTC. Scheduling/UI presentation converts timestamps to `Asia/Kolkata`. Datetime requests require explicit timezone information; timezone-less values are rejected rather than interpreted using the server timezone. Branches and users cannot override the MCC scheduling timezone. Historical records preserve the actual instant and are never rewritten for presentation.

**Approved by:** Client instruction received 2026-08-29
**Approved on:** 2026-08-29

**Affected requirements/modules:** NFR-07, REQ-SESSION-001, REQ-ATT-001; Scheduling and future Attendance/Reports.

**Acceptance implications:** Tests must prove UTC persistence, `Asia/Kolkata` presentation, explicit timezone handling, rejection of ambiguous/missing timezone input, stable historical instants, and UTC audit timestamps.

## Attendance decision package — AT-01–AT-06

The following records are approved using the documented proposed baselines. AT-06 remains explicitly deferred as stated in its approved baseline.

### AT-01 — Attendance event, eligibility, and denominator

**Status:** APPROVED

**Business question:** What is the attendance event grain, which session/player relationships create an eligible event, and which events belong in attendance denominators?

**Proposed baseline:** One append-audited attendance record per eligible player per session, with a database uniqueness constraint on `(session_id, player_id)`. A cancelled session creates no attendance records. Eligibility is resolved from the persisted session roster and authoritative player/branch relationships at the approved attendance boundary; players added after scheduling are not silently included, and players removed from the relevant assignment or transferred out of the session branch require an explicit approved treatment. Denominator rules remain part of this decision and are not inferred from status labels.

**Approved by:** Client instruction received 2026-08-29  
**Approved on:** 2026-08-29

**Alternatives:** (A) one row per player/session; (B) one session record containing player statuses; (C) snapshot all batch members at session time; (D) derive eligibility dynamically from current assignments.

**Dependencies/entities:** SCH-01–SCH-05, OPS-09, BR-02–BR-06, PA-01/PA-02; `sessions`, `session_players`, `players`, `player_batch_assignments`, `player_branch_history`, future `attendance` records.

**Authorization/branch:** Read/create/update/report access must resolve the session branch server-side; submitted player/session/branch IDs are not authoritative. Cross-branch reads and writes are denied.

**Audit/concurrency:** Unique-key duplicate rejection; row/version locking for updates; no silent overwrite. Record actor, UTC timestamp, session/player IDs, prior/new state, and reason where applicable without unnecessary sensitive data.

**Acceptance implications:** Tests must cover event uniqueness, roster-only eligibility, cancelled-session exclusion, post-scheduling assignment changes, branch transfer/inactive player handling, duplicate rejection, branch isolation, and denominator membership.

### AT-02 — Attendance status vocabulary and leave/absence treatment

**Status:** APPROVED

**Business question:** Which statuses are valid, how are leave/medical/excused absences represented, and who may change them?

**Proposed baseline:** Use `PRESENT`, `ABSENT`, `LATE`, and `EXCUSED` as proposed statuses only. Preserve `LATE` as an attendance status or separate lateness attribute only after client selection; do not collapse approved leave/medical treatment into ordinary absence without an approved policy. No medical detail is stored in Attendance.

**Approved by:** Client instruction received 2026-08-29  
**Approved on:** 2026-08-29

**Alternatives:** (A) `PRESENT/ABSENT/LATE/EXCUSED`; (B) `PRESENT/ABSENT` plus a separate late flag and approved absence reason; (C) `PRESENT/ABSENT/LATE/EXCUSED/LEAVE`; (D) separate absence reason taxonomy.

**Dependencies/entities:** AT-01, SEC-05, OPS-01, `sessions`, `session_players`, future attendance status/history tables.

**Authorization/branch:** Status changes require a dedicated approved permission and current session-branch scope; no role receives access merely from a UI role label.

**Audit/concurrency:** Status changes retain prior/new values, actor, UTC timestamp, and reason where required; stale writes are rejected.

**Acceptance implications:** Tests must cover every approved status, invalid status rejection, leave/medical privacy boundary, status edit authorization, branch isolation, and audit history.

### AT-03 — Recording authority, timing, and timezone

**Status:** APPROVED

**Business question:** Who may record attendance, when may it be recorded or edited, and what lock/cutoff applies?

**Proposed baseline:** Use dedicated permissions such as `attendance.view`, `attendance.record`, and `attendance.correct`, subject to client approval. Permit recording only for eligible roster players and only within an approved timing window; proposed default is from session start through a defined post-session cutoff. Use OPS-09: requests carry explicit timezone information or the documented MCC contract, persistence is UTC, presentation is `Asia/Kolkata`, and audit timestamps are UTC. Do not silently use the server timezone.

**Approved by:** Client instruction received 2026-08-29  
**Approved on:** 2026-08-29

**Alternatives:** (A) coach only during/after session; (B) scheduled coach plus authorized branch staff; (C) branch staff/admin only; (D) open recording with post-session lock; (E) no automatic cutoff, correction permission required indefinitely.

**Dependencies/entities:** AT-01/AT-02, SCH-02/05, OPS-09, `sessions`, `session_players`, future attendance records.

**Authorization/branch:** The recorder must have explicit permission and authorization for the session branch; scheduled coach authority must be decided separately from general branch staff authority.

**Audit/concurrency:** Timing decisions are evaluated server-side using UTC instants; lock/cutoff changes are audited; simultaneous writes use optimistic versioning or row locks and reject stale updates.

**Acceptance implications:** Tests must cover before-start behavior, active-window behavior, cutoff behavior, explicit timezone conversion, unauthorized roles, cross-branch denial, and concurrent recording.

### AT-04 — Cancellation and session/player eligibility boundary

**Status:** APPROVED

**Business question:** How does cancellation affect attendance records, and what is the authoritative player eligibility snapshot?

**Proposed baseline:** Preserve the cancelled session as a Scheduling record, create no attendance records for it, and exclude it from attendance denominators. Attendance may be created only for the persisted eligible roster of a non-cancelled session. A player assigned after scheduling is excluded unless the client approves a roster-snapshot amendment; a player removed before attendance requires an explicit policy rather than silent inclusion/exclusion.

**Approved by:** Client instruction received 2026-08-29  
**Approved on:** 2026-08-29

**Alternatives:** (A) session roster snapshot is authoritative; (B) current assignment at attendance time is authoritative; (C) snapshot with authorized correction workflow; (D) cancelled sessions represented only by the session-level cancellation audit event.

**Dependencies/entities:** SCH-04, AT-01, OPS-09, `sessions`, `session_players`, future attendance and history tables.

**Authorization/branch:** Cancellation state and roster must be checked server-side for every attendance request; cross-branch historical access remains denied unless separately authorized.

**Audit/concurrency:** Cancellation cannot be converted into a player attendance status; attempts to record against cancelled sessions are rejected and may be audited. Session state is rechecked under lock.

**Acceptance implications:** Tests must cover cancelled-session creation denial, no attendance rows, denominator exclusion, rescheduled-session independence, roster snapshot behavior, and stale cancellation races.

### AT-05 — Corrections, history, and absence thresholds

**Status:** APPROVED

**Business question:** Who may correct attendance, whether prior values are immutable history, and whether continuous-absence thresholds/alerts are in scope?

**Proposed baseline:** Require a dedicated correction permission, a reason, append-only change history, and optimistic stale-write rejection. Keep the original attendance event recoverable; do not silently overwrite. Defer continuous-absence thresholds, windows, calculations, and alerts until explicit approval.

**Approved by:** Client instruction received 2026-08-29  
**Approved on:** 2026-08-29

**Alternatives:** (A) authorized correction with append-only history; (B) immutable attendance with a superseding correction event; (C) mutable current row plus audit snapshot; (D) correction window after which only elevated authority may act.

**Dependencies/entities:** AT-02/AT-03, OPS-01, `sessions`, `session_players`, future attendance and attendance-history tables.

**Authorization/branch:** Correction authority must be explicit, branch-scoped, and independently auditable; no portal or cross-branch correction is implied.

**Audit/concurrency:** Record prior/new status, actor, UTC timestamp, reason, source version, and branch; reject stale versions and preserve all prior states.

**Acceptance implications:** Tests must cover correction permission, required reason, immutable history, stale correction, cross-branch denial, and rejection after any approved lock window.

### AT-06 — Reporting/alerts and continuous absence behavior

**Status:** APPROVED (IMPLEMENTATION DEFERRED)

**Business question:** Are continuous-absence thresholds, reporting definitions, and alerts part of Attendance, and what exact formulas and recipients apply?

**Proposed baseline:** Defer continuous-absence calculations and alerts. Attendance stores source events only; no threshold, denominator, notification, report, dashboard, or escalation behavior is authoritative without approved formula, date scope, timezone treatment, missing-data rule, recipient policy, and version.

**Approved by:** Client instruction received 2026-08-29  
**Approved on:** 2026-08-29

**Alternatives:** (A) defer all thresholds/alerts; (B) configurable threshold by branch; (C) fixed academy-wide threshold; (D) warning-only reporting with no notifications.

**Dependencies/entities:** AT-01/AT-02/AT-05, OPS-09, OPS-01, REP-01–REP-03, DASH-01–DASH-03; future attendance aggregates and reporting projections.

**Authorization/branch:** Any future report/alert must apply server-side branch scope, approved recipient policy, and least-privilege field visibility.

**Audit/concurrency:** Version formula/configuration and audit changes; do not recompute historical results silently after policy changes.

**Acceptance implications:** No implementation acceptance test is authorized until formula, threshold, scope, notification, and correction decisions are approved.

---

# Lead Management / CRM & Admission

### LD-01 — Lead status vocabulary

**Status:** APPROVED

**Decision:** Use configurable, approved lead statuses with an ordered funnel and a terminal Lost state.

**Approved by:** Client / Architecture
**Approved on:** 2026-08-28

**Source:** `REQUIREMENTS-REVIEW.md / LD-01`

### LD-02 — Lead ownership and reassignment

**Status:** APPROVED

**Decision:** Each lead has one accountable owner. Reassignment is restricted to authorized users and is audited.

**Approved by:** Client / Architecture
**Approved on:** 2026-08-28

**Source:** `REQUIREMENTS-REVIEW.md / LD-02`

### LD-03 — Duplicate leads

**Status:** APPROVED

**Decision:** Use configurable duplicate-detection rules to warn users. Automatic merging is not performed.

**Approved by:** Client / Architecture
**Approved on:** 2026-08-28

**Source:** `REQUIREMENTS-REVIEW.md / LD-03`

### LD-04 — Lead source attribution

**Status:** APPROVED

**Decision:** Store a primary lead source and support campaign/touchpoint history.

**Approved by:** Client / Architecture
**Approved on:** 2026-08-28

**Source:** `REQUIREMENTS-REVIEW.md / LD-04`

### LD-05 — Campaign definition

**Status:** APPROVED

**Decision:** Campaigns have a name, source, dates, branch scope, and active/inactive state.

**Approved by:** Client / Architecture
**Approved on:** 2026-08-28

**Source:** `REQUIREMENTS-REVIEW.md / LD-05`

### LD-06 — Admission probability

**Status:** APPROVED

**Decision:** Admission probability is manually entered using an approved scale and is clearly treated as a non-authoritative estimate.

**Approved by:** Client / Architecture
**Approved on:** 2026-08-28

**Source:** `REQUIREMENTS-REVIEW.md / LD-06`

### LD-07 — Trial rules

**Status:** APPROVED

**Decision:** A trial is a schedulable event with booking, attendance, cancellation, and rescheduling history.

**Approved by:** Client / Architecture
**Approved on:** 2026-08-28

**Source:** `REQUIREMENTS-REVIEW.md / LD-07`

### LD-08 — Lost lead handling

**Status:** APPROVED

**Decision:** Closing a lead as Lost requires a reason and closed date. Authorized users may reopen a Lost lead with an audit record.

**Approved by:** Client / Architecture
**Approved on:** 2026-08-28

**Source:** `REQUIREMENTS-REVIEW.md / LD-08`

### LD-09 — Selection criteria

**Status:** APPROVED

**Decision:** The Coach provides the selection recommendation. An authorized Admission/Admin user confirms the selection.

**Approved by:** Client / Architecture
**Approved on:** 2026-08-28

**Source:** `REQUIREMENTS-REVIEW.md / LD-09`

### LD-10 — Conversion definition

**Status:** APPROVED

**Decision:** Admission completion is the primary conversion event. Active-player conversion is reported separately where required.

**Approved by:** Client / Architecture
**Approved on:** 2026-08-28

**Source:** `REQUIREMENTS-REVIEW.md / LD-10`

### LD-11 — Fees and payments

**Status:** APPROVED

**Decision:** Fees, payments, discounts, refunds, and financial records remain outside the current scope. No financial workflow or tables are to be inferred from the admission requirement.

**Approved by:** Client / Architecture
**Approved on:** 2026-08-28

**Source:** `REQUIREMENTS-REVIEW.md / LD-11`

### LD-12 — Counselor KPIs

**Status:** APPROVED

**Decision:** Counselor KPIs will be based on defined CRM activity and conversion metrics. KPI formulas must be documented before implementation.

**Approved by:** Client / Architecture
**Approved on:** 2026-08-28

**Source:** `REQUIREMENTS-REVIEW.md / LD-12`

### LD-13 — CRM Follow-Up workflow

**Status:** APPROVED

**Decision:** Define a minimal operational Follow-Up record owned by CRM and linked to exactly one Lead. The approved baseline is:

1. **Type/purpose:** Use a controlled list: `call`, `whatsapp`, `email`, `meeting`, and `other`. The list is configuration owned by CRM administration; arbitrary free text is not accepted as a type. A free-text note records the interaction detail.
2. **Status:** Use `pending`, `completed`, and `cancelled`. New records start as `pending`. A Follow-Up may be completed or cancelled only by an authorized user; cancelled records remain historical evidence.
3. **Outcome:** Use `no_answer`, `contacted`, `interested`, `callback_requested`, `not_interested`, and `invalid_contact`. Outcome is required when completing a Follow-Up and must be absent or null while pending. Cancellation uses a required reason instead of an outcome.
4. **Scheduling:** Store an optional Follow-Up date/time using the approved MCC timezone input and UTC persistence convention. Past date/times are allowed for recording historical work; a new pending Follow-Up defaults to a future date/time in the UI but server validation does not require future scheduling. A next Follow-Up is a separate record linked to the same Lead; completing one does not automatically create another.
5. **Lead relationship and ownership:** Each Follow-Up belongs to exactly one Lead and inherits the Lead’s branch scope. The Lead’s accountable owner is the default assignee/actor context; authorized CRM staff in the Lead’s branch may create and manage it. Reassignment of the Lead does not rewrite Follow-Up history.
6. **Editing/deletion:** Authorized `lead.update` users may edit pending Follow-Ups. Completed and cancelled Follow-Ups are immutable except for a narrowly controlled correction by the same permission with a mandatory reason and audit. Physical deletion is not permitted; cancellation is the retention-preserving alternative.
7. **History:** Lead Details displays all Follow-Ups in chronological order, newest first for operational use, showing type, scheduled date/time, status, outcome, note, actor, and completion/cancellation timestamp and reason where present.
8. **Notifications:** Phase 1 sends no reminders, email, WhatsApp, push, or other Follow-Up notifications. Notification behavior remains outside this decision and requires the existing notification decisions before implementation.
9. **Permissions:** Reuse `lead.view` for viewing Follow-Up history and `lead.update` for create/update/complete/cancel operations. All permissions are evaluated server-side within the Lead’s authorized branch scope; no new Follow-Up permission is introduced.
10. **Audit:** Audit create, update, completion, and cancellation with actor, action, Lead/Follow-Up entity identifiers, UTC timestamp, branch, and appropriate before/after values or reason. Deletion has no event because deletion is not approved. Audit retention/access remains subject to `OPS-01`.

**Approved baseline:** The rules above: controlled operational types, three statuses, required outcome on completion, optional date/time with historical past dates permitted, separate next records, immutable completed/cancelled history, no Phase 1 notifications, existing Lead permissions, and append-oriented audit.

**Alternatives considered:** Free-text types; pending/completed only; allowing completion without an outcome; future-only scheduling; one mutable “next follow-up” field; editable/deletable history; automatic reminders; dedicated Follow-Up permissions; and automatic Lead Status changes.

**Consequences:** The baseline keeps Phase 1 small, searchable, auditable, branch-safe, and compatible with the existing `lead_follow_ups` ownership model. Controlled values require configuration and validation. Immutable historical records prevent silent evidence changes but require correction-by-audited-update or cancellation. No notification load, consent, provider, retry, or delivery state is introduced. This decision does not authorize migrations, code, UI, reminders, KPIs, or Lead Status transitions beyond the existing approved Lead workflow.

**Acceptance/test impact:** Tests must cover authenticated and unauthenticated access, `lead.view`/`lead.update`, branch isolation, Lead ownership scope, controlled type/status/outcome validation, required completion outcome, past and future scheduling, separate next records, immutable/correction behavior, chronological history, no physical deletion, audit metadata, safe retries/concurrency, and the absence of Phase 1 notifications.

**Approved by:** Client / Product Owner
**Approved on:** 2026-08-31
**Review:** Architecture and Security review applies to permission, branch, audit, and retention implications.

**Related requirements:** `REQ-CRM-001`, `REQ-AUDIT-001`.
**Related decisions:** `LD-01`, `LD-02`, `OPS-01`, `OPS-09`.
**Source:** `REQUIREMENTS.md`, `REQUIREMENTS-REVIEW.md`, and client decision workshop.

---

## Notes

Only decisions marked **APPROVED** may be used as implementation requirements.
PROPOSED, REJECTED, and DEFERRED decisions must not be implemented.

The approval statements above are the only approval evidence found in this repository. No separate approval artifact was found. The historical gap inventory in `REQUIREMENTS-REVIEW.md` is reconciled by this register: an item with an approved record is no longer unresolved; all items below remain unresolved.

---

# Governance, Security, Data, and Operations Gaps

### GOV-01 — Requirement approval governance

**Status:** APPROVED

**Decision:** Requirements require named product-owner approval with an auditable decision record; architecture/security review is required where relevant.

**Context:** Business modules must not be implemented from draft analysis alone.
**Options considered:** Named client approver; change-control group; documented product-owner approval workflow.
**Chosen option:** Named product owner approval with architecture/security review where relevant.
**Consequences:** Approval evidence is defined; draft requirements remain non-implementable until explicitly approved.
**Owner:** Project Owner (name not provided)
**Approver:** Project Owner (name not provided)
**Approval date:** 2026-08-28
**Source:** PHASE-1-DECISIONS.md; explicit project-owner approval instruction received 2026-08-28.
**Related requirements:** All implementation-ready requirements.
**Related modules:** All.

### GOV-02 — Requirements, formulas, thresholds, and policy change control

**Status:** APPROVED

**Decision:** Changes to requirements, formulas, thresholds, and policies require versioned decision records with impact assessment, effective date, and preserved history.

**Context:** Silent changes could alter access, reports, calculations, or safeguarding outcomes.
**Options considered:** Formal change-control record; owner approval with versioned decision record.
**Chosen option:** Versioned decision with impact, effective date, and history.
**Consequences:** Approved changes are traceable and historically distinguishable; dependent implementation still requires the specific business decision and acceptance evidence.
**Owner:** Project Owner (name not provided)
**Approver:** Project Owner (name not provided)
**Approval date:** 2026-08-28
**Source:** PHASE-1-DECISIONS.md; explicit project-owner approval instruction received 2026-08-28.
**Related requirements:** NFR-06, REQ-DASH-001, REQ-REPORT-001, REQ-PERF-001, REQ-FIT-001.
**Related modules:** Reporting, Dashboard, Performance, Fitness, Development.

### SEC-12 — Malware scanning policy

**Status:** APPROVED

**Decision:** Uploaded files must be scanned before availability; failures must be quarantined and scan outcomes audited.

**Context:** Sensitive documents and media must not be stored or served before safety checks.
**Options considered:** Managed scanning service; approved on-host scanner; manual review fallback.
**Chosen option:** Scan before availability, quarantine failures, and audit scan outcomes.
**Consequences:** Secure upload may be designed against a defined scanning gate; scanner/provider selection and document policy decisions remain separately governed.
**Owner:** Project Owner (name not provided)
**Approver:** Project Owner (name not provided)
**Approval date:** 2026-08-28
**Source:** PHASE-1-DECISIONS.md; explicit project-owner approval instruction received 2026-08-28.
**Related requirements:** REQ-DOC-001, REQ-DOC-002.
**Related modules:** Documents/Media.

### SEC-13 — Consent and legal basis, including minors

**Status:** APPROVED

**Decision:** Consent or legal-basis records must capture guardian authority, evidence, expiry, withdrawal, and audit treatment for minors, personal data, media, and documents.

**Context:** The SRS includes minors, guardian information, photographs, videos, and medical data.
**Options considered:** Documented consent register; policy-based legal-basis register; owner-approved hybrid.
**Chosen option:** Consent register with guardian authority, evidence, expiry, withdrawal, and audit.
**Consequences:** Consent/legal-basis handling has a defined baseline; category visibility, portal access, and retention decisions remain separately unresolved.
**Owner:** Project Owner (name not provided)
**Approver:** Project Owner (name not provided)
**Approval date:** 2026-08-28
**Source:** PHASE-1-DECISIONS.md; explicit project-owner approval instruction received 2026-08-28.
**Related requirements:** REQ-DOC-001, REQ-PORTAL-001, NFR-04.
**Related modules:** Documents, Media, Parent Portal, Player Portal.

### SEC-14 — Account recovery and guardian verification

**Status:** APPROVED

**Decision:** Account recovery must use expiring single-use recovery, rate limits, verified relationship checks, session invalidation, and audit events.

**Context:** Recovery is security-sensitive and guardian accounts must not be linked to a child without verification.
**Options considered:** Verified email; verified phone; staff-assisted recovery; approved combination.
**Chosen option:** Expiring, single-use recovery with rate limits, verified relationship checks, session invalidation, and audit events.
**Consequences:** Generic account-recovery and relationship-verification design may proceed against a defined security baseline; portal visibility and guardian policy decisions remain unresolved.
**Owner:** Project Owner (name not provided)
**Approver:** Project Owner (name not provided)
**Approval date:** 2026-08-28
**Source:** PHASE-1-DECISIONS.md; explicit project-owner approval instruction received 2026-08-28.
**Related requirements:** REQ-AUTH-001, REQ-PORTAL-001.
**Related modules:** Authentication, Parent Portal, Player Portal.

#### SEC-14 approved recovery rate-limit policy

**Status:** APPROVED

**Approved by:** Product Owner + Security/Operations approver

**Approval date:** 2026-08-28

**Scope:** Apply two independent rolling-window limits to `POST /auth/recovery/request`:

- Account scope: maximum 5 recovery requests per 15-minute rolling window.
- IP scope: maximum 10 recovery requests per 15-minute rolling window.

**Security behavior:** Both limits use the existing generic recovery response. The client must not learn which limit triggered, whether a limit triggered, or whether the email/account exists. Reset tokens remain hashed, short-lived, single-use, and excluded from logs. A successful password reset clears the account recovery-request rate-limit state. Existing authentication account lockout remains a separate control and must not be conflated with recovery rate limiting.

**Audit and logging:** Rate-limit events may be audited, but audit and log data must exclude passwords, reset tokens, session IDs, password hashes, credentials, and other secrets.

**Architecture:** Implement through an abstraction that supports multiple application instances. Production storage must not be an application-local in-memory counter. The backing mechanism must be deployment-configurable and must not require hard-coded infrastructure credentials.

**Failure behavior:** Rate-limit enforcement must fail safely and must not silently bypass the security control when its backing mechanism fails. The user still receives a safe generic response without account enumeration. Operational failures must be observable without exposing secrets.

**Required acceptance tests:** Account limit; IP limit; independent operation of both limits; rolling-window boundary/reset; enumeration resistance; successful-reset clearing of approved account rate-limit state; interaction with existing account lockout; concurrent requests; backing-store failure; and absence of sensitive data in audit/log output.

**Approval rationale:** The exact account and IP rolling-window controls are required to protect the approved recovery flow against automated abuse while preserving enumeration resistance.

**Implementation authorization:** The approved policy authorizes only the exact thresholds, behavior, storage abstraction, and acceptance tests recorded above. No threshold or behavior modification is authorized without a new approval.

### DAT-01 — Data quality ownership

**Status:** APPROVED

**Decision:** Assign named module owners, a central data steward, and audited correction authority for validation rules, duplicate review, corrections, reference data, and data-quality reporting.

**Context:** CRM conversion, player identity, attendance, and reports depend on reliable source data.
**Options considered:** Module owner; central data steward; shared ownership matrix.
**Chosen option:** Named module owners with a central data steward and audited correction authority.
**Consequences:** Data-quality ownership is defined for CRM, admissions, players, and reporting; implementation must still evidence the controls and tests.
**Owner:** Project Owner (name not provided)
**Approver:** Project Owner (name not provided)
**Approval date:** 2026-08-28
**Source:** PHASE-1-DECISIONS.md; explicit project-owner approval instruction received 2026-08-28.
**Related requirements:** REQ-CRM-001, REQ-PLAYER-001.
**Related modules:** CRM, Admissions, Players, Reporting.

### REL-01 — Parent/child cardinality

**Status:** APPROVED

**Decision:** Use explicit relationship records supporting multiple guardians and multiple players.

**Context:** Player conversion and guardian records require defined relationship cardinality.
**Options considered:** One parent/one player; flat parent fields; explicit many-to-many relationships.
**Chosen option:** Explicit relationship records supporting multiple guardians and players.
**Consequences:** Guardian/player relationships remain separately represented and can be authorized without granting portal visibility by implication.
**Owner:** Project Owner (name not provided)
**Approver:** Project Owner (name not provided)
**Approval date:** 2026-08-28
**Source:** PHASE-1-DECISIONS.md; explicit project-owner approval instruction received 2026-08-28.
**Related requirements:** REQ-PLAYER-001.
**Related modules:** Players, Guardians.

### REL-02 — Guardian/contact types

**Status:** APPROVED

**Decision:** Distinguish guardian, emergency-contact, and primary-contact relationships explicitly.

**Context:** Player/contact data requires distinct semantics for validation, safeguarding, and future authorization.
**Options considered:** One contact type; flat parent fields; distinct relationship/contact types.
**Chosen option:** Separate guardian, emergency-contact, and primary-contact types.
**Consequences:** Contact responsibilities are unambiguous; access remains subject to approved authorization and visibility policies.
**Owner:** Project Owner (name not provided)
**Approver:** Project Owner (name not provided)
**Approval date:** 2026-08-28
**Source:** PHASE-1-DECISIONS.md; explicit project-owner approval instruction received 2026-08-28.
**Related requirements:** REQ-PLAYER-001.
**Related modules:** Players, Guardians.

### REL-03 / REL-04 / REL-05 — User-provided Admission transfer direction

**Status:** NEEDS_DECISION

**User-provided scoped direction (2026-09-24; not client approval):** During an approved Admission conversion, non-empty father and mother names are linked as `guardian` contacts and a non-empty `emergency_contact` is linked as `emergency-contact`. Admission parent email and mobile are attached only to the first newly created guardian. Conversion retries must not create duplicate matching relationships, manually added Player contacts must be preserved, and the source Admission must be recorded in audit data. Existing branch authorization and Player visibility checks continue to govern display.

**Unresolved client decisions:** REL-03 category-level and portal visibility; REL-04 Player/guardian visibility and eligibility; REL-05 relationship editing, revocation, deletion, and transfer policy. This direction does not approve any of those broader policies.

**Related requirements:** REQ-PLAYER-001, REL-03, REL-04, REL-05.
**Related modules:** Admissions, Players, Guardians.

### DAT-02 — Data migration and reconciliation

**Status:** APPROVED

**Decision:** Evidence not found. Define source inventory, mapping, validation, duplicate handling, reconciliation, cutover, rollback, and sign-off.

**Context:** The repository contains no business schema or migration evidence.
**Options considered:** No migration; controlled import; staged migration with reconciliation.
**Chosen option:** None.
**Consequences:** Production data onboarding and import requirements remain blocked.
**Owner:** NEEDS_DECISION
**Approver:** NEEDS_DECISION
**Approval date:** NEEDS_DECISION
**Related requirements:** REQ-PLAYER-001, REQ-CRM-001.
**Related modules:** CRM, Players, Reporting.

### OPS-01 - Audit scope, retention, access and append-only policy

**Status:** APPROVED

**Decision:**

1. **Audit retention:** Retain security and business audit records for **7 years**. Do not automatically purge audit records during normal application operation. After the retention period, disposal may occur only through an approved administrative/operational process. Any disposal must itself produce an audit record where technically possible.

2. **Audit read access:** Audit records are sensitive administrative/security records. Normal users/staff and branch-level operational staff have no unrestricted audit-log access. Users with an explicitly approved audit/security permission may view audit records within their authorized scope. Super administrators/security/operations administrators may access audit records across branches where their role explicitly permits it. Audit access itself must be audited.

3. **Branch scope:** For branch-owned business entities, branch-authorized users may view audit records only within their authorized branch scope. Cross-branch access requires explicit administrative/security authorization. System/security events without a branch remain accessible only to authorized administrative/security roles.

4. **Export:** Audit-log export is not part of the current implementation scope. No audit export feature is authorized by this decision.

5. **Administrative access:** Administrative/security/operations access is explicitly permission-controlled. UI restrictions are not sufficient. Every audit-read endpoint must enforce authorization server-side.

6. **Append-only behavior:** Audit records are strictly append-only. Application users and normal application services must not update, delete, or overwrite audit records. Corrections preserve the original record and create a new compensating/correction event with reason, actor, and a relationship to the original event where technically possible.

7. **Database protection:** Use the strongest technically appropriate protection available in the deployment architecture. Application update/delete paths must not exist, audit persistence must use the approved audit boundary, readers must not receive write capability, and database privileges/triggers should be used where supported and appropriate. Existing security controls must not be weakened.

8. **Audit metadata:** Preserve, where applicable, actor, action, entity type, entity ID, branch, timestamp, reason, before summary, and after summary. Never store passwords, password hashes, reset tokens, session secrets, SMTP credentials, authentication secrets, or other sensitive secrets.

9. **Audit-read behavior:** A dedicated audit-read interface may be implemented only for authorized users. It must require authentication, enforce RBAC and branch scope server-side, avoid exposing secrets, audit audit-log access, and provide no modification/deletion capability.

10. **Required evidence:** Tests must prove unauthorized read denial, branch-boundary enforcement, authorized administrative reads, application-level modification/deletion denial, compensating correction events, audit-of-audit access, and sensitive-value exclusion.

**Implementation permission:** The existing RBAC architecture requires an explicit permission for the approved audit-read boundary. The permission name `audit.view` is documented for that purpose; assignment remains explicit and permission-controlled.

**Approved by:** Project owner instruction received 2026-08-31

**Approved on:** 2026-08-31

**Related requirements:** REQ-AUDIT-001, NFR-04, REQ-OPS-001.

**Related modules:** Authentication, CRM, Trials, Admissions, Players, Scheduling, Attendance, Administration, and future documents/reports.

---

### OPS-11 — Incident response

**Status:** NEEDS_DECISION

**Decision:** Evidence not found. Define detection, severity, containment, notification, evidence preservation, recovery, post-incident review, and ownership.

**Context:** Sensitive data and branch isolation require a documented response to security and availability incidents.
**Options considered:** Internal runbook; managed response; hybrid process.
**Chosen option:** None.
**Consequences:** Operational release gate is incomplete.
**Owner:** NEEDS_DECISION
**Approver:** NEEDS_DECISION
**Approval date:** NEEDS_DECISION
**Related requirements:** REQ-OPS-001, NFR-05.
**Related modules:** Platform operations.

### OPS-12 — Observability and alerting

**Status:** NEEDS_DECISION

**Decision:** Evidence not found. Define metrics, logs, traces, alert thresholds, sensitive-data redaction, retention, escalation, and ownership.

**Context:** Availability, authorization failures, upload failures, and backup failures require detection without leaking sensitive data.
**Options considered:** Application/platform monitoring; managed monitoring; hybrid.
**Chosen option:** None.
**Consequences:** Monitoring and operational acceptance cannot be marked ready.
**Owner:** NEEDS_DECISION
**Approver:** NEEDS_DECISION
**Approval date:** NEEDS_DECISION
**Related requirements:** REQ-OPS-001, NFR-01, NFR-04.
**Related modules:** Platform operations.

### INT-01 — Password recovery delivery and future integration contracts

**Status:** APPROVED

**Decision:** Recovery delivery is approved through a self-managed SMTP/relay hosted and operated by MCC infrastructure. SMTP infrastructure ownership and operation remain with MCC. This approval does not select SMTP hosts, ports, domains, credentials, TLS modes, retry values, or other deployment-specific parameters.

**Context:** Recovery delivery requires a defined integration contract without weakening SEC-14 recovery controls or exposing credentials, reset tokens, account existence, or sensitive recovery data.

**Options considered:** Managed email provider; approved SMTP/relay; staff-assisted recovery; adapter contract per provider; integration gateway; no integration until separately approved.
**Chosen option:** Option B — Self-managed SMTP/relay hosted and operated by MCC infrastructure.
**Consequences:** SMTP credentials/secrets must never be committed to source control and must be stored only in environment/secret configuration. SMTP authentication and TLS are mandatory where supported. Recovery responses remain enumeration-resistant; reset tokens remain short-lived, single-use, securely generated, and never logged. Application logs must not contain SMTP credentials, reset tokens, or sensitive recovery data. Delivery failures must produce safe generic application responses. Retry behavior must be bounded and idempotent. SMTP outages must be observable and auditable without exposing secrets. Credential rotation must be supported. SMTP availability, queue/delivery failures, and operational ownership must be documented. Production mail-server configuration is an operational deployment responsibility.
**Implementation boundary:** This decision authorizes implementation planning and verification of the approved mechanism only. It does not authorize arbitrary provider/server details, hard-coded credentials, `.env.example` secrets, or unrelated integrations.
**Recovery HTTP contract:** The single authoritative request/reset endpoint contract is defined in `docs/REQUIREMENTS.md` under “Password-recovery HTTP contract”; it does not add routes or implementation by itself.
**Owner:** MCC infrastructure / operations.
**Approver:** Product owner + Security/Operations approver.
**Approval date:** 2026-08-28.
**Related requirements:** REQ-AUTH-001, REQ-ADV-001; recovery-dependent portal behavior remains subject to REQ-PORTAL-001 decisions.
**Related modules:** Authentication and accounts; future Notifications and Platform integrations. Other future integrations remain individually governed.

### TRIAL-01 — Trial Management decision contract

**Status:** APPROVED

**Decision boundary:** This contract records the approved Trial Management policy. It authorizes implementation of the independent Trial workflow using existing Lead/CRM permissions and does not authorize new permissions, Session repurposing, or changes to unrelated modules.

**Authoritative workflow:**

```text
Lead → Trial Booked → Trial Attended → Selected → Admission Pending
```

#### Authority and authorization decisions

Approval is required for the exact existing role and/or permission authorized to:

- book a trial;
- reschedule a trial;
- cancel a trial;
- mark trial attendance;
- record a selection outcome;
- move a lead to Admission Pending.

The decision must also define whether each action has a distinct existing permission, shares an existing permission, or is unavailable until a permission is approved. No controller or UI role label may be treated as authorization.

#### Trial record contract

Approval is required for the Trial record fields and their required/optional/nullability rules:

| Field | Decision required |
|---|---|
| Lead | Required relationship and authoritative ownership |
| Branch | Required ownership source and derivation rule |
| Trial date | Required or optional; date boundary/timezone |
| Trial time | Required or optional; timezone and precision |
| Trial location/ground | Required or optional; relationship to existing grounds |
| Assigned coach/evaluator | Required or optional; eligible role/assignment |
| Booking status | Required vocabulary and lifecycle |
| Attendance status | Required vocabulary and source |
| Selection outcome | Required vocabulary and authority |
| Remarks | Optional/required, length, and visibility |
| Cancellation reason | Required when cancelled, length, and visibility |
| Reschedule history | Required append-only history fields and retention |

Approval must distinguish user-editable fields from server-controlled identifiers, timestamps, actor, branch, and audit metadata.

#### Lead relationship and cardinality

Approval is required for:

- whether one Lead may have multiple Trial records;
- whether re-trials are allowed and under what condition;
- which Trial is authoritative for selection;
- whether more than one future trial may be booked for a Lead;
- whether historical cancelled, no-show, or failed trials remain readable.

#### Session relationship

Approval is required to select exactly one boundary:

- Trials are independent records;
- each Trial links to one existing Session;
- one Session may contain multiple Trials;
- a dedicated Trial schedule is required.

Existing Session records must not be repurposed or altered for Trials until this relationship is approved.

#### Status and transition matrix

Approval is required for the allowed transition matrix, including:

```text
Lead → Trial Booked → Trial Attended → Selected → Admission Pending
```

The decision must define behavior for cancellation, rescheduling, no-show, failed/not selected, re-trial, invalid transitions, retries, and any permitted reversals. It must also define whether these are Trial states, Lead Status values, or both.

#### Branch scope

Approval is required for:

- which branch owns a Trial;
- whether branch transfer is allowed;
- whether staff may book or manage a Trial outside assigned branches;
- Super Admin scope and behavior;
- historical visibility after a branch becomes inactive.

Submitted branch identifiers must not be trusted without server-side resolution.

#### Attendance source

Approval is required to identify the authoritative attendance source: manual Trial action, the Attendance module, Session attendance, or another explicitly approved source. Duplicate or conflicting attendance sources must be prohibited or reconciled by an approved rule.

#### Audit contract

Approval is required for audit events and metadata for booking, rescheduling, cancellation, attendance marking, selection outcome, and transition to Admission Pending. The contract must specify actor, entity, branch, UTC timestamp, prior/new state, reason requirements, and sensitive-data exclusions.

#### Lead Status interaction

Approval is required to select one consistent state model:

- Trial workflow automatically updates Lead Status;
- Trial status is separate from Lead Status;
- both are updated together transactionally.

Historical Lead Status values must remain readable, and no duplicate or conflicting state system may be introduced.

**Approved contract:** Trials are independent, branch-owned records linked to one Lead. Authorized Lead/CRM staff use existing permissions within permitted branch scope; no new permission is introduced. A Lead may have historical re-trials but only one future active booking. Required fields are Lead, server-authorized Branch, trial date, trial time, and location. Coach/evaluator and remarks are optional; identifiers, timestamps, actor/audit data, workflow state, and branch authorization are server-controlled. Operational statuses are Booked, Attended, No Show, Cancelled, and Rescheduled; selection outcomes are Pending, Selected, and Not Selected. Attendance is manually recorded in Trial Management. Selection requires an Attended Trial, and only Selected may transition the Lead to Admission Pending. Trial history is preserved, rescheduling records prior evidence, and no Trial history is physically deleted. Trial workflow updates the existing admin-managed Lead Status values for Trial Booked, Trial Attended, Selected, and Admission Pending; missing/inactive required configuration fails safely. Required audit events cover booking, rescheduling, cancellation, attendance, no-show, selection, and the Admission Pending transition. Existing Sessions and the Attendance module are not modified or reused in this phase.

**Implementation status:** APPROVED_FOR_IMPLEMENTATION. The implementation must preserve existing Lead, Admission, branch, and test data and use forward-only schema changes.

**Related requirements:** REQ-CRM-001, REQ-ADMISSION-001, REQ-SESSION-001, REQ-BRANCH-001, REQ-RBAC-001, REQ-AUDIT-001.

**Source:** Approved SRS workflow and `REQUIREMENTS-REVIEW.md` transition analysis.

### TRIAL-BOOK-01 — Trial booking permission direction

**Status:** USER_PROVIDED_DIRECTION — IMPLEMENTED_FOR_SCOPED_WORK (not client approval)

**Direction source:** User-provided direction dated 2026-09-21. Approver identity and client approval date are not recorded.

**Scope:** Trial booking uses the centralized `trial.book` permission for its GET form route, POST booking route, Lead detail form visibility, and service authorization. The service must still check the Lead's branch against the user's current authorized branches and enforce existing Trial eligibility and validation. Other Trial management actions continue to use `lead.update`.

**Existing access preservation:** The migration grants `trial.book` to each existing role that currently has `lead.update`, preserving existing role-based booking access while separating booking authority from unrelated Lead edits. Super Admin booking is therefore available only when `trial.book` is present and the Lead's branch is currently authorized. No `lead.update` grant is added by this direction.

**Conflict requiring client confirmation:** Approved `TRIAL-01` states that Trial Management uses existing Lead/CRM permissions and does not authorize a new permission. This user-provided direction introduces `trial.book` for booking only. The implementation records the scoped user direction but does not supersede or claim client approval of `TRIAL-01`; reconciliation of this conflict remains `NEEDS_DECISION`.

**Related records:** `TRIAL-01`, `REQ-TRIAL-001`, and the Trial booking section of `docs/RBAC.md`.

### TRIAL-ELIG-01 — Lead-status eligibility for first Trial

**Status:** USER_PROVIDED_DIRECTION — IMPLEMENTED_FOR_SCOPED_WORK (not client approval)

**Direction source:** User-provided direction dated 2026-09-22, updating the earlier scoped direction dated 2026-09-21. Approver identity and client approval date are not recorded.

**Scope:** A Lead in any current status may be booked for its first Trial when no future booked Trial exists. The Lead-status restriction is removed; no status allow-list is applied by the eligibility check or booking service. Permission checks, branch isolation, validation, future-booking protection, and the existing booking flow are unchanged.

**Conflict requiring client confirmation:** `TRIAL-01` approves the Trial workflow beginning with `Lead → Trial Booked` but does not explicitly define first-Trial eligibility for every Lead status. This direction records the supplied implementation rule without changing `TRIAL-01` or claiming client approval. Reconciliation of status eligibility remains `NEEDS_DECISION`.

**Related records:** `TRIAL-01`, `TRIAL-BOOK-01`, and `REQ-TRIAL-001`.

### TRIAL-LOC-01 — Optional Trial location dropdown

**Status:** USER_PROVIDED_DIRECTION — IMPLEMENTED_FOR_SCOPED_WORK (not client approval)

**Direction source:** User-provided direction dated 2026-09-22. Approver identity and client approval date are not recorded.

**Scope:** Trial booking renders Trial location as an optional dropdown. Options come from active Grounds records in the Lead's already authorized branch; the empty option is valid. The selected value is preserved after validation failure, including a prior submitted value that is no longer in the current options. Server-side validation and the database workflow accept an empty location. Trial permissions, branch authorization, CSRF protection, eligibility, date/time validation, audit behavior, and routes are unchanged.

**Conflict requiring client confirmation:** The approved `TRIAL-01` contract lists location as a required Trial field and leaves the relationship to Grounds for approval. This direction records the supplied implementation rule without changing `TRIAL-01` or claiming client approval. Optionality and the Grounds relationship remain `NEEDS_DECISION`.

**Related records:** `TRIAL-01`, `TRIAL-ELIG-01`, and `REQ-TRIAL-001`.

---

## RBAC and Administrative Account Decisions

### RBAC-SA-01 — Super Admin test-account scope and credential lifecycle

**Status:** NEEDS_DECISION

**Record type:** User-provided direction; not documented as client approval. This entry records the supplied direction without authorizing account creation, permission assignment, credential generation, or delivery.

**User-provided direction:**

- Super Admin scope is all permissions in the approved permission catalog that is in effect when this decision takes effect.
- Permissions added to the catalog after that point require separate approval and are not automatically granted by this direction.
- Super Admin branch access is all branches.
- MCC account owner is the intended account creator and the intended owner for revocation or credential rotation after testing.
- Credential delivery channel remains `NEEDS_DECISION`; “secure” alone does not identify an MCC-approved channel.

**Unresolved approval and implementation conditions:**

- The approver identity and decision date were not supplied and are intentionally blank.
- No approved permission-catalog snapshot/version or effective date was supplied. The phrase “approved permission catalog” must resolve to an identifiable approved catalog before implementation.
- `RBAC-MATRIX` remains `NEEDS_DECISION`. `AGENTS.md` requires the final permission matrix to derive from approved MCC requirements and requires Super Admin access to follow explicit authorization rules. This entry does not approve the complete role-to-permission matrix.
- `SEC-03` approves multiple authorized branch assignments but does not itself approve all-branch Super Admin access. The supplied all-branch direction is recorded as pending approval and must be enforced explicitly server-side if approved.
- Current Admin user/role/branch administration routes require `rbac.manage`. No record establishes the named MCC account owner’s identity or that the owner is authorized for this action. Do not infer that permission from the title “MCC account owner.”
- The credential delivery channel, named owner identity, revocation trigger/timing, session invalidation procedure, and audit evidence remain unresolved.
- The candidate identifier `test.mcc.superadmin@example.invalid` was supplied in conversation; it is not approved, provisioned, or evidence of an account.

**Approver:** 
**Approval date:** 
**Decision source:** User-provided direction recorded in `docs/SUPER-ADMIN-TEST-ACCOUNT-DECISION-REQUEST.md`; no client approval evidence supplied.
**Related decisions/requirements:** `SEC-02`, `SEC-03`, `SEC-04`, `REQ-RBAC-001`, `REQ-BRANCH-001`, `RBAC-MATRIX`, `AUTH-DESIGN`.
**Related modules:** Administration, Authentication, Authorization, Branch Management.

## Unresolved decision index

The following stable IDs were identified in the requirements analysis but have no approval evidence. Each remains `NEEDS_DECISION`; the analysis in `REQUIREMENTS-REVIEW.md` supplies the detailed context and options. They are listed here so no unresolved requirement exists outside the authoritative register.

For every row in this index, the decision-record fields are defined as follows: **Context / why a decision is required** is the corresponding ambiguity in `REQUIREMENTS-REVIEW.md`; **PROPOSED recommendation** and **alternatives** are the matching client-facing entries in `CLIENT-DECISION-WORKSHOP.md`; **security implications**, **data implications**, and **operational implications** are stated in that entry’s consequences; **modules blocked** and **acceptance criteria affected** are stated in the same entry; **related requirements** are the linked requirements in `REQUIREMENTS.md` and the SRS traceability in the review. No row has an approver or approval date until the client workshop records one. This cross-reference is part of each indexed decision record and does not approve any recommendation.

| ID | Status | Decision required | Owner | Approver | Approval date |
|---|---|---|---|---|---|
| AT-01 | APPROVED | One unique player/session record; persisted roster eligibility; denominator deferred | Client | Client | 2026-08-29 |
| AT-02 | APPROVED | PRESENT/ABSENT/LATE/EXCUSED; no medical detail | Client | Client | 2026-08-29 |
| AT-03 | APPROVED | Dedicated attendance permissions; approved timing window; OPS-09 semantics | Client | Client | 2026-08-29 |
| AT-04 | APPROVED | Cancelled sessions create no attendance; persisted roster boundary | Client | Client | 2026-08-29 |
| AT-05 | APPROVED | Correction permission, reason, append-only history, stale-write rejection; thresholds deferred | Client | Client | 2026-08-29 |
| AT-06 | APPROVED (IMPLEMENTATION DEFERRED) | Source events only; thresholds, formulas, reports, alerts, and recipients deferred | Client | Client | 2026-08-29 |
| DASH-01 | NEEDS_DECISION | Dashboard KPI formulas and source fields | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| DASH-02 | NEEDS_DECISION | Role-specific dashboard variants | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| DASH-03 | NEEDS_DECISION | Dashboard refresh and performance behavior | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| DEV-01 | NEEDS_DECISION | Development scales and improvement formula | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| DEV-02 | NEEDS_DECISION | Development review workflow and locking | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| DOC-01 | NEEDS_DECISION | Document upload authority by type | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| DOC-02 | NEEDS_DECISION | Document verification authority/states | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| DOC-03 | NEEDS_DECISION | Unverified-document visibility | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| DOC-04 | NEEDS_DECISION | Parent/player document access | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| DOC-05 | NEEDS_DECISION | Medical-document role visibility | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| DOC-06 | NEEDS_DECISION | Encryption at rest and key management | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| DOC-07 | NEEDS_DECISION | Document retention and deletion periods | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| DOC-08 | NEEDS_DECISION | Document/media replacement and version policy | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| FIT-01 | NEEDS_DECISION | Fitness units, ranges, and precision | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| FIT-02 | NEEDS_DECISION | Fitness risk thresholds, severity, and alerts | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| FIT-03 | NEEDS_DECISION | Fitness visibility by role | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| HP-01 | NEEDS_DECISION | High-performance eligibility | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| HP-02 | NEEDS_DECISION | Selection-readiness meaning/calculation | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| HP-03 | NEEDS_DECISION | Benchmark ownership and change control | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| HP-04 | NEEDS_DECISION | High-performance program lifecycle | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| HP-05 | NEEDS_DECISION | Normal versus high-performance data boundary | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| MKT-01 | NEEDS_DECISION | Marketing ROI data source | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| MKT-02 | NEEDS_DECISION | Lead attribution model | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| MKT-03 | NEEDS_DECISION | Campaign ownership/scope | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| MKT-04 | NEEDS_DECISION | Cost-per-admission data and timing | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| MT-01 | NEEDS_DECISION | Match/tournament relationship | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| MT-02 | NEEDS_DECISION | Shared competition scope | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| MT-03 | NEEDS_DECISION | Playing XI authority | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| MT-04 | NEEDS_DECISION | Scorecard authority and locking | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| MT-05 | NEEDS_DECISION | MVP and selection-status rules | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| MT-06 | NEEDS_DECISION | Tournament hierarchy | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| NOT-01 | NEEDS_DECISION | Required notification channels and phase | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| NOT-02 | NEEDS_DECISION | Notification recipients, templates, timing, retries, opt-out | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| NOT-03 | NEEDS_DECISION | Notification delivery audit and failure handling | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| OPS-01 | APPROVED | Audit scope, 7-year retention, access, branch scope, no export, append-only behavior, database protection, metadata, and audit-read evidence | Project owner instruction | Project owner instruction | 2026-08-31 |
| OPS-02 | NEEDS_DECISION | Backup retention, ownership, and restore objectives | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| OPS-03 | NEEDS_DECISION | Private storage provider/abstraction deployment | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| OPS-04 | NEEDS_DECISION | API consumers and endpoint scope | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| OPS-05 | NEEDS_DECISION | UI framework | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| OPS-06 | NEEDS_DECISION | Bulk import/export formats and controls | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| OPS-07 | NEEDS_DECISION | Search fields, ranking, and sensitive-data behavior | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| OPS-08 | NEEDS_DECISION | Performance measurement dataset/load/environment | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| OPS-09 | APPROVED | MCC `Asia/Kolkata` presentation; UTC database/audit timestamps; explicit timezone input | Client | Client | 2026-08-29 |
| OPS-10 | NEEDS_DECISION | Entity-specific data retention/deletion | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| PF-01 | NEEDS_DECISION | Performance source grain | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| PF-02 | NEEDS_DECISION | Batting formulas and edge cases | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| PF-03 | NEEDS_DECISION | Bowling formulas and over representation | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| PF-04 | NEEDS_DECISION | Performance correction and locking | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| PF-05 | NEEDS_DECISION | Coach rating scale | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| PF-06 | NEEDS_DECISION | Graph aggregation and correction behavior | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| RBAC-SA-01 | NEEDS_DECISION | Super Admin permissions/branch scope, catalog snapshot/effective date, account-creation authority, credential delivery, and post-test revocation/rotation | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| REL-01 | APPROVED | Parent/child cardinality | Project Owner (name not provided) | Project Owner (name not provided) | 2026-08-28 |
| REL-02 | APPROVED | Guardian/contact types | Project Owner (name not provided) | Project Owner (name not provided) | 2026-08-28 |
| REL-03 | NEEDS_DECISION | Parent category-level visibility | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| REL-04 | NEEDS_DECISION | Player visibility and eligibility | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| REL-05 | NEEDS_DECISION | Relationship change and revocation | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| REP-01 | NEEDS_DECISION | Report columns | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| REP-02 | NEEDS_DECISION | Report filters/date semantics | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| REP-03 | NEEDS_DECISION | Report authorization | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION |
| SCH-01 | APPROVED | One-time sessions; recurrence deferred | Client | Client | 2026-08-29 |
| SCH-02 | APPROVED | Scheduling authority, ownership, and eligibility | Client | Client | 2026-08-29 |
| SCH-03 | APPROVED | Capacity and booking conflicts | Client | Client | 2026-08-29 |
| SCH-04 | APPROVED | Cancellation and linked rescheduling | Client | Client | 2026-08-29 |
| SCH-05 | APPROVED | Coach availability and leave precedence | Client | Client | 2026-08-29 |
| LD-13 | APPROVED | CRM Follow-Up types, statuses, outcomes, scheduling, ownership, history, permissions, notifications, and audit | Client / Product Owner | Client / Product Owner | 2026-08-31 |
| TRIAL-01 | APPROVED | Independent Lead-linked Trial records; existing Lead/CRM authorization; approved statuses, transitions, branch scope, manual attendance, audit, and Lead Status interaction | Client | Client | 2026-08-30 |

### GROUND-01 — Maintenance lifecycle

**Status:** NEEDS_DECISION

Ground maintenance records currently support creation and read-only display. The requirements do not approve whether a maintenance period may be corrected, edited, cancelled, or deleted after creation. No correction or deletion workflow is implemented until the lifecycle, authority, reason, history, and audit rules are approved. Existing behavior for maintenance overlapping an already-booked session remains unchanged.
## Coach and Attendance scoped user-direction reconciliation (2026-09-25; not client approval)

### AT-02 reconciliation

The user-provided direction selects exactly `PRESENT`, `ABSENT`, `LEAVE`, `MEDICAL`, `LATE`, `RAIN_CANCELLED`, and `COACH_CANCELLED`. Existing `EXCUSED` rows are mapped to `LEAVE` by a forward-only migration and are not deleted. Leave and Medical are excluded from percentage denominators; no medical detail is stored in Attendance. This is a user-provided scoped direction and does not claim client approval of broader AT-02 policy.

### AT-06 reconciliation

The user-provided direction defines daily local-calendar, Monday-Sunday weekly, calendar-month, and calendar-year percentages over occurred scheduled sessions excluding cancelled sessions; Leave and Medical are excluded from denominators; Present and Late count as attended; Absent counts as not attended; and zero denominators display `No data`. Below-75%, three-consecutive-eligible-absence, and Late alerts are report-ready/in-app data only for authorized branch staff, with no external delivery unless an approved integration exists. AT-06 remains unresolved for client approval of formula versioning, recipient detail, visibility beyond authorized branch staff, correction propagation, and notification policy.

### PA-02 reconciliation

The user-provided direction confirms that Coaches may be assigned to multiple authorized branches, may be assigned only to sessions in those branches, and that Branch Admin/Super Admin management remains limited to their current authorized branches. Existing identity, branch history, audit, and `player.coach.assign` boundaries remain in force. PA-02’s remaining authority, availability/leave, capacity, correction, and concurrency wording is not expanded by this direction and remains subject to the existing record.

### Coach implementation evidence (2026-09-25; not client approval)

The current implementation verifies the defined source-backed Coach slice without adding a new policy: `coach_profiles` stores and validates optional Qualification, Certifications, Availability notes, Specialization, and Experience values; `user_branches` supplies authorized branch assignments; `sessions` supplies assigned-session reads; and `coach_unavailability` supplies upcoming leave/unavailability reads. `CoachManagementService::kpis()` calculates Sessions conducted, Attendance compliance, and Assigned players from existing session, roster/attendance, and player-coach assignment records, gated by `coach.kpi.view` and current authorized branch scope. Player feedback rating and Performance improvement index remain unavailable because approved source records and formulas are absent. This evidence does not resolve `CM-02` branch-assignment authority, `CM-03` Coach-specific session assignment, `CM-04` leave/availability writes, `CM-05` KPI source/formula/visibility policy, or `CM-06` Coach history/correction policy; those items remain `NEEDS_DECISION`. No new migration is required for this verified slice.

### Coach Management scoped user-direction expansion (2026-09-25; not client approval)

The user-provided direction authorizes a bounded implementation slice: multi-branch Coach assignments within current actor authorization; session assignment and pre-start reassignment within the session branch, with reasoned authorized correction after start; branch-scoped leave/availability writes with required dates/reason, overlap rejection, retained approved history, and no attendance mutation; append-only audit metadata and reasoned corrections; 1–5 completed-session Player feedback ratings averaged by reporting period; and a performance improvement index comparing earliest/latest approved comparable Player performance records. KPI definitions must be versioned in documentation, calculated from source records in the configured application timezone, remain permission/branch scoped, recalculate after source correction, and show `Not yet available` when data is insufficient. Existing `coach.assign`, `coach.update`, `coach.kpi.view`, scheduling permissions, and centralized audit services remain the authorization boundary unless a later explicit record changes it. This direction does not claim client approval or resolve unspecified role precedence, correction windows, retention, or source-data gaps.

Implementation limitation: PII-v1 adds user-provided approval-marker and ordering direction, implemented by a forward-only migration. Existing records without approval markers remain excluded, and wider PF/CM policy and client approval remain unresolved.

### CM-04A — Coach availability policy (user-provided direction)

**Status:** Implemented as user-provided direction on 2026-09-25; not client approval.

Store either a recurring weekly weekday interval or a one-time date interval per Coach and branch. Use the configured application timezone. Require start time before end time and reject overlapping active intervals for the same Coach, branch, weekday/date, and period. Management requires existing `coach.update` authorization and current branch access; Coaches may view their own records but cannot edit without an existing permission. Approved leave takes precedence during scheduling checks. Edits and cancellations require a reason and append-only audit metadata. Availability history is retained and existing sessions and attendance are not rewritten. Leave approval, external alerts, and unresolved KPI rules remain `NEEDS_DECISION`.

### SCH-05A — Availability is informational during scheduling (user-provided direction)

**Status:** User-provided direction — not client approval (2026-09-30)

Session creation, editing, rescheduling, and Coach reassignment do not enforce configured availability intervals. They continue to enforce approved leave overlap, Coach active status and branch assignment, session-window validity, scheduling conflicts, permissions, and Player eligibility. Availability records remain managed and displayed through Coach workflows; they are not deleted or changed by this decision. Cross-calendar-day handling remains governed only by independent session-window rules, not by availability lookup.

### SCH-06 — Session completion timing

**Status:** User-provided direction recorded 2026-09-30; not client approval.

Completion does not require the current time to be after `ends_at`. A scheduled session may be completed when it has a non-empty immutable roster snapshot and complete attendance, and the existing branch-scoped `session.update`, cancellation, audit, transaction, and idempotency rules continue to apply.

### CM-04B — Coach leave approval workflow

**Status:** User-provided direction recorded 2026-09-25; not client approval.

New leave is created as `PENDING`. Branch Admin and Super Admin users authorized by the existing `coach.update` permission may approve or reject leave in permitted branches. Rejection requires a reason. A Coach may cancel their own pending leave; an authorized manager may cancel pending or approved leave with a reason. Approved leave continues to take precedence in scheduling checks and prevents new conflicting session assignments. Attendance history is unchanged. Leave rows are retained, and transitions are append-only audit events containing actor, branch, timestamp, previous status, new status, and reason. No new permission is introduced.


### PII-v1 — Performance Improvement Index

**Status:** User-provided implementation direction recorded 2026-09-25; not client approval.

The PII uses approved `performance_records` only. Baseline is the earliest approved record and latest assessment is the most recent approved record for the same Player, skill, and metric, ordered by `recorded_on` and record ID. At least two comparable approved records are required. Missing, invalid, incomparable, and zero-baseline values are excluded. Each valid metric uses `((latest - baseline) / abs(baseline)) * 100`; the index is the average of valid metric results and retains negative values. No valid comparison returns `Not yet available`. Formula version `PII-v1` is documented. `coach.kpi.view` and current branch authorization protect Coach visibility; source corrections require a reason, append-only audit metadata, and read-time recalculation. This direction does not resolve the wider `PF-01`–`PF-06` or client replacement-baseline decisions.

### Documents secure-default direction (2026-09-27; user-provided direction — not client approval)

This versioned direction records secure defaults for later reconciliation. It does not change the status of `DOC-01`–`DOC-08`, `OPS-03`, or `OPS-10`; those records remain `NEEDS_DECISION` until an authorized approver explicitly resolves them.

- Sensitive documents use private storage. Uploads enforce server-side MIME, extension, size, and upload-error validation and use safe generated filenames.
- Unauthorized and missing document requests return the same generic `Not found.` response.
- Verification states are `PENDING`, `VERIFIED`, and `REJECTED`, and only authorized staff with branch access may verify.
- Expired documents are excluded from normal use.
- Medical documents are visible only to explicitly authorized roles.
- Parent/Guardian and Player visibility is limited by approved relationship and branch rules.
- Replacement creates a new version and retires the previous version. Retired documents remain auditable and cannot be downloaded.
- Malware scanning is required before verification or download; failed scans are quarantined.
- Audit history is retained and file contents or credentials are never logged.
- Deployment-owner confirmation is required for scanner, private-storage, retention, and encryption settings.

This is user-provided direction — not client approval — and it authorizes no code, migration, permission, or data change.

### Documents foundation implementation direction (2026-09-27; user-provided direction — not client approval)

The source implementation records the first secure foundation slice without changing the approval status of `DOC-01`–`DOC-08`, `OPS-03`, or `OPS-10`: `PENDING`, `VERIFIED`, and `REJECTED` verification states; branch-scoped reasoned verification using the existing staff permission boundary; validated expiry dates with expired normal-use exclusion; immutable new-version replacement with prior-version retirement; medical-document access requiring an explicit medical permission; and fail-closed `QUARANTINED` scanner status when no scanner integration is configured. Deployment scanner, private-storage, encryption, and retention evidence remains the deployment owner's responsibility. This is user direction only and is not client approval.

### T-FIXTURE-STANDINGS-DIRECTION — Tournament fixtures and standings

**Status:** USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL (2026-10-03).

The user directed implementation of manual Tournament fixtures with Scheduled, Completed, Cancelled, and Postponed statuses; same-Tournament teams; branch-matched active Grounds; separate local date/time inputs; reasoned cancellation/postponement; audited corrections; and a completed-fixture-only points table using Win 2, Loss 0, Tie 1 each, and No result 1 each. Cancelled, Postponed, and Scheduled fixtures do not affect standings. Net run rate, scorecards, innings, registration, team selection, notifications, and exports remain deferred by unresolved T-01–T-20 decisions. This direction authorizes implementation work for this slice but is not client approval.

## Match Player Performance direction (2026-10-03)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** The implementation slice adds a dedicated Match performance page, append-only audit events, source-field validation, read-time batting and bowling calculations, and safe empty/zero-denominator states. Net run rate, event-level scorecards, tournament standings integration, exports, notifications, and wider performance governance remain blocked until separately approved.

## Match Player Fielding Details direction (2026-10-03)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** Fielding details are a bounded extension of Match Player Performance. Broader scorecard event capture, innings policy, net run rate, tournament integration, exports, notifications, and performance governance remain deferred.

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
### User-provided direction: attendance-first Player feedback (2026-10-05)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** A Coach or authorized staff member may add or correct Player feedback after the Player's attendance has been recorded for the immutable Session roster. Session completion is not required; scheduled, active, and completed sessions are eligible, while cancelled sessions remain excluded unless a later approved policy changes that rule. Existing branch-scoped authorization, snapshot membership, attendance checks, correction reasons, append-only audit history, and transaction behavior remain mandatory.

## Match opponent catalog direction (2026-10-05)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** Matches use required, branch-scoped active Opponent records selected from a controlled dropdown. Opponent names are unique within a branch; inactive or unauthorized records cannot be selected. Existing free-text values are preserved and backfilled to the catalog by a forward-only migration. Opponent catalog access uses `opponent.view`, `opponent.create`, and `opponent.update`.
## Achievements decision reconciliation (2026-10-06)

The Achievements questionnaire was reviewed, but no decision fields are completed. No option, resolution, approver, approval date, or effective date is treated as approval. Categories, fields, eligibility, branch visibility, source attribution, creation mode, authority, lifecycle, duplicates, correction/revocation, certificates, portal visibility, staff permissions, audit history, dates, retention, reports/exports, and notifications remain `NEEDS_DECISION`.

See `docs/ACHIEVEMENTS-IMPLEMENTATION-MATRIX.md`. This entry authorizes no implementation and does not modify `docs/SRS-original.md`.

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL (2026-10-06):** The secure defaults in the questionnaire and implementation plan are planning direction only. Every Achievement area remains `NEEDS_DECISION`; no route, permission, migration, table, or workflow may be implemented from these defaults alone.

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL (2026-10-06):** A bounded catalog foundation may be implemented with branch-scoped title/category/description/active state, create/list screens, `achievement.view` and `achievement.create`, CSRF, validation, transactions, and append-only audit events. This does not approve award eligibility, assignment, approval, revocation, certificates, portal visibility, reports, exports, notifications, or retention.

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL (2026-10-06):** The next selected scope is draft-only manual award creation with explicit Player or Coach recipient assignment. Only active same-branch recipients, `draft` status, bounded nomination notes, reasoned draft edits, centralized permissions, and audited transactions are in scope. Approval/publication and all other Achievement workflows remain `NEEDS_DECISION`.

Implementation evidence: forward-only draft award migration and explicit create/list/detail/edit routes are present under this user-provided direction. Client approval is not claimed.
## Phase 2 decision reconciliation

Reviewed 2026-10-06. No selected options, resolutions, comments, approver names, approval dates, or effective dates were recorded for APL-01 through APL-05. Blank fields are not approval. All five items remain NEEDS_DECISION; none is approved or rejected.

| ID | Workflow | Status |
| --- | --- | --- |
| APL-01 | Draft approval | NEEDS_DECISION |
| APL-02 | Publication | NEEDS_DECISION |
| APL-03 | Published lifecycle | NEEDS_DECISION |
| APL-04 | Revocation | NEEDS_DECISION |
| APL-05 | Correction after publication | NEEDS_DECISION |

Recommended defaults remain USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL. No Phase 2 code, route, permission, migration, table, menu item, or test is authorized.
## Phase 2 secure-default direction — USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL

On 2026-10-06, user-provided defaults were recorded for APL-01 through APL-05: explicit approval before publication using achievement.approve; branch-scoped publication of approved drafts using achievement.publish; read-only published records with reasoned achievement.update corrections; non-destructive reasoned revocation using achievement.revoke; and explicit post-publication correction preserving actor, branch, timestamp, old values, new values, and reason, with revoked-record correction restricted.

These are not client approvals. Approver, approval date, and effective date were not provided. All five items remain NEEDS_DECISION and no implementation is authorized.
## Phase 2 lifecycle implementation evidence — USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL

The APL-01 through APL-05 defaults are implemented as a bounded lifecycle slice: draft approval with achievement.approve, publication with achievement.publish, read-only published records, reasoned revocation with achievement.revoke, and reasoned published corrections using achievement.update. Status changes are branch-scoped, transactional, version-checked, and audited with actor, branch, timestamp, reason, and before/after values. Revoked records are retained and no deletion workflow is introduced.

Certificates, portal visibility, reports, exports, notifications, retention, and automatic eligibility remain unavailable and unresolved.
## Phase 3 decision reconciliation

Reviewed 2026-10-06 against docs/ACHIEVEMENTS-PHASE-3-DECISION-REQUEST.md.

No explicit decision, resolution, comments, approver name, approval date, or effective date is recorded for any Phase 3 item. Blank fields are not approval. Secure defaults remain USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.

| ID | Workflow | Status | Implementation state |
| --- | --- | --- | --- |
| A3-01 | Certificates | NEEDS_DECISION | Not implemented; certificate templates, numbering, storage, approval, and retention remain unresolved. |
| A3-02 | Parent/Student portal visibility | NEEDS_DECISION | Not implemented; portal status, relationship, consent, and branch visibility remain unresolved. |
| A3-03 | Reports and exports | NEEDS_DECISION | Not implemented; fields, formulas, filters, formats, privacy, and permissions remain unresolved. |
| A3-04 | Notifications | NEEDS_DECISION | Not implemented; events, recipients, consent, channels, retries, and delivery remain unresolved. |
| A3-05 | Retention and archival | NEEDS_DECISION | Not implemented; retention periods, archive, legal hold, restore, and deletion authority remain unresolved. |
| A3-06 | Automatic eligibility | NEEDS_DECISION | Not implemented; source rules, formulas, thresholds, idempotency, and human approval remain unresolved. |

No Phase 3 code, route, permission, migration, table, menu item, or test may be added until all required approval fields are complete.
## Phase 3 secure-default direction — USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL

Secure defaults were recorded for A3-01 Certificates, A3-02 Parent/Student portal visibility, A3-03 Reports and exports, A3-04 Notifications, A3-05 Retention and archival, and A3-06 Automatic eligibility. Each remains NEEDS_DECISION because no client approver, approval date, or effective date is provided. No code, route, permission, migration, table, menu, or test is authorized.
## A3-01 certificate implementation evidence — USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL

A bounded certificate slice is implemented for published Achievement awards only. It uses private LocalPrivateStorage, safe deterministic generated filenames, branch-scoped generate/download permissions, idempotent award-to-certificate linkage, and append-only generation audit events. No certificate portal visibility, replacement, retirement, or public URL workflow is introduced.

A3-02 portal visibility, A3-03 reports/exports, A3-04 notifications, A3-05 retention/archival, and A3-06 automatic eligibility remain NEEDS_DECISION and are not implemented.

### A3-03 implementation record (2026-10-06)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** Implemented only published-award read-only reports and CSV export with branch, Player, Coach, category, and date filters. Existing `report.view` authorization and export audit behavior are retained. Broader reporting/export policy remains outside this bounded slice.
### Achievement schema reconciliation decision (2026-10-07)

**USER-PROVIDED DIRECTION - NOT CLIENT APPROVAL.** Treat `20261007000200_AddAchievementArchival` as canonical. Reconcile only missing archival indexes and foreign keys on `achievement_awards`; preserve `notification_recipients`, all existing records, and every migration-history row, including the duplicate notification migration entry. No destructive repair, alternate table, column rewrite, or automatic eligibility implementation is authorized.

### Achievement A3-06 implementation evidence (2026-10-07)

**USER-PROVIDED DIRECTION - NOT CLIENT APPROVAL.** Implemented draft-only automatic eligibility with versioned `attendance.v1`, `performance.v1`, and `coach.v1` rules. Evaluation and retry records are append-oriented and audited; branch and active Player/Coach checks are server-side; failed evaluations retain failure evidence without creating awards; duplicate active drafts are not created. No automatic approval or publication, and no changes to certificates, portal visibility, reports, notifications, or archival behavior.


## Director Dashboard secure-default direction (2026-10-11)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** DASH-01 through DASH-20 use branch-scoped access with an explicit authorized “All Branches” option for Super Admin only; MCC-local date/month inputs with UTC conversion for stored timestamps; active, authorized source records; no sensitive medical, document, guardian, or private contact data; no invented KPI or performance formulas outside approved existing metrics; clear safe empty states; no caching until freshness and invalidation rules are defined; existing centralized permissions and branch authorization; exports inheriting dashboard filters and authorization; and audit limited to sensitive administrative actions without private data. Exact numeric formulas remain unresolved and every DASH item remains NEEDS_DECISION. No implementation, permission grant, migration, cache, export, or UI change is authorized by this direction.

## Individual Session Skill taxonomy direction (2026-10-11)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** Secure defaults for SKILL-01 through SKILL-13 are recorded as unresolved planning direction. Approval fields remain blank and all items remain `NEEDS_DECISION`; implementation is not authorized.
## Match-Tournament ownership and result direction (2026-10-11)

**Status:** `USER_PROVIDED_DIRECTION` — not client approval  
**Decision status for every item:** `NEEDS_DECISION`  
**Approver:**   
**Approval date:**   
**Effective date:**  

The user-provided planning flow is: Tournament schedules Fixtures, Fixtures may
lead to Matches, and verified completed Match results feed Tournament standings.

| Item | User-provided direction | Status |
| --- | --- | --- |
| Match ownership | Standalone Matches are allowed; one Fixture may link to one Match; a Match may link to only one Tournament Fixture. | `NEEDS_DECISION` |
| Fixture ownership | Fixture owns Tournament scheduling, round, date/time, Ground, and status; linked Match owns cricket result and completion details. | `NEEDS_DECISION` |
| Result source | Verified completed Match result is authoritative for winner, tie, no-result, and Match completion; Fixture status does not replace it. | `NEEDS_DECISION` |
| Tournament points | Points come only from a completed, verified Match linked to the Fixture; scheduled, postponed, cancelled, and incomplete Fixtures contribute no points. | `NEEDS_DECISION` |
| Postponed/cancelled Fixtures | Postponed Fixtures may be rescheduled with audit history; cancelled Fixtures are retained and contribute no result or points. | `NEEDS_DECISION` |
| Corrections/reopening | Corrections require authorized permission, reason, optimistic version check, before/after audit metadata, and transaction; ordinary editing cannot change completed results or awarded points. | `NEEDS_DECISION` |
| Branch ownership | Tournament, Fixture, Match, Ground, Opponent, and Player references remain within the authorized branch; no implicit cross-branch links. | `NEEDS_DECISION` |

This direction does not approve code, routes, permissions, migrations, schema,
database changes, tests, reports, exports, notifications, or portal behavior.
## Admission PDF field capture

USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL. The complete PDF field set is
captured in the existing Admission workflow. Downstream payment, hostel,
signature, medical, document approval, notification, and portal decisions
remain NEEDS_DECISION.
