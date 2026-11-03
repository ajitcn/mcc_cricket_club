# Equipment Module Scope and Decision Brief

**Purpose:** Track Equipment scope and decisions that remain for an authorized MCC approver.

**Overall status:** `USER_PROVIDED_DIRECTION — LIMITED INVENTORY IMPLEMENTED`; wider scope remains `NEEDS_DECISION`

**Approver:** ______________________________  
**Decision date:** __________________________  
**Approved requirements baseline/version:** __________________________

This brief began as a decision request. A user-provided direction dated 2026-09-21 authorizes implementation of the limited inventory scope recorded under `EQUIP-01`; this is not documented client approval and does not approve the wider requirements baseline. The numbered items below are brief-local references, not project decision IDs.

### User-provided limited scope addendum (2026-09-21)

**Status:** Implemented as user-authorized scope; not client approval. No approver identity beyond the user direction or client decision date is recorded.

- Equipment records contain Name, Quantity, and Branch. Create, list, edit, and soft delete are implemented for this limited scope.
- The existing Super Admin role can select from its currently authorized branches. The existing Branch Admin role is the user-confirmed mapping for “Branch Manager”.
- A Branch Admin with one current authorized active branch uses it automatically. With multiple branches, the Branch Admin selects an active branch in shared navigation; the Equipment form displays it as fixed. A Branch Admin with no authorized active branch cannot create a record.
- Active branch selection is server-side, CSRF-protected, and revalidated against current database assignments at selection and each Equipment use. Submitted Equipment `branch_id` is ignored for Branch Admins. The shared `authorizedBranches` session value and other modules' data-access behavior are unchanged.
- Edit changes only Name and Quantity; Branch is fixed. Delete uses `deleted_at`, excludes the record from active lists, and writes an audit event transactionally. Hard deletion, a restore interface, categories, assignments, maintenance, availability, Grounds/session links, and other fields/workflows remain `NEEDS_DECISION` or out of this authorized slice.
- No client-approved replacement for missing `docs/SRS-original.md` is recorded.

## Source and approval status

| Source | What it records | Status and limit |
|---|---|---|
| `docs/SRS-original.md` | Identified by `AGENTS.md` as authoritative client input. | Not present in this repository; `docs/MODULE-STATUS.md` also records that it was not recovered from repository history or available archives. The Equipment requirements cannot be reconstructed from it here. |
| `docs/SRS.md` | Controlled working specification, baseline `v1.0 — PRE-IMPLEMENTATION`. | Its opening authority note says the original SRS is missing and that this document is not a replacement for client approval. It is not an approved Equipment baseline. |
| `AGENTS.md` and `docs/PROJECT-RULES.md` | General MCC architecture, security, authorization, branch isolation, audit, and approval gates. | These are engineering constraints. They do not define Equipment workflows, fields, permissions, or screens. Both prohibit creating business modules or functionality without approved requirements and decisions. |
| `docs/REQUIREMENTS.md` | Product navigation and implementation traceability. | `REQ-EQUIPMENT-001` records the limited user-provided scope; it is not client approval of a replacement baseline. |
| `docs/DECISIONS.md` | Current decision register. | `EQUIP-01` records limited user-provided direction, not client approval. `SCH-02` and `SCH-03` concern their stated scheduling scope; `GROUND-01` concerns Ground maintenance only. |
| `docs/REQUIREMENTS-REVIEW.md` | Earlier requirements analysis and gap inventory. | Its references to Equipment as a session dependency, resource inventory, availability, and conflicts are analysis, not an approved Equipment requirements baseline. Recommendations in this document are not approvals. |
| `docs/MODULE-STATUS.md` | Dated status and navigation notes. | Its 2026-09-04 status notes that Equipment remains deferred. The document says its older status snapshot is historical and superseded for traceability by `REQUIREMENTS-TRACEABILITY.md`. |
| `docs/UI-ARCHITECTURE.md` | Shared presentation conventions. | It says planned/future entries are not linked until implemented. It provides no Equipment screen specification. |
| `docs/ARCHITECTURE.md` | Architecture record named in `AGENTS.md`. | Records the user-authorized Equipment module architecture; it does not authorize wider workflows. |

The shared navigation and Equipment module are implemented under `Grounds & Equipment` for the scoped permissions. The existing Grounds module is separate and does not define Equipment behavior.

## Decision requests

For each item, select a response and enter the approver’s exact resolution. Leave unresolved items as `NEEDS_DECISION`.

### E-01 — Phase, scope, and boundary

**Status:** `PARTIALLY_RESOLVED` — inventory create/list is authorized as a limited user direction; phase/release and wider product boundary remain `NEEDS_DECISION`.  
**Sources:** `docs/REQUIREMENTS.md` (`Grounds & Equipment [future scope]`); `docs/MODULE-STATUS.md` (Equipment deferred); `AGENTS.md` and `docs/PROJECT-RULES.md` (implementation gates).

1. Is Equipment in scope for a named phase or release?
2. What outcomes must that phase deliver, and what is explicitly out of scope?
3. Is Equipment a standalone capability, part of Grounds, or another approved boundary? No option is selected in the current records.

**Response:** ☐ Approved  ☐ Rejected  ☐ Needs discussion  
**Exact resolution / comments:** ________________________________________________  
**Approver:** ______________________________  **Date:** __________________

### E-02 — Workflows and lifecycle

**Status:** `PARTIALLY_RESOLVED` — create/list/edit/soft delete are authorized by user direction; hard deletion, restoration, and other lifecycle workflows remain `NEEDS_DECISION` and out of scope.  
**Sources:** `docs/SRS-original.md` unavailable; `docs/REQUIREMENTS-REVIEW.md` §12 is historical analysis and mentions resource inventory and Equipment availability without an approved lifecycle; `EQUIP-01` in `docs/DECISIONS.md` records limited user-provided create/list/edit/soft-delete direction, not client approval.

1. What additional Equipment workflows are required beyond create, list, edit, and soft delete?
2. Is a restore workflow required, and who may restore a soft-deleted record?
3. How should retirement, loss, and disposal be represented, if in scope?
4. Which transitions require a reason, approval, or retained history?

**Response:** ☐ Approved  ☐ Rejected  ☐ Needs discussion  
**Exact resolution / comments:** ________________________________________________  
**Approver:** ______________________________  **Date:** __________________

### E-03 — Required fields and relationships

**Status:** `PARTIALLY_RESOLVED` — Name, Quantity, and Branch are specified for this slice. The implementation currently requires a Name of at most 150 characters and a positive whole Quantity within the database integer range as provisional technical constraints; those boundaries are not client-approved. Name length, zero/fractional quantity, upper-range policy, uniqueness, further fields, and relationships remain `NEEDS_DECISION`.  
**Sources:** `docs/SRS-original.md` unavailable; `docs/REQUIREMENTS-REVIEW.md` §12 and its entity inventory are analysis, not approved field or relationship definitions; `AGENTS.md` prohibits creating database structure without approved requirements.

1. Which fields are required, optional, controlled-vocabulary, or system-generated?
2. Is Equipment represented as individually identified assets, quantities, or another approved unit of record?
3. What identifiers and duplicate-handling rules are required?
4. Which relationships are required or optional, including Branch, Ground, Session, coach, player, or other records? Specify cardinality and behavior when related records change.
5. Which values may be changed after creation, and which values require history?

**Response:** ☐ Approved  ☐ Rejected  ☐ Needs discussion  
**Exact resolution / comments:** ________________________________________________  
**Approver:** ______________________________  **Date:** __________________

### E-04 — Branch ownership and permissions

**Status:** `RESOLVED_FOR_LIMITED_SCOPE` — role mapping, `equipment.view/create/update/delete`, immutable Branch on edit, and branch scoping are recorded in `EQUIP-01`; inactive assigned branches for Super Admin, overlapping Super Admin and Branch Admin roles, future actions, and the wider RBAC matrix remain `NEEDS_DECISION`.  
**Sources:** `AGENTS.md` and `docs/PROJECT-RULES.md` require centralized server-side RBAC and explicit branch isolation; `EQUIP-01` records scoped Equipment permissions; `SCH-02` defines session authority and branch ownership only and is not Equipment authorization.

1. Is each Equipment record owned by one branch, shared across branches, or governed by another rule?
2. Which roles may view, create, update, assign, transfer, maintain, retire, or administer Equipment?
3. What cross-branch access, if any, is authorized, and which permission names and server-side checks are required?
4. Who may select a branch when creating a record, and how are branch transfers authorized and recorded?

**Response:** ☐ Approved  ☐ Rejected  ☐ Needs discussion  
**Exact resolution / comments:** ________________________________________________  
**Approver:** ______________________________  **Date:** __________________

### E-05 — Availability, assignment, and maintenance

**Status:** `NEEDS_DECISION` — these workflows are excluded from the current implementation.  
**Sources:** `docs/REQUIREMENTS-REVIEW.md` §12 historically mentions Equipment availability/conflicts; `SCH-03` in `docs/DECISIONS.md` is approved for its stated scheduling/capacity scope but does not define Equipment availability or assignment; `GROUND-01` applies only to Ground maintenance and is `NEEDS_DECISION`.

1. What does Equipment availability mean, and which events change it?
2. Is Equipment allocated, assigned, reserved, checked out, or otherwise associated with a person, session, or location? Define ownership, duration, return, and overdue handling where applicable.
3. Is maintenance tracked for Equipment? If so, define its lifecycle, required details, authorization, effect on availability, corrections, and history.
4. What conflict behavior is required when an Equipment item is unavailable or already allocated? Define any capacity, override, and audit rules.

**Response:** ☐ Approved  ☐ Rejected  ☐ Needs discussion  
**Exact resolution / comments:** ________________________________________________  
**Approver:** ______________________________  **Date:** __________________

### E-06 — Links to Grounds and scheduling

**Status:** `NEEDS_DECISION` — no Grounds or scheduling relationship is authorized in this slice.  
**Sources:** `docs/REQUIREMENTS-REVIEW.md` §12 historically lists Ground, Session, and Equipment dependencies and Equipment conflicts; `SCH-02` approves session authority/eligibility, not Equipment relationships; `SCH-03` approves its recorded capacity/conflict policy, not Equipment-specific allocation; `docs/DECISIONS.md` `GROUND-01` concerns Ground maintenance only.

1. Must Equipment be linked to Grounds, Sessions, or both? If so, define when each link is required, its cardinality, branch rules, and history.
2. Does scheduling reserve or allocate Equipment? If yes, define the source of truth, conflict timing, failure behavior, and who may override a conflict.
3. Are Equipment records usable across Grounds or branches? Define any location and transfer constraints.

Do not inherit Equipment behavior from the Grounds module or infer it from `SCH-02`/`SCH-03`.

**Response:** ☐ Approved  ☐ Rejected  ☐ Needs discussion  
**Exact resolution / comments:** ________________________________________________  
**Approver:** ______________________________  **Date:** __________________

### E-07 — Audit and history

**Status:** `PARTIALLY_RESOLVED` — create, update, and soft delete write audit events transactionally; broader Equipment history, audit visibility, and retention policy remain `NEEDS_DECISION`.  
**Sources:** `AGENTS.md` and `docs/PROJECT-RULES.md` require audit coverage for critical changes; `EQUIP-01` specifies create, update, and soft-delete audit events for this user-authorized slice. Broader Equipment history, visibility, and retention remain unresolved.

1. Which Equipment actions and fields must be audited?
2. Which actor, timestamp, branch, reason, before/after summary, and relationship details must be recorded?
3. Must history be append-only? Who may view it, and are correction or retirement records required?
4. Are exports, reports, or notifications in scope, and what permissions and audit events apply?

**Response:** ☐ Approved  ☐ Rejected  ☐ Needs discussion  
**Exact resolution / comments:** ________________________________________________  
**Approver:** ______________________________  **Date:** __________________

### E-08 — Screens and interaction scope

**Status:** `PARTIALLY_RESOLVED` — authorized list, create form, edit form, and delete confirmation screen are included. Detail, restore, and other screens remain `NEEDS_DECISION` or out of scope under `EQUIP-01`.  
**Sources:** `docs/REQUIREMENTS.md` records the limited user-authorized scope; `docs/UI-ARCHITECTURE.md` governs shared presentation; Equipment list, create, edit, and delete-confirmation screens are implemented for the scoped permissions.

1. Which screens are required: list, detail, create, edit, or other named screens?
2. For each screen, which fields, status/history, relationships, filters, sorting, actions, and empty/error/validation states must appear?
3. Which actions should be inline, separate pages, or modal dialogs? Specify permissions and confirmation needs for each.
4. Which roles need responsive/mobile access, and are there accessibility or keyboard-use requirements beyond the shared UI standard?

**Response:** ☐ Approved  ☐ Rejected  ☐ Needs discussion  
**Exact resolution / comments:** ________________________________________________  
**Approver:** ______________________________  **Date:** __________________

### E-09 — Technical architecture and acceptance

**Status:** `PARTIALLY_RESOLVED` — the user direction authorizes the limited inventory implementation using existing MCC architecture; wider architecture/integration and acceptance scope remain `NEEDS_DECISION`.  
**Sources:** `AGENTS.md` defines the general CodeIgniter module pattern and explicit routes; `docs/ARCHITECTURE.md` is absent; `docs/UI-ARCHITECTURE.md` covers presentation only; no Equipment data model, migration, or acceptance criteria exists.

1. Confirm the approved module boundary and ownership of Equipment data and workflows.
2. Approve the required persistence, identifiers, retention/history, and integration contracts after E-01 through E-08 are resolved.
3. Define acceptance criteria for permissions, branch isolation, validation, lifecycle, allocation conflicts, audit, and each approved screen.
4. Confirm phase priority and release acceptance authority.

**Response:** ☐ Approved  ☐ Rejected  ☐ Needs discussion  
**Exact resolution / comments:** ________________________________________________  
**Approver:** ______________________________  **Date:** __________________

## Approval record

**Overall decision:** ☐ Approved  ☐ Rejected  ☐ Needs discussion  
**Approved scope/version:** _________________________________________________  
**Explicit exclusions/deferred items:** ________________________________________  
**Authorized approver:** __________________________  **Decision date:** ____________  
**Comments / follow-up owner:** _______________________________________________

The user-provided `EQUIP-01` direction authorizes the limited inventory slice implemented in this repository, but it is not client approval and does not replace the unavailable original SRS or approve a wider baseline. Other Equipment behavior remains future scope and `NEEDS_DECISION`.
