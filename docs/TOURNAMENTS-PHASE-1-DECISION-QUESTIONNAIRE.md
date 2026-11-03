# Tournament Phase 1 Decision Questionnaire

**Scope:** T-01 through T-04  
**Source evidence:** `docs/TOURNAMENTS-IMPLEMENTATION-MATRIX.md`, `docs/TOURNAMENTS-IMPLEMENTATION-PLAN.md`, `docs/DECISIONS.md`, `docs/REQUIREMENTS.md`, `docs/RBAC.md`, `docs/ARCHITECTURE.md`, and `docs/DATABASE.md`  
**Current status:** All four items are `NEEDS_DECISION`.  
**Approval rule:** The recommendations below are secure defaults only. They are not client approval and must not authorize implementation until an approver records a decision.

## How to complete this form

Select one option for each item, or write a constrained alternative. A decision is effective only after the approver identity, approval date, and effective date are completed. Any option that changes permissions, branch visibility, lifecycle, data retention, or schema requires the migration and permission impacts to be accepted explicitly.

### Common approval fields

- **Approver name:** ______________________________________________
- **Approval date:** _______________________________________________
- **Effective date:** ______________________________________________

---

## T-01 — Competition type, hierarchy, and Match ownership

### Current status

`NEEDS_DECISION`

### Existing implementation behavior

- `TournamentManagementService` has a fixed Tournament type allowlist.
- Tournament CRUD stores a branch-owned record.
- No parent competition or hierarchy relation is implemented.
- Existing Match records have their own Match ownership and branch rules.
- Existing CRUD code and routes are evidence only; they do not approve a competition model.

### User-provided direction

No specific T-01 direction has been approved. Existing fixture/standings work is recorded separately as `USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL` and does not resolve competition hierarchy or Match ownership.

### Business question

Which competition types should MCC support, and how should a Tournament relate to Matches or a parent competition?

### Select one decision

- [ ] **T-01-A — Standalone branch-owned Tournament.** Keep the current type allowlist. Each Tournament is independent, belongs to one owning branch, and may link only to explicitly related branch-matched Matches.
- [ ] **T-01-B — Parent competition hierarchy.** Keep the current type allowlist and add parent/child competition records. Define which records may own Matches and how hierarchy branch scope is inherited.
- [ ] **T-01-C — Restricted type catalogue.** Replace the current allowlist with this approved set: ____________________________________. Tournaments remain standalone and branch-owned.
- [ ] **T-01-D — Other constrained model.** Describe the type catalogue, parent relationship, Match owner, and branch rule: ________________________________________________________________

### Recommended secure default

**T-01-A:** Retain the existing allowlist, use standalone branch-owned Tournaments, and permit only explicitly authorized Match links. Do not add hierarchy or broaden Match ownership until separately approved.

### Required comments and approval

- **Decision:** ____________________________________________________
- **Comments / rationale:** _________________________________________
- **Approver name:** _______________________________________________
- **Approval date:** ________________________________________________
- **Effective date:** _______________________________________________

### Impact and acceptance criteria

- **Migration impact:** None for T-01-A. T-01-B may require parent/competition tables, foreign keys, indexes, and backfill/ownership rules. T-01-C may require a controlled type registry or data migration.
- **Permission impact:** Existing `tournament.view`, `tournament.create`, `tournament.update`, and existing Match permissions remain the boundary for T-01-A. Hierarchy, ownership override, or parent-management permissions require explicit approval.
- **Branch/security requirements:** Every Tournament, Match link, parent reference, and related read/write must be revalidated against the authorized branch context. Forged and cross-branch IDs must fail safely.
- **Tests after approval:** Type allowlist tests; hierarchy/parent lineage if selected; Match ownership/link validation; branch isolation; permission and CSRF tests; transaction and audit tests; safe not-found behavior.

---

## T-02 — Tournament statuses, transitions, terminal states, reopening, reasons, and locks

### Current status

`NEEDS_DECISION`

### Existing implementation behavior

- CRUD currently accepts the existing Tournament status values, including `planned`, `active`, `completed`, and `cancelled`.
- There is no approved transition matrix, dedicated transition service, reopening policy, lock policy, or universal reason requirement.
- Existing CRUD audit records are evidence of logging, not approval of lifecycle rules.

### User-provided direction

No specific T-02 lifecycle direction has been approved. Existing fixture status behavior is a bounded user-directed slice and does not define Tournament lifecycle transitions.

### Business question

Which Tournament statuses and transitions are permitted, which states are terminal, can a completed or cancelled Tournament reopen, when is a reason required, and what records become locked?

### Select one decision

- [ ] **T-02-A — Explicit terminal lifecycle.** `planned → active → completed`; cancellation is allowed from approved non-terminal states; `completed` and `cancelled` are terminal; no reopening.
- [ ] **T-02-B — Controlled reopening.** Use T-02-A, but allow reopening of completed/cancelled records only through an authorized, reasoned, audited correction action.
- [ ] **T-02-C — Direct CRUD status selection.** Retain direct status editing with no additional transition workflow, lock, or reopening restriction.
- [ ] **T-02-D — Other transition matrix.** Define every status, allowed source/target pair, terminal state, reason rule, lock rule, and reopening authority: ____________________________________________________________

### Recommended secure default

**T-02-A:** Introduce an explicit transition matrix, treat completed and cancelled as terminal, require reasons for exceptional changes, and retain append-only audit history. Do not reopen records without a separately authorized correction workflow.

### Required comments and approval

- **Decision:** ____________________________________________________
- **Comments / rationale:** _________________________________________
- **Approver name:** _______________________________________________
- **Approval date:** ________________________________________________
- **Effective date:** _______________________________________________

### Impact and acceptance criteria

- **Migration impact:** A transition/history or lock field/table may be required. Existing records must remain intact; any backfill and terminal-state treatment must be specified.
- **Permission impact:** Existing `tournament.update` may cover ordinary approved transitions only. Completion, cancellation, reopening, override, or correction permissions must be explicitly assigned if required.
- **Branch/security requirements:** Transition routes must be explicit POST actions with CSRF, current branch authorization, idempotency, safe errors, and no homepage redirect after failure.
- **Tests after approval:** Valid/invalid transition matrix; terminal and reopening cases; required reasons; unauthorized/cross-branch denial; CSRF; repeated transition idempotency; transaction rollback; actor/branch/timestamp/previous/new/reason audit fields; locked-record behavior.

---

## T-03 — Branch ownership, multi-branch participation, visibility, and transfer

### Current status

`NEEDS_DECISION`

### Existing implementation behavior

- Tournaments have a `branch_id`.
- Current list and service reads/writes use centralized authorization and branch-scoped access.
- Related participant and fixture validation is currently branch constrained in the existing bounded scope.
- No approved multi-branch participation join, transfer workflow, or historical cross-branch visibility policy exists.

### User-provided direction

No specific T-03 direction has been approved. Existing branch filtering is implementation evidence and must not be expanded into multi-branch access by inference.

### Business question

Is a Tournament owned by one branch only, may multiple branches explicitly participate, who may see historical records, and how are transfers handled?

### Select one decision

- [ ] **T-03-A — Single owning branch.** Every Tournament has one owning branch; all related Players, teams, Grounds, Matches, fixtures, and writes must match it. No implicit cross-branch visibility or transfer.
- [ ] **T-03-B — Owner plus assigned participating branches.** One owning branch remains authoritative, with an explicit approved branch-participation list and defined read/write boundaries.
- [ ] **T-03-C — Global authorized Tournament.** A Tournament is visible to all authorized branches, with explicit restrictions on related records and changes.
- [ ] **T-03-D — Other branch model.** Define owner, participants, read/write visibility, transfer authority, historical access, and related-record behavior: __________________________________________________________

### Recommended secure default

**T-03-A:** Keep one owning branch, reject cross-branch related IDs, and provide no transfer or implicit shared visibility until a participating-branch policy is approved.

### Required comments and approval

- **Decision:** ____________________________________________________
- **Comments / rationale:** _________________________________________
- **Approver name:** _______________________________________________
- **Approval date:** ________________________________________________
- **Effective date:** _______________________________________________

### Impact and acceptance criteria

- **Migration impact:** None for T-03-A. T-03-B may require a participating-branches join table, effective dates, and transfer/history metadata. T-03-C may require visibility indexes and explicit shared ownership semantics.
- **Permission impact:** Existing branch-scoped Tournament permissions remain sufficient for T-03-A. Cross-branch viewing, participation, transfer, or override permissions require explicit approval.
- **Branch/security requirements:** Branch access must be checked in every list, detail, selector, fixture, participant, Match, Ground, Player, export, and history query. Hidden options are not authorization.
- **Tests after approval:** Same-branch success; forged/cross-branch denial; branch-filter and pagination isolation; inactive/deleted related-record rejection; transfer/participant visibility tests if selected; CSRF, audit, rollback, and safe not-found tests.

---

## T-04 — Date/time fields, timezone storage, fixture boundaries, and scheduling rules

### Current status

`NEEDS_DECISION`

### Existing implementation behavior

- Tournament start/end values are currently date fields using the shared datepicker.
- Fixture work uses separate local date and time inputs and service-layer conversion to the existing UTC `scheduled_at` convention, as recorded in the user-directed fixture slice.
- The application timezone is configured as `Asia/Kolkata`; UI timezone suffixes are not intended to be shown.
- Fixture boundary and Tournament start/end inclusion rules are not approved as a complete policy.

### User-provided direction

The fixture/standings direction specifies separate MCC-local date/time fields, service-layer conversion, and existing UTC storage. It does not approve Tournament boundary, recurrence, or wider scheduling policy.

### Business question

Which date/time fields are required, how are local values stored and displayed, must fixtures fall inside Tournament dates, and which scheduling conflicts or calendar rules apply?

### Select one decision

- [ ] **T-04-A — MCC local input with UTC storage.** Use separate date and time fields, interpret them in the configured application timezone, convert in the service layer, store UTC where the schema stores timestamps, and display local values without a timezone suffix.
- [ ] **T-04-B — Local timestamp storage.** Store local date/time values with a documented timezone policy and no UTC conversion. Define database and reporting semantics: ____________________________________
- [ ] **T-04-C — Date-only Tournament boundary.** Keep Tournament start/end as dates; fixture times follow the approved Match/Scheduling convention and must be validated against the selected boundary dates.
- [ ] **T-04-D — Other date/time policy.** Define fields, timezone, storage, boundary inclusion, same-day rule, conflict rule, DST behavior, and reporting semantics: ______________________________________________________

### Recommended secure default

**T-04-A plus T-04-C:** Use the configured MCC timezone for user input and display, separate date/time controls, service-layer UTC persistence, and date-only Tournament boundaries that include fixtures only within the approved start/end dates. Reject invalid or reversed values and preserve submitted values after validation failure.

### Required comments and approval

- **Decision:** ____________________________________________________
- **Comments / rationale:** _________________________________________
- **Approver name:** _______________________________________________
- **Approval date:** ________________________________________________
- **Effective date:** _______________________________________________

### Impact and acceptance criteria

- **Migration impact:** None if existing DATE fields and UTC `scheduled_at` remain sufficient. New time columns, timezone metadata, boundary flags, or indexes require a forward-only migration and deployment plan.
- **Permission impact:** No new permission is required for the recommended date/time convention. Scheduling or conflict overrides require explicit approval and existing branch-scoped permissions.
- **Branch/security requirements:** Date/time normalization occurs only in services after server-side branch, Match, Ground, and permission validation. Client-side datepickers cannot authorize or alter scope.
- **Tests after approval:** Separate date/time rendering; current and selected datepicker behavior; invalid date/time; reversed start/end; timezone round-trip; edit preservation; Tournament boundary inclusion/exclusion; same-calendar-day and conflict rules; branch/RBAC/CSRF; transaction rollback and audit timestamps.

---

## Approval summary

| Item | Selected option | Decision | Approver | Approval date | Effective date |
|---|---|---|---|---|---|
| T-01 |  |  |  |  |  |
| T-02 |  |  |  |  |  |
| T-03 |  |  |  |  |  |
| T-04 |  |  |  |  |  |

## Implementation gate after approval

Approval of these questions does not by itself authorize code changes. Before implementation, reconcile the selected options into `docs/DECISIONS.md`, update the applicable implementation matrix/plan, identify exact routes/services/models/views/migrations/permissions, and add Unit, Feature, insert-only database, CSRF, branch-isolation, audit, rollback, idempotency, and UI contract acceptance tests. Until then, T-01 through T-04 remain `NEEDS_DECISION`.

## Recorded response (2026-10-04)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** The user selected the recommended defaults for T-01 through T-04. This response does not represent client approval and does not resolve T-05 through T-20.

- **T-01:** Existing Tournament type allowlist; standalone branch-owned Tournaments; explicitly authorized Match links only; no parent hierarchy.
- **T-02:** `planned → active → completed`, `planned → cancelled`, and `active → cancelled`; completed and cancelled are terminal; no reopening; exceptional changes require a reason; all transitions are append-only audited.
- **T-03:** One owning branch; no implicit cross-branch access; Grounds, Teams, Players, Coaches, Fixtures, Matches, and reports must match the owning branch; no transfer workflow yet.
- **T-04:** Separate MCC-local date/time inputs; service-layer conversion; UTC storage where timestamps are required; no visible timezone suffix; fixtures must fall within Tournament boundaries; existing Match/Scheduling conventions apply.
