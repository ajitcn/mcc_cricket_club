# MCC Cricket Club Management System — Module Status

Status date: 2026-09-04
REQUIREMENTS BASELINE: v1.0 — PRE-IMPLEMENTATION
Overall implementation gate: `READY-FOR-IMPLEMENTATION` for the first vertical slice only
Phase 1 decision blockers: `docs/PHASE-1-DECISIONS.md`
Repository evidence: first vertical-slice migration, services, controllers, foundation tests, and focused CRM lead acceptance/database tests exist. Deployment evidence, backup/restore execution, monitoring, and production load tests remain absent.

Deferred navigation note: Assignments and Fixtures are not standalone modules; their existing Player/Coach and Tournament/Match workflows remain the canonical implementations. Performance, alerts, statistics, selection, awards, equipment, High Performance, communications, and Settings entries remain deferred where their requirements, formulas, permissions, or approval policies are unresolved.

Governance note (2026-09-04): the referenced authoritative `docs/SRS-original.md` is not present in this repository and no usable repository history/archive was available to recover it. The earlier status table below is retained as a historical implementation snapshot. The current evidence-based 20-module reconciliation is maintained in `docs/REQUIREMENTS-TRACEABILITY.md` and supersedes that snapshot for traceability purposes. No unresolved decision is treated as approved solely because code exists.

## Current 20-module status (2026-09-04)

This table is the current status summary. Detailed evidence, limitations, and decision traceability are maintained in `docs/REQUIREMENTS-TRACEABILITY.md`.

| # | Module | Current status |
|---:|---|---|
| 1 | Admissions | Implemented with limitations |
| 2 | Players | Implemented with limitations |
| 3 | Coaches | Implemented with limitations |
| 4 | Attendance | Implemented with limitations |
| 5 | Performance | Not implemented |
| 6 | Fitness | Implemented with limitations |
| 7 | Session Management | Implemented with limitations |
| 8 | Ground Management | Implemented with limitations |
| 9 | Match Management | Implemented with limitations |
| 10 | Tournament Management | Implemented with limitations |
| 11 | Player Development | Implemented with limitations |
| 12 | High Performance Program | Not implemented |
| 13 | Parent Portal | Implemented with limitations |
| 14 | Documents | Implemented with limitations |
| 15 | Reports | Implemented with limitations |
| 16 | Dashboard | Implemented with limitations |
| 17 | Notifications | Implemented with limitations |
| 18 | Branch Management | Implemented |
| 19 | Marketing Dashboard | Implemented with limitations |
| 20 | Player 360° | Implemented with limitations |

## Product workflow and navigation baseline

Admissions is the final conversion stage of the Lead/Admission pipeline, not a separate unrelated workflow:

`Lead → Trial Booked → Trial Attended → Selected → Admission Pending → Admission Completed → Active Player`

Lead Source, Campaign, Counselor, follow-up history, next follow-up date, remarks, admission probability, status, trial information, and conversion information are the documented Lead capability set. Only the verified subset is implemented; absent fields and workflows remain planned or decision-gated. Campaigns is not an implemented standalone module or navigation item. Lead Sources and Lead Statuses are admin-managed configuration records, not standalone CRM modules.

The authoritative sidebar architecture and implementation/planning labels are maintained in `docs/REQUIREMENTS.md`. The current rendered navigation is intentionally limited to implemented, authorized routes; documentation entries marked planned, blocked, or future scope are not fake menu links.

| Module/capability | Phase | Priority | Requirements | Decisions | Status | Implementation state | Security readiness | Test readiness | Blocking decisions / gaps |
|---|---|---:|---|---|---|---|---|---|---|
| Authentication and accounts | 0 | MUST | REQ-AUTH-001 | SEC-01, SEC-14, INT-01 (approved) | PARTIALLY_IMPLEMENTED | Recovery request/reset endpoints, database token lifecycle, disabled-account suppression, session invalidation, generic responses, CSRF protection, audit events, and approved database-backed account/IP recovery rate limiting verified against MySQL; real SMTP delivery and deployment evidence are not verified | PARTIAL | VERIFIED | Real SMTP endpoint/deployment evidence and broader release evidence |
| Secure session infrastructure | 0 | MUST | REQ-AUTH-001, NFR-02 | SEC-01, SEC-14 (approved) | FOUNDATION | Secure cookie defaults, login regeneration hook, logout destruction, and session-version checks verified against MySQL and real HTTP/filter requests; exact native session-ID evidence remains unverified in PHPUnit CLI | PARTIAL | VERIFIED | Deployment HTTPS and native web-SAPI session evidence |
| Authorization framework | 0 | MUST | REQ-RBAC-001 | SEC-02, SEC-04 | FOUNDATION | Central generic authorization service and deny-by-default permission filter added; no final policy matrix | BLOCKED | TESTING | Final permissions, identity source |
| Branch-scope framework | 0/1 | MUST | REQ-BRANCH-001 | SEC-03, BR-01-BR-09 | PARTIALLY_IMPLEMENTED | Organization-level branch create/list/details/update plus non-destructive deactivate/reactivate lifecycle using `is_active`, CSRF-protected CI4 MVC routes, preserved relationships, shared staff UI, and audited transitions; physical deletion prohibited | VERIFIED for branch management slice | VERIFIED for safe focused coverage | Broader business ownership and deployment evidence |
| Audit logging infrastructure | 0 | MUST | REQ-AUDIT-001 | OPS-01 (approved) | PARTIALLY_IMPLEMENTED | Durable audit store, centralized persistence boundary, authorized branch-scoped read boundary, audit-of-audit access, and forward-only append-protection migration added; retention disposal remains operationally controlled | PARTIAL | TESTING | Migration/deployment trigger evidence and 7-year operational retention evidence |
| Documents/private media | 0/1 | MUST | REQ-DOC-001, REQ-DOC-002 | SEC-06-SEC-13, OPS-03 | FOUNDATION | Private storage interface/local adapter and redaction helper added; no upload policy or document categories | BLOCKED | TESTING | Document access, categories, retention, provider |
| Backup/restore/operations | 0 | MUST | REQ-OPS-001 | OPS-02, OPS-11, OPS-12 | NOT_STARTED | No documented execution evidence or implementation found | BLOCKED | NOT_STARTED | RPO/RTO, incident response, observability |
| CRM/leads | 1 | MUST | REQ-CRM-001 | LD-01-LD-08, LD-13 (approved), DAT-01 (approved) | IMPLEMENTED | Branch-scoped lead create/view, database-backed configurable source/status values, immutable auto-increment identity, owner/status/timestamps, duplicate warning without merge, controlled Lead/Lost/reopen transitions, lost reason/closed date, audited reassignment, and Follow-Up workflow conforming to approved `LD-13` | VERIFIED for the implemented lead and Follow-Up slices | VERIFIED for approved scope | Deployment/release evidence; broader CRM fields/workflows remain outside verified scope |
| Admissions/conversion | 1 | MUST | REQ-ADMISSION-001 | PL-01-PL-06, AD-01-AD-03, LD-09-LD-11, DAT-01 (approved) | PARTIALLY_IMPLEMENTED | Approved seven-status lifecycle, five-field Phase 1 checklist, Lead-linked and Direct Admission intake, shared list/detail/new/edit UI, safe filters, controlled rejection/withdrawal, idempotent APPROVED-to-CONVERTED player lineage, branch authorization, history, audit, and rollback protection are implemented and tested; trial scheduling remains separate | VERIFIED for lifecycle/conversion, direct intake, and browser MVC rendering tests | VERIFIED | Browser automation, trial scheduling, documents, and any later Phase 1 capability |
| Players/guardians | 1 | MUST | REQ-PLAYER-001 | BR-02, BR-04, BR-06, PA-01, PA-02, REL-01/REL-02 (approved), REL-03-REL-05, DAT-01 (approved), DAT-02 | PARTIALLY_IMPLEMENTED | Admission conversion, Player identity/profile, lifecycle history, branch transfer, batch/group assignment, primary-coach assignment, and approved guardian/emergency/primary contact creation are covered with preserved history, branch authorization, optimistic conflict protection, validation, and audit; guardian visibility/revocation and migration remain separate | VERIFIED for approved write workflows and CI4 MVC render contracts | VERIFIED | REL-03-REL-05, DAT-02, real-browser/production evidence, and scheduling remain separate |
| Sessions/grounds/batches | 1 | MUST | REQ-SESSION-001 | SCH-01-SCH-05, BR-04, OPS-09 | IMPLEMENTED | One-time branch-owned scheduling with persisted roster eligibility, coach authorization/availability, conflict protection, cancellation, linked rescheduling, transaction rollback, audit, UTC persistence, Asia/Kolkata presentation, canonical Browser routes, and shared staff UI | VERIFIED for approved scope: safe workflow/UI test passes | VERIFIED | Attendance remains separate; deployment/release evidence remains pending |
| Attendance | 1 | MUST | REQ-ATT-001 | AT-01-AT-06 | PARTIALLY_IMPLEMENTED | Approved source-event attendance plus canonical server-rendered CI4 MVC session/roster list, approved server-side filters, detail/history UI, CSRF forms, service-mediated recording/correction, audit, and concurrency controls; AT-01 denominator and AT-06 calculations/alerts explicitly deferred | VERIFIED for approved source-event and MVC scope; deployment evidence remains separate | VERIFIED | Production deployment/runtime evidence and true multi-process evidence remain unverified; deferred denominator/formula/alert behavior |
| Reports/exports | 1 | MUST | REQ-REPORT-001 | REP-01-REP-03, SEC-11 | BLOCKED | No report/export code found | BLOCKED | NOT_STARTED | Columns, filters, role policy |
| Operational dashboard | 1 | MUST | REQ-DASH-001 | DASH-01-DASH-03 | BLOCKED | No dashboard code found | BLOCKED | NOT_STARTED | Approved KPI catalog |
| Performance | 2 | SHOULD | REQ-PERF-001 | PF-01-PF-06 | DEFERRED | No module/code found | BLOCKED | NOT_STARTED | Formulas, locking, visibility |
| Fitness | 2 | SHOULD | REQ-FIT-001 | FIT-01-FIT-03 | DEFERRED | No module/code found | BLOCKED | NOT_STARTED | Units, thresholds, visibility |
| Parent/player portals | 3 | SHOULD | REQ-PORTAL-001 | SEC-09/14, REL-01-REL-05 | BLOCKED | No portal code found | BLOCKED | NOT_STARTED | Consent, eligibility, category visibility, revocation |
| Matches/tournaments/HPP/media/advanced operations | 3 | LATER | REQ-ADV-001 | MT-01-MT-06, HP-01-HP-05 | DEFERRED | No module/code found | BLOCKED | NOT_STARTED | Multiple business decisions |

Staff dashboard UI update (2026-08-29): the authenticated landing page now uses the shared staff layout with permission-aware navigation, real branch-scoped summaries, quick actions, recent-work tables, responsive styling, and safe empty states. This is application navigation/UI, not the deferred Operational Dashboard KPI module.

Branch-management verification update (2026-09-01): `tests/database/BranchManagementSafeTest.php` passes 5 tests / 35 assertions for approved branch creation, update, active/inactive lifecycle, validation, RBAC, authentication, CSRF form rendering, canonical routes, shared staff UI, and audit events. Tests use persistent `TEST_MCC_BRANCH_WRITE_*` fixtures and never perform destructive cleanup.

Sessions/Scheduling verification update (2026-09-01): `tests/database/SchedulingSafeWorkflowTest.php` passes 6 tests / 44 assertions. Duplicate legacy Scheduling routes/controller were removed; the canonical Browser controller and shared staff layout are now the verified path. Destructive legacy Scheduling tests remain excluded.

Documents security hardening update (2026-09-04): private storage now rejects public-web-root configuration, validates an allowlisted server MIME type and size, derives a random server-side extension/key, sanitizes the display filename, rejects common executable/script markers, applies download authorization and `nosniff` attachment headers, records centralized upload audit data, and removes an uncommitted storage object if metadata persistence fails. DOC-01 through DOC-08 remain policy or infrastructure decisions; no medical visibility, verification authority, retention, encryption/key ownership, malware-scanning workflow, parent access, or replacement/retirement rule was invented.

## Trial Management

| Phase | Priority | Requirement | Decision | Status | Implementation state | Security/test readiness | Remaining evidence |
|---|---:|---|---|---|---|---|---|
| 1 | MUST | REQ-TRIAL-001 | TRIAL-01 | IMPLEMENTED | Independent Lead-linked Trial records, append-only history, approved states and transitions, manual attendance, existing Lead Status integration, branch authorization, CSRF-protected MVC routes, and focused integration tests | VERIFIED | Deployment/release evidence only |

## NFR evidence register

| NFR | Status | Evidence | Test required | Owner | Blocking decision |
|---|---|---|---|---|---|
| NFR-01 | NOT_IMPLEMENTED | Evidence not found; no load-test tooling or production-like dataset. | p95 load test with agreed dataset, concurrency, and environment. | NEEDS_DECISION | OPS-08 |
| NFR-02 | PARTIALLY_IMPLEMENTED | HTTPS filter/configuration exists; adaptive hashing, database identity, lifecycle invalidation, and production HTTPS evidence are absent. | Session rotation/invalidation and production security tests. | APPROVED DEPENDENCY; EVIDENCE REQUIRED | SEC-14 |
| NFR-03 | PARTIALLY_IMPLEMENTED | Generic auth/permission filters exist; no protected business endpoints or document downloads. | Endpoint and file authorization tests. | NEEDS_DECISION | SEC-04, DOC-01-DOC-05 |
| NFR-04 | PARTIALLY_IMPLEMENTED | Redaction helper exists; production log review and full integration coverage are absent. | Log redaction and error-response inspection. | NEEDS_DECISION | OPS-12 |
| NFR-05 | NOT_IMPLEMENTED | Evidence not found for encrypted backup or restore testing. | Quarterly restore exercise with approved RPO/RTO/retention. | NEEDS_DECISION | OPS-02 |
| NFR-06 | NOT_IMPLEMENTED | Evidence not found for release checklist or migration guidance workflow. | Release checklist audit. | APPROVED GOVERNANCE; EVIDENCE REQUIRED | GOV-01, GOV-02 |
| NFR-07 | IMPLEMENTED for Scheduling | MCC timezone `Asia/Kolkata`; scheduling/audit timestamps persist in UTC; explicit timezone input is required; presentation converts to MCC timezone. | Scheduling timezone boundary and UTC audit tests pass; report semantics remain future scope. | VERIFIED for Scheduling | OPS-09 approved |
| NFR-08 | NOT_IMPLEMENTED | No export implementation exists. | Export authorization, filtering, logging, timestamp, and failure tests. | NEEDS_DECISION | REP-01-REP-03 |

## Gate interpretation

`FOUNDATION` means technical scaffolding exists, not that the capability is secure or release-ready. `BLOCKED` means implementation must not start until its listed decisions and acceptance criteria are sufficiently defined. No business module is `READY_FOR_REVIEW` or `APPROVED_FOR_RELEASE`.

Approval of the Phase 1 decision sheet does not itself make a module implemented. After approval, each module still requires migrations, server-side authorization, validation, acceptance tests, security review, and release evidence. P2 and migration-conditional decisions remain outside the Phase 1 implementation gate.

Browser MVC update (2026-08-29): shared authenticated staff layout, dashboard navigation, and server-rendered browser workflows were added for the implemented CRM, Admissions, Players, Scheduling, and Attendance capabilities. Existing services, permissions, branch scope, CSRF, and approved business rules remain authoritative. Deployment evidence remains separate.

UI system update (2026-08-30): the existing server-rendered screens now share a consolidated Valex-targeted presentation layer with grouped permission-aware navigation, responsive shell behavior, reusable component styles, and centralized local assets. No business, authentication, authorization, route, or database behavior changed; visual browser comparison remains environment-dependent.

Attendance quality update (2026-08-29): final server-rendered UI accessibility, browser-security, database-integrity, and MVC review completed. Focused Attendance coverage is 27 tests / 133 assertions; the complete PHPUnit suite is 128 tests / 610 assertions. No deployment or production verification is claimed. Reports/exports and dashboards remain unavailable pending REP-01--REP-03 and DASH-01--DASH-03 approvals; no new module was started.

Attendance safe workflow update (2026-09-01): tests/database/AttendanceSafeWorkflowTest.php passes 5 tests / 51 assertions against mcc_cricket_club_test without destructive cleanup. The legacy AttendanceIntegrationTest.php remains excluded because it contains emptyTable() cleanup. Safe verification adds persistent unique session/attendance fixtures; production/browser evidence remains separate.

Administration MVC update (2026-08-29): user, role, approved-permission, effective-access, account enablement, and authorized branch-assignment pages are implemented under the existing `rbac.manage` boundary. Focused Admin coverage is 8 tests / 26 assertions; password credentials and hashes are excluded from views and audit summaries.

Player scope update (2026-08-31): Player browser list/detail, search/status filtering, profile editing, approved lifecycle history, and approved guardian/emergency/primary contact creation now use the canonical Browser controller and shared staff layout. Admission conversion remains owned by `AdmissionService`; branch, RBAC, optimistic-concurrency, lineage, assignment, and audit boundaries remain in force. The module is still `PARTIALLY_IMPLEMENTED` pending decision-gated relationship visibility/revocation, DAT-02 evidence, and safe end-to-end workflow coverage.

Player verification update (2026-08-31): `tests/database/PlayerBrowserSecuritySafeTest.php` passes 5 tests / 57 assertions against existing `mcc_cricket_club_test` fixtures without cleanup or fixture writes. This is CI4 feature/render evidence only; no real-browser or production verification is claimed, and conversion/lifecycle/contact write workflows remain outside this read-only run.

Player safe write verification update (2026-09-01): `tests/database/PlayerWriteWorkflowSafeTest.php` passes its non-destructive conversion, lifecycle, profile, contact, and approved batch/coach assignment coverage using unique persistent fixtures. The legacy destructive Player integration test remains excluded. Player remains `PARTIALLY_IMPLEMENTED` because REL-03-REL-05, DAT-02, and real-browser/production evidence are unresolved or unavailable.

Pending-work matrix (2026-09-01): CRM Follow-Ups — `APPROVED + IMPLEMENTABLE`, selected and safely verified under `LD-13`; Sessions/Scheduling — `IMPLEMENTED + NEEDS VERIFICATION` for deployment/release evidence; Admissions — `PARTIALLY_IMPLEMENTED`; Players/guardians and Attendance — `PARTIALLY_IMPLEMENTED`; Documents, Reports/Exports, Operational Dashboard, and Parent/Player Portals — `BLOCKED BY DECISION`; Performance, Fitness, and advanced Matches/Tournaments/HPP/Media — `DEFERRED`. No blocked or deferred module was started.

CRM Follow-Up verification update (2026-09-01): approved Follow-Up service, routes, shared MVC views, audit, branch/RBAC controls, and safe CI4 coverage are verified by `tests/database/CrmFollowUpSafeTest.php` (9 tests / 130 assertions). The test suite remains non-destructive and legacy cleanup-based CRM tests remain excluded.

CRM Lead UI and safe workflow update (2026-09-01): the canonical Lead detail route is `/crm/leads/{id}` with the legacy `/crm/leads/{id}/page` URL preserved only as a 301 redirect. Lead list/detail relationship context, preserved GET filters, permission-aware actions, server-side branch isolation, and Trial/Admission/Follow-Up links are verified by `tests/database/CrmLeadSafeWorkflowTest.php` (8 tests / 66 assertions) against persistent unique fixtures. The legacy cleanup-based CRM browser and integration suites remain excluded from execution.

Cross-module UI and route consistency update (2026-09-01): the existing staff shell remains centralized with one `main` document structure, shared footer, shared sidebar, NavigationService authority, active-group expansion, Coming Soon preservation, compact desktop mode, mobile drawer, and self-hosted Font Awesome controls. Route/link audit found no second implemented business workflow. CRM Lead `/page` and Player `/page` remain compatibility redirects; Admissions and Scheduling `/page` detail paths remain their documented canonical URLs. Safe implemented-module verification passes 88 tests / 1,035 assertions. Browser and JavaScript runtime verification remain unavailable; no database cleanup was performed.

Shared navigation update (2026-09-01): duplicate hard-coded sidebar grouping was consolidated into `NavigationService::staffGroups()` and one shared `partials/sidebar` implementation. The sidebar now supports authorized grouped navigation, automatic active-group opening, desktop compact mode, mobile drawer controls, keyboard-visible focus, ARIA state, and non-sensitive local-storage persistence. No database, business module, or permission policy changed.

Font Awesome navigation icon update (2026-09-01): Font Awesome Free 7.3.1 is self-hosted under `public/assets/vendor/fontawesome` using the official package CSS, solid CSS, required solid webfont, and license. The shared sidebar uses only `fa-solid fa-chevron-down` and `fa-solid fa-chevron-right` for group state; no navigation routes or permissions changed.
Player UI polish update (2026-09-01): the canonical list and profile now surface approved Admission/Lead/optional Trial lineage, admission state, Direct Admission/no-linked-Trial context, and clear placement/contact/status presentation. Safe Player browser/security, write-workflow, contract, and navigation coverage passes 20 tests / 182 assertions. No real-browser or deployment verification is claimed; REL-03-REL-05 and DAT-02 remain unresolved.

Equipment inventory update (2026-09-21): a user-provided direction, not client approval, authorized a limited Name/Quantity/Branch create/list slice. Explicit routes, branch-scoped persistence, `equipment.view/create`, live assignment validation, Branch Admin active-branch context, and shared navigation were implemented. No tests were run for this update. Equipment edit/delete, assignments, maintenance, availability, categories, and Grounds/scheduling relationships remain `NEEDS_DECISION`; the replacement requirements baseline remains unapproved.
