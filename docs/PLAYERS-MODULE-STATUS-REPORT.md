# Players Module Status Report

**Review date:** 2026-09-24  
**Review type:** Read-only source and document inventory  
**Scope:** Players routes, controllers, services, models, views, migrations, navigation, permissions, branch checks, document/photo handling, tests, and related records.  
**Constraints:** No code, test, database, or existing decision record was changed. Tests were not run. `docs/SRS-original.md` is not present; its requirements were not reconstructed from code.

## Executive summary

The Players module has a working, branch-scoped browser slice, but the repository still records `REQ-PLAYER-001` as `PARTIALLY_IMPLEMENTED`. The implemented slice is strongest for conversion lineage, profile reads/updates, contacts, lifecycle history, branch history/transfer, batch assignment, coach assignment, and private profile-photo handling. Guardian/portal policy, document policy evidence, real-browser evidence, deployment evidence, and several wider Player capabilities remain unresolved or unverified.

## Source map

- **Routes/controllers:** `app/Modules/Players/Config/Routes.php`, `Controllers/Browser.php`, and the JSON-oriented `Controllers/Players.php`. The canonical browser route is `/players/{id}`; `/players/{id}/page` is a compatibility redirect. There is no standalone Player-create route.
- **Service/persistence:** `app/Services/PlayerService.php` contains the main workflows and uses explicit SQL/query-builder reads and transactions. Player model classes are present under `app/Modules/Players/Models/` (`PlayerModel`, `BatchModel`, `PlayerBatchAssignmentModel`, `PlayerCoachAssignmentModel`, `PlayerStatusHistoryModel`), but the browser service is the effective orchestration boundary.
- **Views/navigation:** `Views/index.php` and `Views/show.php`; shared navigation is defined by `app/Services/NavigationService.php` and permission descriptions/grants by `app/Services/AdminService.php` plus the authorization seeders.
- **Schema:** Players/persons/branch history/contacts originate in `202608280001_CreateSprintOneSchema.php`; assignment tables in `202608290004_CreatePlayerAssignmentTables.php`; status history in `20260831000500_CreatePlayerStatusHistory.php`; private documents in `20260904000700_CreateDocumentManagement.php` and its admission relationship/permission migrations.

## Capability inventory

| Capability | Implementation evidence | Tests/evidence | Status |
|---|---|---|---|
| List and filtering | `app/Modules/Players/Config/Routes.php` (`GET /players`); `Browser::index()`; `PlayerService::list()` scopes to `session('authorizedBranches')` and supports query, status, branch, and pagination; `Views/index.php` renders the table and filter form. | `tests/database/PlayerBrowserSecuritySafeTest.php` covers authorized list, search/status filters, branch isolation, and shared shell. | **PARTIALLY_COMPLETE**; safe browser contract is covered, but no real-browser or deployment evidence. |
| Detail/profile | `GET /players/{id}` → `Browser::showPage()` → `PlayerService::find()`; profile and admission/Lead/Trial lineage are rendered in `Views/show.php`. `find()` authorizes the current player branch and checks every historical branch used by the detail view. | `PlayerBrowserSecuritySafeTest.php`; `Player360IntegrationTest.php` for related authorized detail data. | **IMPLEMENTED for documented slice**, with verification limits below. |
| Create/conversion | No standalone Player create route or permission exists. `AdmissionService` creates the Player during approved Admission conversion; `AD-03` requires one idempotent conversion and preserves `admission_id`/optional `lead_id`. | `PlayerWriteWorkflowSafeTest.php` covers Lead-linked/direct conversion, idempotency, lineage, and unauthorized/cross-branch conversion denial. | **IMPLEMENTED through Admissions**; standalone creation is not an implemented or documented workflow. |
| Profile edit | `POST /players/{id}/profile` → `Browser::update()` → `PlayerService::updateProfile()`; explicit profile allowlist, validation, optimistic revision, branch authorization, and audit are present. | `PlayerWriteWorkflowSafeTest.php` covers validation, branch-field escalation rejection, and persistence. | **IMPLEMENTED for approved profile fields**. |
| Lifecycle/status | `POST /players/{id}/status`; status vocabulary and reason are validated; service writes `player_status_history` and audit data with optimistic concurrency. | `PlayerWriteWorkflowSafeTest.php` covers reason, history/actor/audit, duplicate status, and cross-branch denial. | **PARTIALLY_COMPLETE**; see confirmed authorization defect. |
| Branch transfer/history | `POST /players/{id}/branch`; service requires `player.transfer`, validates target branch authorization, serializes history, updates current ownership, and audits. | Safe write coverage includes cross-branch denial; source contract checks route/service boundaries. | **IMPLEMENTED for BR-02 scope**. |
| Batch assignment/history | `POST /players/{id}/batch`; `assignPlacement()` enforces `player.batch.assign`, same player branch, one active placement, effective dates, optimistic revision, audit, and append-only rows. | `PlayerWriteWorkflowSafeTest.php`; `PA-01` is recorded approved. | **IMPLEMENTED for PA-01 scope**. |
| Coach assignment/history | `POST /players/{id}/coach`; same service path enforces `player.coach.assign`, coach branch membership, active replacement, dates, optimistic revision, and audit. | `PlayerWriteWorkflowSafeTest.php`; `PA-02` is recorded approved. | **IMPLEMENTED for PA-02 scope**. |
| Contacts | `POST /players/{id}/contacts`; service permits the documented `guardian`, `emergency-contact`, and `primary-contact` types, validates name/email/phone, stores a related `persons` row and `player_contacts` relationship, and audits. | `PlayerWriteWorkflowSafeTest.php`; `REL-01` and `REL-02` are approved. | **IMPLEMENTED for contact creation**; broader relationship visibility/revocation remains unresolved. |
| Profile photo | Player update form accepts JPEG/PNG only after conversion; `DocumentManagementService::uploadPlayerPhoto()` delegates to private storage, `document.upload`, MIME/content checks, metadata/history, and audit. `authorizedProfilePhotoId()` selects the newest active JPEG/PNG; the view uses `/documents/{id}/preview`, whose route calls authorized private `read()`. | Source contracts and document integration tests cover private document paths; no dedicated Players end-to-end photo test was found in the reviewed test set. | **PARTIALLY_COMPLETE / NEEDS_VERIFICATION**; deployment scanner and real-browser evidence are not available. |
| Player 360 | Navigation and `GET /player-360/{id}` expose the separate authorized view. | `Player360IntegrationTest.php` covers permission and cross-branch denial. | **IMPLEMENTED for current 360 slice**; broader portal/relationship policy is separate. |
| Development/performance links | Player detail conditionally calls `performance.view` and links to Performance; Development is a navigation entry. | Development contract/lineage tests exist; no Player-owned development workflow is defined here. | **SEPARATE / NEEDS_DECISION** where formulas, visibility, and lifecycle are required. |

## Authorization and branch isolation

- Browser routes use `auth` plus centralized permission filters in `app/Modules/Players/Config/Routes.php`: `player.view`, `player.update`, `player.transfer`, `player.batch.assign`, and `player.coach.assign`.
- `PlayerService::list()` filters by the session's authorized branches. `find()` authorizes the current branch and validates authorization for each historical branch, batch row, and coach row before returning them.
- Writes resolve the player and branch from persisted records; submitted branch IDs are not used to authorize profile, batch, coach, or transfer operations. Assignment targets are checked against the player's branch or current user-branch membership.
- `AdminService`, `AuthorizationRestoreSeeder`, and `TestDatasetSeeder` document the player permissions. There is no `player.create` or `player.delete` permission, consistent with conversion-owned creation and no implemented delete workflow.

### Confirmed defect

`POST /players/{id}/status` is route-filtered and rendered using `player.update`, but `PlayerService::changeStatus()` also requires `rbac.manage` (see `app/Services/PlayerService.php`, lines 81–84). The documented permission catalog describes `player.update` as “Update player profiles” and does not state that lifecycle changes require `rbac.manage`. A user who has `player.update` and branch authorization but lacks `rbac.manage` can see the status form and pass the route filter, then receive `Forbidden` from the service. This is an authorization contract mismatch and should be resolved before treating status changes as complete. No permission was changed during this review.

## Sensitive data and document handling

- Profile email, phone, date of birth, contacts, admission lineage, and status history are returned only after branch authorization in `PlayerService::find()`; view output uses escaping for displayed values.
- Photos and other documents use `DocumentManagementService`, private storage under `WRITEPATH . 'private'`, request-time `document.view`/`document.download` checks, and the existing preview route. Storage keys are not exposed in the Player view.
- `SEC-06`–`SEC-13`, `DOC-01`–`DOC-08`, and `REQ-DOC-001/002` still require policy and deployment evidence for document verification, unverified visibility, medical-document visibility, retention, replacement, encryption/key management, scanning operation, and portal access. The current code must not be treated as approval of those policies.
- `SEC-13` establishes consent/legal-basis records, but the reviewed Players screens do not establish a complete consent, withdrawal, or relationship-revocation workflow. `REL-03`–`REL-05` remain `NEEDS_DECISION`.

## UI findings

- `Views/index.php` and substantial portions of `Views/show.php` use compressed legacy markup and inline dense structure rather than the readable multi-line standard in `docs/CODING-STANDARD.md`.
- The list uses the shared `mcc-table`, `mcc-table-wrap`, and `mcc-button` classes, but the action menu, lineage/contact columns, and long contact strings rely on generic table wrapping. Narrow-screen behavior and keyboard interaction need real-browser verification.
- The detail page has a large, multi-section profile with repeated forms for profile, lifecycle, contacts, branch, batch, and coach actions. The source uses shared cards/buttons, but spacing and action placement should be compared against the current shared detail patterns in a browser before calling the UI complete.
- The profile photo upload is placed in the hero and is conditional on both permissions and admission status `CONVERTED`; this preserves the current upload rule but has no dedicated rendered-flow evidence in the reviewed safe tests.
- The detail template references a Performance action only when `performance.view` is granted. That permission is not present in the reviewed `AdminService` catalog/seeder entries; Performance is therefore a separate unresolved/deferred capability, not a safe reason to broaden Player permissions.

## Test coverage and limits

Relevant test files found (not run):

- `tests/database/PlayerBrowserSecuritySafeTest.php` — authentication/permission denial, branch-scoped list/detail, filters, canonical/legacy route behavior, lineage/lifecycle presentation, shared layout, and route contracts.
- `tests/database/PlayerWriteWorkflowSafeTest.php` — conversion, idempotency, lineage, profile validation, contact creation, lifecycle history/audit, and batch/coach assignment boundaries.
- `tests/database/Player360IntegrationTest.php` — Player 360 authorization and branch isolation.
- `tests/Unit/PlayerModuleContractTest.php` — route/service/view contract checks.
- `tests/database/PlayerManagementIntegrationTest.php` — legacy destructive integration coverage; project documents state it is excluded.
- `tests/database/PlayerDevelopmentIntegrationTest.php`, `PlayerDevelopmentLineageSafeTest.php`, and related unit contracts cover separate Development functionality, not a complete Player workflow.

Unverified or incomplete evidence includes: a dedicated end-to-end profile-photo upload/preview test, status behavior for a user with `player.update` but no `rbac.manage`, migration/deployment evidence, scanner/consent operations, real-browser desktop/mobile checks, and production evidence.

## NEEDS_DECISION items (not defects)

- `REL-03`–`REL-05`: parent category visibility, player relationship eligibility/visibility, and relationship change/revocation.
- `DAT-02`: migration/reconciliation ownership and handling for historical Player/contact data, despite its recorded status; operational evidence is still not present in this source review.
- `REQ-DOC-001/002`, `SEC-06`–`SEC-13`, `DOC-01`–`DOC-08`: document verification, scan provider/operation, category visibility, medical access, retention, replacement, encryption/key management, and portal access.
- Player lifecycle details beyond the implemented status vocabulary and controlled corrections, including exact role boundary for status changes if `rbac.manage` is not intended.
- Player 360, Development, Performance, attendance, fitness, portals, reporting, media, and other SRS areas whose formulas, visibility, or workflows are deferred or separately governed.

## Prioritized smallest safe next fixes

1. **Resolve and test the status-permission contract (highest priority).** Record whether lifecycle changes use `player.update` or a separate approved permission, then align the route, view, service, RBAC record, and focused authorization tests. This is a concrete access-control defect and does not require a broad refactor.
2. **Add focused photo-flow coverage.** Cover converted-player upload, JPEG/PNG validation, newest active-photo selection, branch denial, and private preview authorization without exposing storage keys. This closes the highest-risk unverified sensitive-data path without changing policy.
3. **Perform a source-only UI cleanup plan, then one narrow rendered fix.** Start with the Players list action menu/table responsiveness or the detail action/form spacing after browser evidence identifies the first visible defect. Keep shared classes and behavior unchanged.
4. **Reconcile document and relationship decisions before extending Players.** Obtain decisions for `REL-03`–`REL-05` and `DOC-01`–`DOC-08`; do not add guardian portals, medical visibility, document verification, retention, or revocation behavior from existing code alone.
5. **Collect deployment and migration evidence.** Verify the intended test environment, migration state, private storage/scanner configuration, and release evidence separately from repository claims.

## Recommended first focused Players task

Resolve the confirmed status authorization mismatch: have the authorized approver confirm the intended permission boundary, then align `PlayerService::changeStatus()`, the browser route/view condition, RBAC documentation, and focused tests. Until that decision is recorded, status behavior should be reported as partially complete; no other Players feature should be broadened to compensate.

## Actions-menu verification (2026-10-19)

The rendered `/public/players` list was inspected with 20 visible rows and 215 total results. Every row exposed the same supported action contract, with 160 controls checked across the rendered page and no malformed or non-positive Player IDs:

- View details uses the canonical `/players/{player_id}` route.
- Edit player, Update status, Manage contacts, Assign branch, Assign batch, and Assign coach open the existing Player browser workflows and retain their centralized permission and branch checks.
- Player 360 view uses the existing `/player-360/{player_id}` route and `player360.view` authorization.

Attendance, sessions, performance, documents, and Admission actions are not displayed in the list menu because no corresponding supported row-action workflow exists in the current Player routes. The Admission relationship remains available from the Player detail view where authorized.

The list now skips malformed Player IDs before rendering action controls. Dropdowns are moved outside the table overflow container, constrained for narrow screens, scrollable when needed, and support focus restoration plus Arrow/Home/End keyboard navigation. The action route, ID propagation, unsupported-action, CSRF, and explicit permission contracts are covered by `PlayerModuleContractTest` and `PlayerActionsMenuRoutesTest`; existing browser security and Player write suites cover missing records, branch isolation, permission denial, lifecycle behavior, conversion/idempotency, audit, and preservation rules.

### Modal assignment correction

The Batch assignment modal error was traced to the optional `effective_to` field. The browser controller passed an empty string to `PlayerService::assignBatch()`, which correctly rejected it as an invalid datetime. Empty optional dates are now normalized to `null` in both browser and API Player controllers. Assignment validation messages also distinguish incomplete dates, stale effective dates, missing active assignments, invalid targets, duplicate assignments, and cross-branch batch/coach selections. Modal forms now have Player-specific unique IDs and retain their explicit action, POST method, CSRF field, Player primary-key route, and action-specific fields. The modal loader rejects a fetched form unless its action exactly matches the triggering Player ID and action, and assignment options are scoped to the Player's current authorized branch. Personal Coach, Attendance, Sessions, Performance, Documents, and Admission actions are not rendered because no corresponding Player-list modal route exists.
