# MCC Project Codebase and Requirements Map

**Analysis date:** 2026-09-20  
**Repository:** `D:\xampp-8.2\htdocs\cricket-club`  
**Review type:** Read-only repository and documentation analysis  
**Verification status:** Tests not run; application and database commands not run.

## Scope, evidence, and exclusions

This map describes the checked-out source and project documents available during this review. Source proves implementation is present; it does not prove client approval, production deployment, or current test success. Approval state below comes only from explicit decision records. Historical tables are identified where current code or documents disagree.

Read first and followed: `AGENTS.md` and `docs/CODING-STANDARD.md`. Also inspected project entry points and manifests, `app/Config/Routes.php`, `Routing.php`, `Filters.php`, module directory/file inventory, cross-cutting service/class method declarations, migration/seeder filenames, test filenames, and governance documents: `REQUIREMENTS-REVIEW.md`, `REQUIREMENTS-TRACEABILITY.md`, `REQUIREMENTS.md`, `DECISIONS.md`, `PHASE-1-DECISIONS.md`, `IMPLEMENTATION-GATE.md`, `MODULE-STATUS.md`, `PROJECT-RULES.md`, `SPRINT-1-HARDENING.md`, `COMPLETION-INVENTORY-AND-RECONCILIATION-CHECKLIST.md`, `PRIORITIZED-COMPLETION-BACKLOG.md`, the replacement-baseline packet, and related briefs.

**Original baseline:** `docs/SRS-original.md` does not exist and is not readable. `docs/REQUIREMENTS-TRACEABILITY.md` reports that no recoverable copy or usable Git archive was found in its earlier search. This analysis did not reconstruct it. `docs/SRS.md` is a working specification, not approved here.

**Exclusions:** No `.env`, credentials, private uploaded documents, personal records, logs, generated caches, `vendor/`, or database contents were inspected. No database connection/query, migration, seeder, application command, or test was run. `public/assets/vendor/` is third-party Font Awesome distribution and was not treated as first-party source. The directory listing excluded `vendor/`, `writable/`, private upload paths, logs, and caches. The test inventory includes filenames and selected source-level coverage descriptions reflected in project documents; every test body was not read. Several large service/controller implementations and all 48 migration bodies were not reviewed line by line. Therefore this is a broad codebase map, not a claim that every source statement was audited. The relevant files to inspect for a future change are named below.

## Executive summary

- `composer.json` requires CodeIgniter 4 `^4.7`, PHP `^8.2`, PSR-4, and PHPUnit `^10.5.16`. Web entry is `public/index.php`; root routes load module route files through `app/Config/Routes.php`; auto-routing is disabled in `app/Config/Routing.php`.
- The current tree contains 21 module directories, shared services/models/filters/libraries, 48 migration files and 3 seeders. This conflicts with `AGENTS.md`'s statement that only the technical foundation exists and there are no approved business modules. Code presence is not approval; preserve code while governance reconciles scope.
- `docs/SRS-original.md`, `docs/ARCHITECTURE.md`, `docs/DATABASE.md`, and `docs/RBAC.md` are absent. The requirements review references an unavailable SRS. The working SRS and traceability replacement cannot independently establish client approval.
- Governance is inconsistent. `DECISIONS.md` says it is authoritative; `IMPLEMENTATION-GATE.md` and older status material say there are no unresolved RBAC decisions or no business code, while the current approval packet, traceability, `AGENTS.md`, and `RBAC-SA-01` retain open issues. Authorized reconciliation is needed; this map does not resolve those differences.
- Main shared risks are identity/RBAC/branch scope, sensitive data and document policy, deployment-only evidence, and divergence between implementation and documented authority. Keep RBAC matrix, Super Admin all-branch scope, sensitive document visibility, attendance calculations, dashboard KPIs, and HPP gated until decisions are explicitly recorded.

## Project structure and architecture

### Top-level roles

| Path | Observed role |
|---|---|
| `app/` | CI4 configuration, controllers, filters, models, services, libraries, modules, views, migrations and seeders. |
| `public/` | Web document root; `index.php`, CSS/JS and vendor UI assets. |
| `writable/` | CI4 runtime write area; excluded. |
| `tests/` | Unit, feature, and database/integration suites. |
| `bin/` | Project-specific migration, verification and fixture/setup scripts; not run. |
| `docs/` | Working requirements, decisions, architecture, inventory and governance records. |
| `vendor/` | Composer dependencies; excluded. |
| `composer.json`, `composer.lock` | Dependency constraints/lock and Composer scripts. |
| `phpunit.dist.xml` | PHPUnit configuration. |
| `AGENTS.md` | Project coding, security, architecture and requirements instructions. |

No separate frontend package manifest or build pipeline appears in the inventoried root files. Frontend assets are checked in under `public/assets`.

### Entry points and route loading

- Web: `public/index.php` boots CI4 through configured `app/Config/Paths.php`.
- `app/Config/Routes.php` registers `/` to `Home::index` and requires each `app/Modules/*/Config/Routes.php` discovered with `glob`.
- `app/Config/Routing.php` sets `autoRoute = false` and enables controller attributes. Module route files are explicit, while route-file discovery is filesystem-driven.
- `app/Config/Filters.php` aliases CSRF, auth, permission, HTTPS, cache, performance and toolbar filters. CSRF is global before-filter; required before/after filters include HTTPS/page cache and page cache/performance/toolbar. Read `app/Filters/Auth.php`, `Permission.php`, and `Filters.php` before changing route protection.
- CLI scripts include `bin/migrate.php`, `seed-test-data.php`, `seed-development.php`, `seed-browser-account.php`, `restore-authorization.php`, and verification/smoke scripts. Inventoried only, not invoked.

Key source locations: module route glob `app/Config/Routes.php:6`; auto-routing off `app/Config/Routing.php:12`; auth/permission filter entry points `app/Filters/Auth.php:6` and `app/Filters/Permission.php:6`; authorization methods `app/Services/AuthorizationService.php:43,57`; login attempt `app/Services/AuthService.php:64`; admission conversion `app/Services/AdmissionService.php:523`; attendance correction `app/Services/AttendanceService.php:243`. These line references are navigation aids; re-check after edits because line numbers move.

### Implemented architecture compared with AGENTS.md

The code follows the broad requested CI4 layers: `app/Config`, `Controllers`, `Database`, `Filters`, `Helpers`, `Libraries`, `Models`, `Modules`, `Services`, and `Views` exist. All 21 module directories have `Config/Routes.php` and controller(s); models and views vary by module. Reusable workflows are largely in `app/Services`. Auth, authorization and RBAC are cross-cutting services; private storage and audit/security helpers are under `Libraries`.

Important difference: `AGENTS.md` says the project contains only the technical foundation, has no approved business modules, and requires relevant requirements/architecture review before modules are created. The current tree has CRM, admissions, players, attendance, coaching, tournaments, portals, reports, fitness and other business code. This is a governance/status conflict, not a reason to delete or rewrite code.

## Module and capability inventory

Every listed module has `Config/Routes.php`. Counts are inventory only, not evidence of completeness or approval. Test names are recognizable matching files; cross-module contracts/safe suites may cover additional behavior.

| Module / capability | Purpose and principal files | Test evidence by filename | Status / open areas |
|---|---|---|---|
| Admin | User, role, permission, branch and lead configuration: `app/Modules/Admin/{Config/Routes.php,Controllers/Browser.php,Models/*,Views/*}`, `app/Services/AdminService.php`, `RbacService.php`. | `AdminRbacIntegrationTest.php`, `BranchManagementSafeTest.php`, `AuthorizationServiceTest.php`. | RBAC matrix and account authority unresolved. Catalog code does not approve policy. |
| Auth | Login/logout/recovery: `app/Modules/Auth/*`, `AuthService.php`, `DatabaseRecoveryRateLimiter.php`, `SmtpRecoveryDelivery.php`. | `AuthControllerTest.php`, `AuthLifecycleIntegrationTest.php`, `AuthRecoveryIntegrationTest.php`, `RecoveryRateLimiterIntegrationTest.php`, `SmtpRecoveryDeliveryTest.php`. | `SEC-01`, `SEC-14`, `INT-01` apply only to recorded scope; `AUTH-DESIGN` open in packet. SMTP and native web-session deployment evidence outstanding in `SPRINT-1-HARDENING.md`. |
| CRM | Leads, statuses/sources, follow-ups and import: `app/Modules/CRM/*`, `CrmService.php`. | `CrmLeadSafeWorkflowTest.php`, `CrmFollowUpSafeTest.php`, legacy `CrmLeadIntegrationTest.php`, `CrmLeadBrowserSafeTest.php`. | Bounded approvals (including `LD-13`) recorded. Legacy suites are not necessarily safe for persistent DB per `TEST-DATASET.md`. |
| Trials | Lead-linked trial booking/lifecycle: `app/Modules/Trials/*`, `TrialService.php`. | `TrialManagementIntegrationTest.php`, `TrialModuleContractTest.php`. | Traceability says `TRIAL-01` implemented; deployment and future scope remain separate. |
| Admissions | Intake, checklist, transitions, docs and player conversion: `app/Modules/Admissions/*`, `AdmissionService.php`. | `AdmissionsIntakeIntegrationTest.php`, `AdmissionsBrowserSafeTest.php`, `AdmissionDocumentsIntegrationTest.php`. | `AD-01`–`AD-03` approved in `DECISIONS.md`; original SRS and document policy gaps remain material. |
| Players | Profile, lifecycle, branch transfer, assignments: `app/Modules/Players/*`, `PlayerService.php`. | `PlayerWriteWorkflowSafeTest.php`, `PlayerBrowserSecuritySafeTest.php`, `PlayerManagementIntegrationTest.php`, `Player360IntegrationTest.php`. | Defined slices approved; relationship and sensitive visibility and RBAC edges remain open. |
| Coaches | Coach profiles: `app/Modules/Coaches/*`, `CoachManagementService.php`. | `CoachManagementSafeTest.php`, `CoachManagementModuleContractTest.php`. | Traceability notes permission/assignment completeness and production evidence limits. |
| Scheduling | Sessions, roster, conflicts, cancellation/rescheduling: `app/Modules/Scheduling/*`, `SchedulingService.php`, session models. | `SchedulingSafeWorkflowTest.php`, legacy `SchedulingIntegrationTest.php`, `SessionManagementRegressionTest.php`, `SessionManagementContractTest.php`. | `SCH-01`–`SCH-05`, `OPS-09` recorded approved for bounded scope; recurrence/resource policies separate. Legacy destructive-suite warning in `TEST-DATASET.md`. |
| Attendance | Roster snapshots, event record/correction/history: `app/Modules/Attendance/*`, `AttendanceService.php`. | `AttendanceSafeWorkflowTest.php`, legacy `AttendanceIntegrationTest.php`, `AttendanceModuleContractTest.php`. | Source events exist; denominator, absence calculations, thresholds/alerts remain unresolved/deferred (`AT-01`–`AT-06`). |
| Grounds | Branch grounds and availability: `app/Modules/Grounds/*`, `GroundManagementService.php`. | `GroundManagementSafeTest.php`, `GroundManagementIntegrationTest.php`, `GroundManagementContractTest.php`. | Bounded implementation; operational override policy and deployment evidence require review. |
| Fitness | Fitness assessments/plans: `app/Modules/Fitness/*`, `FitnessManagementService.php`. | `FitnessManagementIntegrationTest.php`, `FitnessModuleContractTest.php`. | Metrics, thresholds, sensitivity and visibility decision-gated. |
| Performance | Performance records: `app/Modules/Performance/*`, `PerformanceService.php`. | `PerformanceIntegrationTest.php`, `FitnessModuleContractTest.php`. | Conflict: traceability says not implemented despite current module and tests. Formulas/visibility unresolved. |
| Development | Player development plans/goals/progress: `app/Modules/Development/*`, `PlayerDevelopmentService.php`. | `PlayerDevelopmentLineageSafeTest.php`, `PlayerDevelopmentContractTest.php`. | Present in tree; traceability does not list it as a separate module. Policies need review. |
| Documents | Private document listing/upload/preview/download: `app/Modules/Documents/*`, `DocumentManagementService.php`, storage libraries. | `AdmissionDocumentsIntegrationTest.php`, `DocumentManagementContractTest.php`, `PrivateStorageTest.php`. | Traceability describes safeguards; type authority, verification, medical/parent visibility, retention, keys and scanning remain unresolved/deployment-dependent. `SEC-06` is not a complete matrix. |
| ParentPortal | Parent views of child data: `app/Modules/ParentPortal/*`, `ParentPortalService.php`. | `ParentPortalIntegrationTest.php`, `ParentPortalContractTest.php`. | Relationship proof, consent, revocation and sensitive visibility open. |
| Reports | Catalog/results and CSV: `app/Modules/Reports/*`, `ReportService.php`. | `ReportBranchScopeIntegrationTest.php`, `ReportContractTest.php`, `ReportBranchScopeContractTest.php`. | `REP-*` definitions/columns/export policy unresolved; code does not approve all export scope. |
| Dashboard | Authenticated landing summaries: `app/Views/dashboard.php`, `DashboardService.php`, `DashboardModel.php`, `NavigationService.php`. | `DashboardIntegrationTest.php`, `DashboardModuleContractTest.php`, `NavigationServiceTest.php`. | Distinguish landing UI from unresolved operational KPI catalog (`DASH-*`). |
| Marketing | Lead/admission summaries: `app/Modules/Marketing/*`, `MarketingDashboardService.php`. | `MarketingDashboardIntegrationTest.php`, `MarketingDashboardContractTest.php`. | Attribution, conversion definitions, targets and ROI not inferable; decisions remain. |
| Notifications | In-app list/read/history: `app/Modules/Notifications/*`, `NotificationService.php`. | `NotificationIntegrationTest.php`, `NotificationContractTest.php`. | Triggers, recipients, channels, retries/preferences/provider unresolved; `INT-01` only covers recovery SMTP. |
| Matches | Match records/participants/results: `app/Modules/Matches/*`, `MatchManagementService.php`. | `MatchManagementIntegrationTest.php`, `MatchManagementContractTest.php`. | Ownership, selection and score/result governance remain decision-sensitive. |
| Tournaments | Tournaments/fixtures/results: `app/Modules/Tournaments/*`, `TournamentManagementService.php`. | `TournamentManagementIntegrationTest.php`, `TournamentManagementContractTest.php`. | Hierarchy, result governance, awards and cross-branch rules unclear. |
| Player360 | Read-only consolidated player view: `app/Modules/Player360/*`, `Player360Service.php`. | `Player360IntegrationTest.php`, `Player360ContractTest.php`. | Aggregation exists; player/document/medical/parent policy constrains categories. |

No dedicated HPP module was found in the module directory listing; `REQUIREMENTS-TRACEABILITY.md` identifies HPP as not implemented/deferred. No separate API, queue worker or scheduler module was identified; full runtime-registration inspection is needed before asserting none exists.

### Cross-cutting capabilities

| Capability | Main files | Notes |
|---|---|---|
| Authentication | `AuthService.php`, Auth controller/routes, `Auth.php`, `Session.php`, `Services.php` | Database-backed login/recovery/session service methods present; architecture remains subject to `AUTH-DESIGN`. |
| Authorization | `AuthorizationService.php`, `RbacService.php`, `Permission.php`, Admin module | Central service/filter exists. Final role-permission catalog/matrix is not established by code. |
| Branch scope | `AuthorizationService::can()` / `canAccessBranch()`, module services/models, branch relationship migrations | Review every resource path. `SEC-03` approves multiple authorized assignments, not universal access. |
| Audit | `AuditLogger.php`, `SensitiveDataRedactor.php`, `AuditPersistenceService.php`, `AuditLogService.php`, migrations | Logging/redaction code/tests exist; OPS-01 deployment evidence is separate. |
| Private storage | `PrivateStorageInterface.php`, `LocalPrivateStorage.php`, `DocumentManagementService.php` | Abstraction and local private adapter; provider/operations decisions separate. |
| Shared UI | `app/Views/{staff_header.php,staff_footer.php,partials/*,components/*}`, CSS/JS assets | Shared shell and custom assets; Font Awesome vendored. |

## Important class/function and request-flow map

This prioritized index is not an exhaustive API catalog. Inspect full method bodies and call sites before changing behavior.

| Layer/class | Representative responsibility / methods |
|---|---|
| Routes | `app/Config/Routes.php` loads module route files; each module `Config/Routes.php` maps explicit paths and filters. |
| Controllers | Auth login/logout/recovery; CRM list/create/update/follow-up/import; Admissions create/checklist/status/convert/document; Players profile/status/branch/batch/coach/contact; Attendance list/record/snapshot/correct; Admin users/roles/permissions/branches/audit. |
| `AuthService` | `isLoggedIn`, `isCurrentSession`, `user`, `roles`, `permissions`, `attempt`, `requestReset`, `resetPassword`, `disable`, `logout`. Recovery limiter/delivery are dependencies. |
| `AuthorizationService` | `isAuthenticated`, `isDisabled`, `can(permission, branchId)`, `canAccessBranch`. Central authorization boundary. |
| `AdminService` | Permission catalog; user/branch/role CRUD; role/permission/branch assignments. `createUser` does not establish which MCC person may invoke it. |
| `AdmissionService` | Intake/list/detail/checklist/transition/completion/conversion; multi-row lifecycle flows depend on approved contracts and persistence. |
| `PlayerService` | List/find/status/contact/assignment/transfer/profile; uses authorization, transaction/version and audit patterns. |
| `AttendanceService` | Session views, roster, event record, roster snapshot, correction/history. Derived rates are separate. |
| `SchedulingService` | Session list/options/create/find/update/cancel/reschedule and coach availability/conflict validation. |
| `DocumentManagementService` | Authorized document list/detail/upload/download/preview and storage; inspect every type-level policy gate. |
| Audit classes | `AuditPersistenceService::record`, `AuditLogService::listForAuthorizedScope`, `AuditLogger::record`, `SensitiveDataRedactor::redact`. |

Representative flows:

1. **Login:** Auth routes → `Auth::login()` validation → `AuthService::attempt()` → identity/password/session checks → session and authorization context → response. Recovery routes call reset request/token handling, rate limiter, delivery adapter, audit and session invalidation.
2. **Protected action:** module route → auth/permission filters or controller checks → service validation and branch-scoped lookup/mutation → model/database and audit → view/redirect/JSON. Check both route and service; hidden navigation is not access control.
3. **Admission conversion:** route → `Admissions\Browser::convert()` → `AdmissionService::convert()` → transaction/player lineage/status/audit → response. `AD-03` approval is bounded; inspect idempotency/concurrency in source/tests before changes.
4. **Document download:** route/controller → service authorization and branch checks → `PrivateStorageInterface` adapter → attachment response. Route existence does not approve access to a category.
5. **Attendance correction:** route/controller → `AttendanceService::correct()` reason/version checks → history and audit. Source facts do not define thresholds or rates.

## Routes, authorization, and branch isolation

Module routes are spread across 21 `Config/Routes.php` files. A full route-by-route table was not compiled here. Before changes, run a read-only source search such as `rg -n '\$routes->' app/Modules app/Config/Routes.php` and inspect all affected route definitions. Auth filter delegates to `service('auth')->isLoggedIn()`; permission filter delegates to `service('authorization')->can($required)`. `BaseAuthenticatedController` reads user/roles/permissions from session. Module services may independently check permissions and branches, so verify both layers for IDOR/cross-branch exposure.

Decision comparison: `SEC-01` approves one database-backed identity model; it does not settle all auth architecture details. `SEC-02` allows multiple roles, `SEC-03` multiple authorized branch assignments, and `SEC-04` centralized/audited changes; they do not define the final matrix. `AGENTS.md` requires central server-side RBAC and explicit Super Admin branch rules. `RBAC-MATRIX` and `RBAC-SA-01` remain `NEEDS_DECISION`, including all-permissions/all-branches proposal, missing approver/date and credential channel. Pay special attention to Admin assignment, ParentPortal, Player360, Reports/CSV, Documents, and branch transfers.

Decision navigation: `docs/DECISIONS.md:1240-1267` (`RBAC-SA-01`), `docs/DECISIONS.md:1334` (unresolved index), `docs/CLIENT-APPROVAL-PACKET-REPLACEMENT-BASELINE.md:68` (`RBAC-MATRIX`), `:70` and `:125` (`DAT-02` conflict), and `docs/REQUIREMENTS-TRACEABILITY.md:3-9` (replacement authority/missing original SRS).

## Data and persistence

There are 48 PHP migration files and 3 seeders in `app/Database`. Names cover sprint-one schema/recovery limiting; CRM/admissions/player lifecycle and assignments; scheduling/attendance; audit append protection; branch/lead/trial configuration; documents, portal, development, tournament/match/ground/fitness/performance, notifications, reports and permission additions. Paired names include `Deployment` variants. Inspect ordering/equivalence and rollback behavior before applying anything; no migration body was exhaustively compared here.

Models are split between shared `app/Models` (`UserModel`, `DashboardModel`) and module-owned models. Services use both CI models and direct query-builder/database operations. For any schema change, compare each table, FK, index, soft-delete/timestamp convention and direct query with its owning migration. Relevant risks include paired deployment migrations, admission/player lineage, branch joins, audit append protection, document metadata versus private storage keys, attendance history protections and soft-delete consistency; exact schema integrity remains unverified.

Seeders: `TestDatasetSeeder.php`, `DevelopmentBootstrapSeeder.php`, `AuthorizationRestoreSeeder.php`. `docs/TEST-DATASET.md` documents persistent `mcc_cricket_club_test` and warns against destructive test suites. This is not a current environment connection check. No seeder ran and no database was queried.

## Validation, sensitive data, documents, and audit

`AGENTS.md` requires server-side validation, output escaping, private documents, secure credentials and redacted audit/log data. Relevant code includes module controllers, `DocumentManagementService`, storage libraries, `SensitiveDataRedactor`, `AuditLogger`, audit persistence, validation config and view helpers. Tests include `SensitiveDataRedactorTest.php`, `PrivateStorageTest.php`, `AuditLoggerTest.php`, `Ops01AuditPolicyTest.php`, plus auth/module workflow suites.

`REQUIREMENTS-TRACEABILITY.md` describes private local storage, generated keys, MIME/size checks, filename/path safeguards, branch/RBAC/IDOR checks, attachment headers and audit/orphan rollback safeguards. Verify those claims in implementations and focused tests before editing; this pass did not inspect every branch. Remaining decisions include type-specific upload/verification authority, medical and parent visibility, retention/replacement, encryption/key ownership, scanning workflow and provider recovery.

Audit code exists and OPS-01 is approved for its stated policy, while `SPRINT-1-HARDENING.md` says database/deployment append protection, scoped reads, retention and production evidence were incomplete as of its status date. Policy approval is not proof deployment controls are enabled. Inspect `202608310004_AddAuditAppendOnlyProtection.php` and deployment counterpart before use.

Sensitive categories named in `AGENTS.md` include identity numbers, birth certificates, passports, visas, medical certificates/conditions, parent contacts and player data. No actual person rows or private files were read or reproduced.

## Frontend, integrations, and runtime boundaries

Views are PHP under `app/Views` and module `Views`; shared staff shell/partials/components are under `app/Views`. CSS is `public/assets/css/{app,components,utilities}.css`; JS is `public/assets/js/app.js`; Font Awesome is vendored. Consult `docs/UI-ARCHITECTURE.md` for detailed conventions. No separate JS framework/build manifest was observed.

`SmtpRecoveryDelivery.php` implements recovery delivery. `INT-01` approves MCC-operated SMTP/relay only for the recorded recovery scope. `Email.php` has configuration structure; secret values and local `.env` were not inspected. Repository inspection cannot prove deployment SMTP, DNS/TLS, provider access, key custody, scheduled execution, workers, backups, monitoring, HTTPS, native web-SAPI sessions, production logs, or production DB identity. These require MCC deployment/operations-owner evidence. No scheduled job/queue infrastructure was established by the inventory; inspect runtime registration before relying on that conclusion.

## Test-file coverage inventory — not run

Inventory count: 26 files under `tests/Unit`, 1 under `tests/Feature`, and 38 under `tests/database` (65 by file count). Filenames indicate unit/contract coverage for authorization, audit/redaction, storage, reports, dashboards, notifications, portals, players, attendance, fitness, matches, tournaments, coaches, trials and controller auth; database suites cover workflows and persistence/branch behavior. Representative safe suites: `CrmLeadSafeWorkflowTest.php`, `CrmFollowUpSafeTest.php`, `AttendanceSafeWorkflowTest.php`, `SchedulingSafeWorkflowTest.php`, `BranchManagementSafeTest.php`, `PlayerWriteWorkflowSafeTest.php`, `PlayerDevelopmentLineageSafeTest.php`, `GroundManagementSafeTest.php`.

`docs/TEST-DATASET.md` warns that legacy suites with destructive cleanup must not run against persistent `mcc_cricket_club_test`. No tests were executed here and no pass result is claimed. Historical counts in hardening, traceability and status documents are dated evidence only.

Documented gaps include true multi-process recovery concurrency, native web-SAPI session ID behavior, SMTP delivery/deployment, production log review, HTTPS, and audit deployment controls. Behavioral/approval gaps include RBAC, sensitive document access, attendance calculations, dashboard KPIs, HPP, performance rules, portal consent/revocation, exports, notification semantics and backup/restore. These are unverified or undecided, not test failures.

## Requirements and decision traceability

Detailed `REQ-*` mappings are in `REQUIREMENTS-TRACEABILITY.md` and `REQUIREMENTS.md`. This is a high-level index; statuses below describe documents, not fresh verification.

| Capability | IDs | Documented state and code/test evidence | Conflict / unresolved matter |
|---|---|---|---|
| Baseline | SRS 1–7, `REQ-*`, `GOV-01/02` | Working `SRS.md`; traceability dated 2026-09-04. Original file absent. | Versioned replacement needs approval; SRS.md is not approved. |
| Auth/recovery/session | `REQ-AUTH-001`, `NFR-02`, `SEC-01`, `SEC-14`, `INT-01`, `AUTH-DESIGN` | Auth service/controller/filters/limiter/delivery and unit/feature/db suites. Decisions limited to recorded wording. | Auth design and deployment evidence outstanding; INT-01 is recovery only. |
| RBAC/Super Admin | `REQ-RBAC-001`, `SEC-02`–`SEC-04`, `RBAC-MATRIX`, `RBAC-SA-01` | Central services/Admin module and integration tests exist. | Final catalog/matrix, Super Admin permissions/branch scope, creator authority, approver/effective date unresolved. Implementation Gate says no unresolved RBAC while current packet/traceability disagree. |
| Branch isolation | `REQ-BRANCH-001`, `SEC-03`, `BR-01`–`BR-09` | Authorization service, branch-aware modules and focused tests. | SEC-03 is multiple authorized branches, not universal access. Recheck every endpoint and exact approved scope. |
| CRM | `REQ-CRM-001`, `LD-*`, `DAT-01` | CRM code and safe tests; decisions record bounded approvals, including LD-13. | Use exact decision text; requirements review/status snapshots include broader open/stale claims. |
| Trials/admissions/players | `REQ-TRIAL-001`, `REQ-ADMISSION-001`, `REQ-PLAYER-001`, `TRIAL-01`, `AD-*`, `PL-*`, `PA-*` | Modules/services/migrations and workflow tests. | Approved slices do not authorize unrelated fields, document policies, or future behavior. |
| Scheduling/grounds | `REQ-SESSION-001`, `SCH-01`–`SCH-05`, `BR-04`, `OPS-09` | Services and safe/regression tests; approved bounded scope in gate documents. | Recurrence, conflict overrides and cross-branch use remain separate. |
| Attendance | `REQ-ATT-001`, `AT-01`–`AT-06` | Event/snapshot/history module and tests/UI. | Denominator, calculations, statuses, cutoffs, thresholds/alerts/recipients open/deferred. |
| Documents/medical/consent | `REQ-DOC-*`, `SEC-05`–`SEC-13`, `DOC-*`, `OPS-03` | Module, Admissions code, storage libraries, tests. | Partial boundaries only; type access, parent/medical visibility, retention, scanning and key/provider decisions open. |
| Audit/operations | `REQ-AUDIT-001`, `OPS-01`–`OPS-12`, `GOV-*` | Audit classes/migrations/tests; OPS-01 approved for explicit policy. | Deployment append/read/retention evidence plus backup, incident, monitoring and ownership decisions remain. |
| Reports/exports | `REQ-REPORT-001`, `REP-*`, `SEC-11` | Module, service, CSV route and branch-scope tests. | Catalog, columns, role policy/export details unresolved; SEC-11 only separates export permission. |
| Dashboard/marketing | `REQ-DASH-001`, `DASH-*` and marketing requirements | Landing/dashboard/marketing services and tests exist. | KPI catalog/formulas and approval remain open. |
| Notifications/integrations | `REQ-NOTIFY-*`, `NOT-*`, `INT-01` | Notification service/UI/tests; recovery adapter. | Triggers/channels/recipients/retries/provider unresolved; INT-01 recovery only. |
| Performance/Fitness/HPP | `REQ-PERF-001`, `REQ-FIT-001`, `REQ-ADV-001`, `PF-*`, `FIT-*`, `HP-*` | Performance/Fitness code and tests; no HPP module found. | Formulas, measures, visibility and HPP scope unresolved. Traceability says Performance absent despite tree. |
| Matches/tournaments/portal/Player360 | `REQ-ADV-001`, `MT-*`, `REL-*`, `SEC-09/13/14` | Modules and tests exist. | Code is not approval; selection/result policy, relationships, visibility and KPIs unresolved. |
| Migration | `DAT-02` | Conflicting source records. | DECISIONS labels APPROVED, but chosen option “None” and approval fields unresolved; workshop/traceability also unresolved. Client confirmation required. |
| Historical status | `MODULE-STATUS.md`, `IMPLEMENTATION-GATE.md`, older review sections | Dated snapshots and claims. | Conflict with current code tree and newer traceability/approval packet. Preserve as historical and reconcile. |

### Document presence checked

Present: `REQUIREMENTS-REVIEW.md`, `REQUIREMENTS-TRACEABILITY.md`, `REQUIREMENTS.md`, `DECISIONS.md`, `PHASE-1-DECISIONS.md`, `MODULE-STATUS.md`, `IMPLEMENTATION-GATE.md`, `PROJECT-RULES.md`, `SPRINT-1-HARDENING.md`, `COMPLETION-INVENTORY-AND-RECONCILIATION-CHECKLIST.md`, `PRIORITIZED-COMPLETION-BACKLOG.md`, `CLIENT-APPROVAL-PACKET-REPLACEMENT-BASELINE.md`, `AUTH-DESIGN-DECISION-BRIEF.md`, `SUPER-ADMIN-TEST-ACCOUNT-DECISION-REQUEST.md`, `UI-ARCHITECTURE.md`, and `TEST-DATASET.md`. Absent: `SRS-original.md`, `ARCHITECTURE.md`, `DATABASE.md`, `RBAC.md`. Presence does not establish approval.

## Conflicts, NEEDS_DECISION items, and deployment evidence

### Conflicts needing reconciliation

1. **Requirements baseline:** `AGENTS.md` names the absent original SRS; replacement packet seeks versioned approval. Do not promote SRS.md or traceability to client approval.
2. **Business scope:** `AGENTS.md` says technical foundation/no approved business modules; current tree has extensive modules. Preserve code; authorized governance must define accepted and remaining scope.
3. **RBAC:** `IMPLEMENTATION-GATE.md` says no unresolved RBAC/branch decisions; `RBAC-MATRIX` remains open in current packet/traceability and `RBAC-SA-01` is open. Reconcile explicitly.
4. **Implementation status drift:** Traceability says Performance not implemented and Dashboard/Reports blocked/not present, while current tree has Performance and Reports modules, dashboard services/views, and matching tests. Older no-code claims also conflict with implementation.
5. **DAT-02:** Decision register labels it approved, but selected option/approver fields and other records show unresolved. Client confirmation required.
6. **Test results:** dated pass counts are not current execution. Some legacy suites are unsafe against the persistent test DB per `TEST-DATASET.md`.
7. **Authentication:** code exists while AGENTS treats generic AuthService as a boundary and approval packet keeps AUTH-DESIGN open. Reconcile authority before auth changes.

### Principal unresolved decision groups

Versioned baseline and accepted functionality; final RBAC/catalog and Super Admin branch scope/account authority; authentication design; guardian proof/consent/visibility/revocation; document and medical policy; attendance calculations; KPI catalog; performance/fitness/HPP criteria; match/tournament result rules; reporting/export; notifications; DAT-02; operational ownership, backup/restore, incidents and monitoring. Use exact IDs and wording in decision records; this map does not amend them.

### Evidence only MCC/deployment owners can provide

- Named client approver/effective dates and which earlier requirement/decision record is superseded.
- Approved permission-catalog version and account creator/credential channel if provisioning is in scope.
- Current target environment identity and explicit non-production confirmation before writes.
- Deployed schema/migration identity, append-only triggers, scoped audit reads, retention operation and audit evidence.
- SMTP delivery/outage, native web-SAPI sessions, HTTPS/TLS/cookies, production log redaction and secret-manager evidence.
- Backup encryption/retention, RPO/RTO, restore exercise and incident ownership.
- Storage provider/encryption/key custody, malware scan operations and recovery.
- Runtime cron/worker/queue state, monitoring, performance/load and release sign-off.

No secret, deployment value, or live account was inspected.

## Prioritized next steps and prerequisites

1. Obtain the original SRS if recoverable, or formal approval for a versioned replacement with named approver/date and supersession scope. Confirm which existing functions are accepted, need remediation, deferred, or out of scope.
2. Reconcile decision/status documents, preserving approved wording/history; resolve RBAC and DAT-02 conflicts; label old snapshots historical; keep unanswered items `NEEDS_DECISION`.
3. Close shared security gates: final permission/branch matrix, endpoint/service branch review, document/medical visibility and audit integrity. Require deployment-owner evidence for deployment claims.
4. Select only clearly approved work; record IDs, acceptance criteria, data/permission/branch impacts, dependency chain and safe test suite. Preserve implementation; change only evidence-backed defects or approved requirements.
5. Before DB-affecting work, verify target identity and follow `TEST-DATASET.md` safety warnings. Run only approved safe suites in explicitly authorized environments.
6. Reconcile traceability, review security, gather deployment evidence and obtain a release decision. Tests do not substitute for client/deployment approval.

## How to use this map in future coding tasks

Before each change, re-read `AGENTS.md`, `docs/CODING-STANDARD.md`, the relevant approved baseline (or record that the original is missing), exact current decisions in `DECISIONS.md`, and affected routes/controllers/services/models/migrations/views/tests. Confirm authority, branch scope, data impact and test-database safety. This map is an index to evidence, not a requirements source or approval record. Do not treat its implementation descriptions, historical test results, `docs/SRS.md`, or code presence as client approval. Keep unresolved matters `NEEDS_DECISION`.
