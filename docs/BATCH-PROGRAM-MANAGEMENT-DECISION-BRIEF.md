# Batch and Program Management Decision Brief

**Status:** `USER_DIRECTION_IMPLEMENTED_LIMITED_SCOPE`; broader lifecycle decisions remain `NEEDS_DECISION`

**Purpose:** Record the missing requirements and architecture decisions around the limited user-provided implementation. This is not client approval of the replacement requirements baseline.

## Recorded user direction (2026-09-22)

- Programs may be created and listed with the required field `name` only.
- Batches may be created and listed with required `name`, an existing Program, and a Branch.
- Super Admin selects an authorized Branch. Branch Admin uses the validated active Branch and cannot override it through submitted values.
- Capacity, status, description, fees, duration, curriculum, coaches, schedules, recurrence, attendance, edit, delete, assignment, and other workflows are out of scope.

The implementation adds a nullable `batches.program_id` so existing Batch rows remain readable. New Batch creation requires a Program. Backfilling existing rows and making the relationship non-null remain `NEEDS_DECISION`.

## Current evidence

| Area | Current evidence | Source / status |
|---|---|---|
| Batch persistence | Migration `202608290004_CreatePlayerAssignmentTables` creates `batches` with `id`, `branch_id`, `name`, `created_at`, and `updated_at`. It has no Program foreign key. | Existing implementation; not a Program management design |
| Batch model | `app/Modules/Players/Models/BatchModel.php` now aligns with the existing schema and allows `branch_id`, `program_id`, `name`, and timestamps. The former unsupported `capacity` allowlist entry was removed. | User-direction reconciliation; capacity remains `NEEDS_DECISION` |
| Batch use | Admissions, Players, Scheduling, and Attendance read or assign existing batches. The limited Batch list/create routes and navigation are now implemented. | User direction implemented; dependent workflows preserved |
| Program persistence | `programs` is added by the scoped migration with `name` and system metadata only. | User direction implemented; not client approval |
| Branch ownership | Batches are branch-owned by default. Explicit shared access requires a separate decision. | `BR-04` — `APPROVED` |
| Player assignment | One active batch/group assignment, effective dates, replacement history, branch match, and `player.batch.assign` are recorded as the approved assignment baseline. | `PA-01` — `APPROVED`; this does not define Batch CRUD |
| Scheduling relationship | Sessions use an existing `batch_id`; one-time session scope is approved. Scheduling decisions do not define Program fields or Batch management. | `REQ-SESSION-001`, `SCH-01`–`SCH-05` |
| Management permissions | `program.view`, `program.create`, `batch.view`, and `batch.create` are catalogued and granted only to Super Admin and Branch Admin by migration/restore seed. | User direction implemented; not client approval |
| Original SRS | `docs/SRS-original.md` is not present in the available project files. | Do not reconstruct or treat another SRS as approved |

## Broader decisions remaining after the limited implementation

The required fields and create/list permissions in `PROG-BATCH-01` are recorded above. The items below remain `NEEDS_DECISION` unless an approver records an explicit answer.

### 1. Program lifecycle and ownership — `NEEDS_DECISION`

- Is a Program branch-owned, global, or both? May the same Program be used by multiple branches?
- What statuses and lifecycle transitions exist (for example active/inactive), and who may change them?
- Are Programs standalone reference records, or do they have scheduling, attendance, fee, or player-enrollment behavior?
- Is edit and/or archive/deactivate required? Is deletion prohibited, soft deletion, or not in scope?

**Sources:** `REQUIREMENTS-REVIEW.md` §4 and §26 list Batch Management as a supporting/MVP capability but do not define Program management; `REQ-PLAYER-001` and `REQ-SESSION-001` mention batch relationships, not Program fields or lifecycle.

### 2. Batch lifecycle and existing-row relationship — `NEEDS_DECISION`

- Must every Batch reference exactly one Program? May a Program have many Batches, and may a Batch ever change Programs?
- New Batches require a Program under the recorded direction; decide how existing null `program_id` rows are backfilled and whether the column may become non-null.
- Define Batch lifecycle/status, edit/archive behavior, and whether existing player/session history must be preserved when a Batch changes.
- Confirm whether capacity is in scope. `SCH-03` and `PA-01` require an approved capacity before enforcement; they do not approve a Batch capacity value or UI.

**Sources:** `BR-04` (`APPROVED`), `PA-01` (`APPROVED` assignment baseline), `REQ-PLAYER-001`, `REQ-SESSION-001`, `SCH-03`, `202608290004_CreatePlayerAssignmentTables`, and `BatchModel.php`.

### 3. Branch and active-branch extensions — `NEEDS_DECISION`

- Confirm whether Programs may be shared across branches. The default for Batches is branch-owned under `BR-04`; no Program sharing rule is recorded.

**Sources:** `BR-04`, existing branch authorization services, and `docs/UI-ARCHITECTURE.md` active-branch behavior.

### 4. Future permissions and authorized roles — `NEEDS_DECISION`

- Which roles, if any, may update, archive, or delete Programs and Batches?
- `player.batch.assign` is approved for assigning a player to a Batch; it must not be treated as Batch management authorization without an explicit decision.

**Sources:** `docs/RBAC.md`, `app/Services/AdminService.php`, `PA-01`, and the role matrix in `REQUIREMENTS-REVIEW.md` §5.

### 5. Audit, history, and relationships — `NEEDS_DECISION`

- Identify the required audit events and fields for Program/Batch creation, changes, status changes, archive/delete attempts, and branch changes.
- Confirm whether Batch changes must preserve append-only history for player assignments and scheduled sessions. Existing `PA-01` preserves assignment history but does not define Batch record history.
- Confirm whether changing or archiving a Program/Batch is blocked while dependent players, sessions, or admissions reference it.

**Sources:** `OPS-01`, `PA-01`, `SCH-04`, existing audit services, and the current foreign keys.

### 6. Additional screens and navigation — `NEEDS_DECISION`

- Confirm whether edit, archive/deactivate, detail, and dependency views are required.
- Confirm whether the current Coaching navigation labels and global Program scope should remain.

## Remaining decisions

Lifecycle/status, capacity, dependent-record behavior, edit/delete, coach assignment, scheduling, recurrence, attendance, fees, Program branch sharing, existing Batch backfill, and whether Program management should be branch-scoped remain `NEEDS_DECISION`.

## Safe next step

An authorized approver should complete the decisions above. After approval, update `docs/REQUIREMENTS.md`, `docs/DECISIONS.md`, `docs/ARCHITECTURE.md`, `docs/DATABASE.md`, and `docs/RBAC.md` with the exact fields, relationships, permissions, lifecycle, branch rules, and audit behavior. Only then should migrations, services, controllers, views, routes, and navigation be implemented.

The limited source, migration, seed, navigation, and documentation changes are recorded in the working tree. No database migration was applied.
