# Tournament Decision Questionnaire

**Source:** `docs/TOURNAMENTS-IMPLEMENTATION-MATRIX.md`  
**Scope:** T-01 through T-20 only  
**Current status:** Every item is `NEEDS_DECISION`.  
**Instruction:** Recommendations below are secure implementation defaults, not approvals. Existing Tournament CRUD and UI are complete and are not changed by this questionnaire.

## Response fields

- **Approver identity:** __________________________________________
- **Decision date:** ______________________________________________
- **Effective date:** _____________________________________________

For each item, record one option or write a constrained alternative in **Decision**. Do not treat the recommendation as approval.

## Phase 1 — Scope, lifecycle, branch, and dates

### T-01 — Tournament scope and competition types

- **Current status:** `NEEDS_DECISION`
- **Business decision required:** Which competition types are supported, and is a Tournament standalone, under a parent competition, or both?
- **Options:**
  1. Keep the existing school, club, district, MCA, state, national, international, and England tour types; standalone only.
  2. Keep the existing types and add a parent competition hierarchy.
  3. Restrict the allowlist to selected types; standalone only.
  4. Other explicitly defined model: ______________________________
- **Recommended secure default:** Existing allowlist, standalone, branch-owned records; no hierarchy until approved.
- **Decision:** ____________________________________________________
- **Migration impact:** None for option 1; hierarchy/parent tables and foreign keys otherwise.
- **Permission impact:** Existing `tournament.view/create/update`; new hierarchy permissions require approval.
- **Test acceptance criteria:** Invalid types rejected; approved scope and hierarchy ownership enforced; branch isolation and audit coverage pass.

### T-02 — Lifecycle and status transitions

- **Current status:** `NEEDS_DECISION`
- **Business decision required:** Which statuses and transitions are allowed, including completion, cancellation, reopening, reasons, and locking?
- **Options:**
  1. `planned → active → completed`; cancellation from non-terminal states; no reopening.
  2. Allow reopening completed/cancelled records with an approved reason.
  3. Keep direct CRUD status selection with no transition workflow.
  4. Other transition matrix: ______________________________________
- **Recommended secure default:** Explicit transition matrix, terminal completed/cancelled states, required reason for exceptional changes, append-only audit.
- **Decision:** ____________________________________________________
- **Migration impact:** Transition/history/lock fields or tables may be required.
- **Permission impact:** Existing update permission plus any approved completion/cancel/override permissions.
- **Test acceptance criteria:** Valid transitions succeed; invalid/repeated transitions fail safely; reason, actor, branch, and before/after audit data are recorded.

### T-03 — Branch ownership and multi-branch access

- **Current status:** `NEEDS_DECISION`
- **Business decision required:** Are Tournaments single-branch, shared across branches, or both? Define participating and historical visibility.
- **Options:**
  1. Single owning branch; all related records must match it.
  2. Owning branch plus explicitly assigned participating branches.
  3. Global Tournament visible to authorized branches.
  4. Other scope model: ___________________________________________
- **Recommended secure default:** Single owning branch; no implicit cross-branch access or transfer.
- **Decision:** ____________________________________________________
- **Migration impact:** Participating-branch join table or transfer history may be required.
- **Permission impact:** Existing branch-scoped permissions; any cross-branch permission must be explicit.
- **Test acceptance criteria:** Forged IDs, cross-branch reads/writes, filters, exports, and related records are denied.

### T-04 — Dates and timezone

- **Current status:** `NEEDS_DECISION`
- **Business decision required:** Define local date/time input, storage conversion, fixture boundaries, and configured timezone behavior.
- **Options:**
  1. MCC local date/time inputs; convert in services; store UTC timestamps where applicable.
  2. Store local timestamps with an explicit timezone policy.
  3. Tournament dates only; fixture time handled by the approved Match/Scheduling convention.
  4. Other date/time policy: ______________________________________
- **Recommended secure default:** Application timezone for user input/display, separate date and time fields, service-layer UTC conversion, no timezone suffix in UI.
- **Decision:** ____________________________________________________
- **Migration impact:** Time columns or timezone metadata only if approved schema requires them.
- **Permission impact:** None beyond existing CRUD scope.
- **Test acceptance criteria:** Same-day, boundary, invalid, reversed, DST/timezone, edit round-trip, and validation-preservation tests pass.

## Phase 2 — Registration, eligibility, assignments, and teams

### T-05 — Registration and entry

- **Current status:** `NEEDS_DECISION`
- **Business decision required:** Define registration entity, entry window, approval, duplicate, withdrawal, limits, consent, and fees if applicable.
- **Options:**
  1. Staff-managed entries only; no self-registration or fees.
  2. Staff approval workflow with open/close dates.
  3. Player/Parent registration with staff approval and withdrawal.
  4. Other entry policy: __________________________________________
- **Recommended secure default:** Staff-managed, branch-scoped entries with duplicate prevention and append-only audit.
- **Decision:** ____________________________________________________
- **Migration impact:** Registration, approval, consent, and history tables may be required.
- **Permission impact:** New register/approve/withdraw permissions require approval.
- **Test acceptance criteria:** Window, duplicate, eligibility, branch, consent, approval, withdrawal, CSRF, and audit tests pass.

### T-06 — Player eligibility and categories

- **Current status:** `NEEDS_DECISION`
- **Business decision required:** Define active status, age/category source, effective-date boundary, exceptions, and selection snapshot rules.
- **Options:**
  1. Active Players only; category and age checked on registration date.
  2. Eligibility checked on Tournament start date.
  3. Staff-approved exceptions with a required reason.
  4. Other eligibility policy: ____________________________________
- **Recommended secure default:** Active, same-branch Players; server-side category/age validation; explicit exception audit.
- **Decision:** ____________________________________________________
- **Migration impact:** Eligibility/category snapshots or exception fields may be required.
- **Permission impact:** Existing update permission is insufficient for new exception authority.
- **Test acceptance criteria:** Inactive, deleted, cross-branch, wrong-category, boundary-age, forged, duplicate, and exception cases are covered.

### T-07 — Coach and official assignments

- **Current status:** `NEEDS_DECISION`
- **Business decision required:** Define assignment roles, authority, branch scope, conflicts, reassignment, and history.
- **Options:**
  1. No Tournament assignments; use Match-level assignments only.
  2. Branch-authorized Coach assignment by Tournament staff.
  3. Separate Coach and official roles with approval and reassignment history.
  4. Other assignment policy: _____________________________________
- **Recommended secure default:** No new Tournament assignment workflow until approved; existing Match/Coach rules remain authoritative.
- **Decision:** ____________________________________________________
- **Migration impact:** Assignment and history tables may be required.
- **Permission impact:** New assign/reassign/approve permissions require approval.
- **Test acceptance criteria:** Active branch assignment, conflict, forged ID, authorization, reassignment, audit, and CSRF tests pass.

### T-08 — Team and squad structure

- **Current status:** `NEEDS_DECISION`
- **Business decision required:** Define team entities, membership, squad/XI/reserves, limits, eligibility, locking, and corrections.
- **Options:**
  1. Free-text team names only; no structured membership.
  2. Structured branch-owned teams and memberships.
  3. Teams plus immutable squad/Playing XI snapshots.
  4. Other team model: ____________________________________________
- **Recommended secure default:** Structured branch-owned teams with explicit membership and immutable snapshots before competitive use.
- **Decision:** ____________________________________________________
- **Migration impact:** Team, membership, selection, and snapshot tables may be required.
- **Permission impact:** New team/member/selection permissions require approval.
- **Test acceptance criteria:** Duplicate membership, branch, eligibility, limits, lock, correction, and audit cases pass.

## Phase 3 — Fixtures, rounds, scorecards, standings, and outcomes

### T-09 — Match scheduling and fixture generation

- **Current status:** `NEEDS_DECISION`
- **Business decision required:** Define manual versus generated fixtures, scheduling conflicts, rescheduling, ownership, and Match linkage.
- **Options:**
  1. Manual fixtures only; optional same-branch Match link.
  2. Staff-generated fixtures from approved teams/rounds.
  3. Full generation with conflict checking and rescheduling workflow.
  4. Other scheduling policy: _____________________________________
- **Recommended secure default:** Manual, same-branch, transactionally linked fixtures until generation rules are approved.
- **Decision:** ____________________________________________________
- **Migration impact:** Round/generation/reschedule fields or tables may be required.
- **Permission impact:** Existing update permission only; generation/reschedule permissions require approval.
- **Test acceptance criteria:** Branch, Ground, Match, date/time, duplicate-link, conflict, transaction, idempotency, and audit tests pass.

### T-10 — Rounds and progression

- **Current status:** `NEEDS_DECISION`
- **Business decision required:** Define groups/brackets, ordering, byes, qualification, and recalculation.
- **Options:**
  1. No rounds; manually ordered fixtures.
  2. Group stage only.
  3. Group plus knockout brackets with explicit progression.
  4. Other progression model: _____________________________________
- **Recommended secure default:** No progression workflow until a deterministic bracket and correction policy is approved.
- **Decision:** ____________________________________________________
- **Migration impact:** Round, group, bracket, seed, and progression tables may be required.
- **Permission impact:** New generation/override permissions require approval.
- **Test acceptance criteria:** Deterministic ordering, byes, qualification, correction, branch scope, and recalculation tests pass.

### T-11 — Scorecards and results

- **Current status:** `NEEDS_DECISION`
- **Business decision required:** Define scorecard fields, innings, result source, verification, draft/lock, and Match integration.
- **Options:**
  1. Match result summary only; no Tournament scorecard.
  2. Verified innings summary per Match.
  3. Full scorecard/innings workflow with locking.
  4. Other scorecard policy: ______________________________________
- **Recommended secure default:** No new scorecard writes; consume only approved Match result fields until a scorecard model is approved.
- **Decision:** ____________________________________________________
- **Migration impact:** Scorecard/innings/verification tables may be required.
- **Permission impact:** New scorecard, verify, correct, and lock permissions require approval.
- **Test acceptance criteria:** Field validation, result calculation, branch lineage, correction, locking, transaction, and audit tests pass.

### T-12 — Points table and standings formulas

- **Current status:** `NEEDS_DECISION`
- **Business decision required:** Define points, tie-breakers, rounding, missing data, formula versioning, and recalculation.
- **Options:**
  1. No standings; display fixture results only.
  2. Win/draw/loss points with explicit tie-breakers.
  3. Configurable competition-specific formulas with versioning.
  4. Other formula policy: ________________________________________
- **Recommended secure default:** Do not publish or extend standings formulas until approved examples and tie-breakers exist.
- **Decision:** ____________________________________________________
- **Migration impact:** Formula version/snapshot tables may be required.
- **Permission impact:** Recalculate/correct permissions require approval.
- **Test acceptance criteria:** Approved examples, rounding, missing data, ties, corrections, recalculation, and branch visibility pass.

### T-13 — Exceptional outcomes

- **Current status:** `NEEDS_DECISION`
- **Business decision required:** Define tie, abandoned, rain-cancelled, and no-result meanings and downstream effects.
- **Options:**
  1. Use existing Match result vocabulary only.
  2. Add explicit Tournament outcome statuses with no automatic progression.
  3. Add outcome statuses with standings/progression effects.
  4. Other outcome policy: ________________________________________
- **Recommended secure default:** Preserve existing Match behavior and do not invent Tournament outcome effects.
- **Decision:** ____________________________________________________
- **Migration impact:** Outcome/reason fields may be required.
- **Permission impact:** New correction/approval permissions require approval.
- **Test acceptance criteria:** Outcome validation, standings effect, correction reason, branch scope, idempotency, and audit tests pass.

## Phase 4 — Performance, attendance, corrections, and permissions

### T-14 — Player and Coach performance integration

- **Current status:** `NEEDS_DECISION`
- **Business decision required:** Define source records, formulas, approval, visibility, correction, branch scope, and recalculation.
- **Options:**
  1. No Tournament contribution to Performance or Coach KPIs.
  2. Consume approved Match/Performance records without new formulas.
  3. Add Tournament-specific performance records and formulas.
  4. Other integration policy: ____________________________________
- **Recommended secure default:** No Tournament performance contribution until source and formula ownership are approved.
- **Decision:** ____________________________________________________
- **Migration impact:** Source links or performance snapshots may be required.
- **Permission impact:** Existing Performance/KPI permissions do not authorize new Tournament visibility.
- **Test acceptance criteria:** Source lineage, formula examples, approval, branch visibility, corrections, and recalculation pass.

### T-15 — Attendance integration

- **Current status:** `NEEDS_DECISION`
- **Business decision required:** Define roster source, snapshot timing, statuses, denominator, correction, and authority.
- **Options:**
  1. No Tournament attendance; retain session attendance only.
  2. Reuse approved Match/session snapshot without a new denominator.
  3. Add Tournament roster snapshot and attendance workflow.
  4. Other attendance policy: _____________________________________
- **Recommended secure default:** No Tournament attendance workflow; existing Attendance snapshot rules remain unchanged.
- **Decision:** ____________________________________________________
- **Migration impact:** Tournament roster/attendance relation may be required.
- **Permission impact:** Existing attendance permissions are insufficient for new Tournament scope.
- **Test acceptance criteria:** Snapshot immutability, status validation, branch/permission checks, correction reason, transaction, and audit tests pass.

### T-16 — Corrections, approval, locking, and audit

- **Current status:** `NEEDS_DECISION`
- **Business decision required:** Define which changes require approval, lock, correction reason, append-only history, and recalculation.
- **Options:**
  1. Existing CRUD audit only; no new locks.
  2. Lock completed/approved records; corrections require reason and approval.
  3. Field-level version history and approval for all competitive records.
  4. Other correction policy: ______________________________________
- **Recommended secure default:** Append-only audit, no deletion of history, reason required for exceptional corrections, terminal records locked.
- **Decision:** ____________________________________________________
- **Migration impact:** Version, lock, correction, and approval fields/tables may be required.
- **Permission impact:** New approve/correct/unlock permissions require approval.
- **Test acceptance criteria:** Before/after, actor, timestamp, branch, reason, rollback, idempotency, and locked-state tests pass.

### T-17 — Permissions and role visibility

- **Current status:** `NEEDS_DECISION`
- **Business decision required:** Define which roles may view, create, update, assign, verify, complete, correct, export, and audit Tournament records.
- **Options:**
  1. Existing Super Admin/Branch Admin/Coach CRUD permissions only.
  2. Staff-only Tournament permissions with branch scope.
  3. Separate action permissions including assignment, approval, correction, and export.
  4. Other permission matrix: _____________________________________
- **Recommended secure default:** Keep existing branch-scoped `tournament.view/create/update`; add no permissions implicitly.
- **Decision:** ____________________________________________________
- **Migration impact:** Permission rows only when an approved matrix adds them.
- **Permission impact:** This item defines all new permission impact.
- **Test acceptance criteria:** Every route/action enforces server-side permission and branch scope; hidden UI controls are not relied upon.

## Phase 5 — Reports, retention, and notifications

### T-18 — Reports and exports

- **Current status:** `NEEDS_DECISION`
- **Business decision required:** Define reports, columns, filters, formats, freshness, visibility, audit, and export scope.
- **Options:**
  1. No Tournament reports or exports.
  2. Branch-scoped CSV only using list/detail fields.
  3. CSV/PDF/Excel reports with approved definitions.
  4. Other report policy: _________________________________________
- **Recommended secure default:** No exports until columns, branch scope, and permission are approved.
- **Decision:** ____________________________________________________
- **Migration impact:** Export job/audit tables only if asynchronous exports are approved.
- **Permission impact:** New export/report permission requires approval.
- **Test acceptance criteria:** Authorized columns, filters, branch scope, escaping, audit, and failure behavior pass.

### T-19 — Retention and historical recalculation

- **Current status:** `NEEDS_DECISION`
- **Business decision required:** Define retention, retirement/archive behavior, historical recalculation, and operational ownership.
- **Options:**
  1. Retain records indefinitely; no automatic recalculation.
  2. Retain audit and archive inactive Tournament records.
  3. Defined retention period with approved archival and recalculation jobs.
  4. Other retention policy: ______________________________________
- **Recommended secure default:** Retain auditable history indefinitely until an operational retention owner and policy are approved; no automatic recalculation.
- **Decision:** ____________________________________________________
- **Migration impact:** Retirement/archive/version metadata may be required.
- **Permission impact:** Archive/delete/recalculate permissions require approval.
- **Test acceptance criteria:** Historical visibility, authorization, retention job safety, audit preservation, and recalculation scope pass.

### T-20 — Notifications and external delivery

- **Current status:** `NEEDS_DECISION`
- **Business decision required:** Define triggers, recipients, consent, channels, templates, retries, failure states, privacy, retention, and deployment boundary.
- **Options:**
  1. No Tournament notifications.
  2. In-app notifications only with existing recipient scope.
  3. In-app plus approved email/WhatsApp/SMS delivery with outbox/retry policy.
  4. Other notification policy: ___________________________________
- **Recommended secure default:** No external delivery; if later approved, fail-closed outbox with explicit consent and no sensitive content.
- **Decision:** ____________________________________________________
- **Migration impact:** Notification/outbox/delivery audit tables may be required.
- **Permission impact:** Recipient visibility and notification administration permissions require approval.
- **Test acceptance criteria:** Trigger, recipient, consent, branch scope, failure, retry, idempotency, audit, and no-sensitive-data tests pass.

## Approval record

- **Approver identity:** __________________________________________
- **Decision date:** ______________________________________________
- **Effective date:** _____________________________________________
- **Comments / constraints:** _____________________________________

Until an item is explicitly marked `Approved` in the authorized decision record, it remains `NEEDS_DECISION` and must not be implemented. No CRUD code, tests, migrations, permissions, or database behavior are changed by this questionnaire.
