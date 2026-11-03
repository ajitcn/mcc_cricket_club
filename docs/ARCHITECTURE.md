# MCC Architecture Record

This document records implemented architecture and scoped user-provided directions. A user-provided implementation direction is not represented as client approval of the missing replacement requirements baseline.

## Trial media upload (2026-10-18)

Trial media is implemented as a focused extension of the existing Trial workflow. `TrialMediaService` owns MIME/extension/size/upload-error validation, branch authorization, private storage, transactional metadata persistence, cleanup on database failure, controlled reads, and audit events. `Trials\\Controllers\\Browser` remains thin and exposes explicit CRM routes for upload, preview, and download. The existing `LocalPrivateStorage` abstraction is reused; no public path, browser-only storage URL, new permission, or payment/match/tournament workflow is introduced.

## Programs and Batches — limited user direction

**Source/status:** `PROG-BATCH-01`, user-provided direction dated 2026-09-22; not client approval.

- Programs and Batches use explicit CodeIgniter module routes under `app/Modules/Programs` and `app/Modules/Batches`.
- `ProgramManagementService` and `BatchManagementService` own authorization, validation, branch resolution, persistence, and audit transactions; controllers remain thin.
- Programs are currently global reference records because the direction defines only global Program metadata (`name` and optional `description`); Program branch ownership/sharing remains `NEEDS_DECISION`.
- Batches are branch-owned under approved `BR-04`. Super Admin selects a live authorized branch. Branch Admin resolves the validated active branch from `BranchContextService`; submitted or hidden branch values are not authoritative.
- Existing `app/Modules/Players/Models/BatchModel.php` is reused and its unsupported `capacity` allowlist entry is removed. No duplicate Batch table or assignment workflow is introduced.
- Only create/list behavior is implemented. Edit/delete/archive, capacity, scheduling, coaches, recurrence, attendance, fees, and dependent-record policies remain `NEEDS_DECISION`.

## Equipment inventory and active branch context

**Scope source:** `EQUIP-01` in `docs/DECISIONS.md`, a user-provided direction dated 2026-09-21. It authorizes only Equipment inventory creation, listing, editing, and soft deletion.

- Equipment business routes, controller, model, and views live under `app/Modules/Equipment` and use explicit CodeIgniter routes.
- Inventory workflows live in `app/Services/EquipmentService.php`; centralized authorization remains in `AuthorizationService`.
- `app/Services/BranchContextService.php` resolves active branch context from live `user_branches` and `branches` rows for the current session user. It does not rewrite the shared `authorizedBranches` session value, so other modules keep their existing branch-context behavior.
- A sole active authorized branch is selected automatically for Branch Admin. Multiple active assignments require a selection through the shared topbar form. A CSRF-protected authenticated POST validates the submitted selection against current database assignments before storing `activeBranchId`. A fingerprint of the current active assignments invalidates a stored selection after the assignment set changes; the user must select again when multiple active branches remain.
- Every Equipment operation re-reads assignments and validates the active context. A stale active-branch value is cleared. The Branch Admin Equipment create service derives branch from that context and ignores request `branch_id`. Super Admin branch input is accepted only after live assignment, active-branch, and centralized permission checks.
- Edit and soft-delete operations use separate `equipment.update` and `equipment.delete` permissions. Services load the target record only within the actor's current authorized branch scope. Editing changes Name and Quantity only; Branch is never accepted as an editable field. Deletion sets `deleted_at`, excludes the row from active lists, and writes its audit event in the same transaction. No hard-delete or restore route exists.
- Users assigned both mapped roles are denied Equipment access while role precedence remains `NEEDS_DECISION`.
- Existing non-Equipment services do not read `activeBranchId`; this implementation does not change their data-access behavior.
- The context selector is presentation only. Server-side assignment and permission checks remain authoritative.

## Deferred design

Equipment detail, a user-facing restore workflow, hard deletion, categories, assignments, maintenance, availability, reports, and relationships with Grounds, Sessions, or scheduling remain `NEEDS_DECISION`. Do not infer these from Grounds or `SCH-02`/`SCH-03`.
## Coach and Attendance scoped user direction (2026-09-25; not client approval)

The existing Coach and Attendance modules remain the implementation boundary. Coach profile updates reuse `CoachManagementService` and `coach_profiles`; branch and session relationships resolve through `user_branches`, `sessions`, and existing player/session assignment records. Attendance status reconciliation uses a forward-only migration and the existing `AttendanceService`, `attendance`, and `attendance_history` tables. Derived percentages, KPI queries, and report-ready alerts are read-time projections over authorized branch/session/roster data; no external notification integration is introduced. All service queries must revalidate branch authorization server-side and preserve existing audit/history behavior.

Coach Management scoped user direction (2026-09-25; not client approval): branch and session assignment changes remain explicit service workflows using existing `user_branches`, `sessions`, scheduling validation, centralized permissions, transactions, and append-only audit events. Leave and availability writes reuse `coach_unavailability` with retained status/history and overlap validation. Feedback ratings are a branch-scoped source record linked to a completed session and Player. The performance index is a read-time projection over approved `performance_records`; no manual KPI totals or external delivery are introduced. Unspecified correction windows, role precedence, retention, and source-data policy remain `NEEDS_DECISION`.

Coach availability policy direction (2026-09-25; user-provided, not client approval): `CoachManagementService` owns availability CRUD, branch authorization, interval validation, overlap checks, and audit writes. `coach_availability` stores either a recurring weekday or one-time date interval and retains cancelled rows. `SchedulingService` rechecks approved leave first, then applies one-time availability overrides or recurring availability in the configured application timezone. Controllers remain thin and Coach views use shared forms and tables. No automatic session or attendance mutation occurs.

Coach leave approval direction (2026-09-25; user-provided, not client approval): `CoachManagementService` owns the leave state machine (`PENDING`, `APPROVED`, `REJECTED`, `CANCELLED`), reason validation, branch authorization, overlap checks, and transition audit events. Coach routes expose explicit approval, rejection, and cancellation actions; cancellation permits a Coach to cancel only their own pending row and requires `coach.update` for manager actions. `SchedulingService` continues to query only approved leave, so historical attendance is untouched.


Performance Improvement Index direction (PII-v1; user-provided, not client approval): `PerformanceService::coachImprovementIndex()` computes a read-time projection from approved `performance_records`, scoped to the Coach's authorized branches and assigned players/sessions. It orders source rows by `recorded_on` and ID, compares earliest/latest values per Player, skill, and metric, ignores invalid comparisons, and returns null when no valid comparison exists. `CoachManagementService` exposes the result only through the existing `coach.kpi.view` branch gate. No manual KPI total or public result is stored.

## Documents secure-default direction (2026-09-27; user-provided direction — not client approval)

The Documents boundary remains `DocumentManagementService` plus `PrivateStorageInterface`/`LocalPrivateStorage`, with thin controllers, explicit routes, server-side validation, centralized authorization, branch revalidation, CSRF-protected browser writes, and append-only audit/history. The following direction is recorded for later approval and implementation planning:

1. Store sensitive documents privately and expose them only through authorized request-time download/preview paths with generic not-found responses.
2. Validate MIME, extension, size, upload errors, and generated filenames server-side.
3. Use `PENDING`, `VERIFIED`, and `REJECTED` verification states; branch-authorized staff alone may verify.
4. Exclude expired documents from normal reads and downloads.
5. Apply an explicitly authorized role/type policy to medical documents and approved relationship/branch rules to Parent/Guardian and Player visibility.
6. Treat replacement as a new version and retire the prior version; retired records remain auditable and are not downloadable.
7. Gate verification/download on malware scanning and quarantine failed scans.
8. Retain audit history without logging file contents or credentials.
9. Require deployment-owner evidence for scanner, private-storage, retention, and encryption settings.

This direction does not select a scanner, provider, permission matrix, expiry boundary, retention period, portal workflow, or schema. Those details remain `NEEDS_DECISION`, and no architecture or deployment behavior is authorized to change until reconciled.

Implementation evidence (2026-09-27; user-provided direction — not client approval): the first foundation slice adds a forward-only document-policy migration, a service verification state transition with append-only history/audit metadata, validated application-timezone expiry checks, immutable replacement rows linked by `previous_version_id`, medical type gating, and a fail-closed quarantine status when no scanner is configured. Controllers remain thin and use existing authenticated/CSRF/permission filters. No deployment scanner, encryption provider, retention job, portal route, or broad permission grant is introduced.

## Portal authentication boundary (user-provided direction — not client approval)

Portal authentication is isolated from staff authentication. `PortalAuthService` validates a secure `portal_accounts` password, account type, enabled state, lockout state, and active Player or guardian relationship before creating a portal-only session. Portal routes use the `portal` filter and never populate staff authorization context. Student and Parent dashboard queries derive Player IDs server-side and do not trust browser-supplied IDs. Staff routes continue to use the existing `auth` and permission filters.

## Portal mobile OTP boundary (user-provided direction — not client approval)

`PortalAuthService` uses a normalized verified mobile number and a hashed, expiring, single-use WhatsApp OTP challenge. Student identity resolves to an active Player; Parent identity resolves through active guardian `player_contacts` relationships. Portal routes remain isolated by the `portal` filter and never populate staff authorization context. Provider credentials are environment-only and missing configuration fails safely.

Portal development OTP display direction (2026-10-02; user-provided direction — not client approval): `PortalOtpDebugService` gates the temporary display using all three server-side checks: development/testing environment, exact `mcc_cricket_club_test` database name, and `PORTAL_OTP_DEBUG=true`. The OTP is passed only to the immediate login view response after provider success. It is not placed in a session, URL, log, cookie, API response, or staff page. This does not change OTP expiry, hashing, attempt, cooldown, single-use, or provider behavior.
### Tournament fixture and standings slice — user-provided direction

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL (2026-10-03).** Fixture routes remain explicit under the Tournament module. Controllers only parse requests and delegate to `TournamentManagementService`; the service owns branch authorization, team/Ground/Match validation, transactions, status workflows, and audit writes. Standings are derived at read time from completed fixtures and existing completed Match result values. No scorecard, innings, registration, selection, notification, or export service is introduced.

## Match Player Performance direction (2026-10-03)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** A new Match Player Performance service and forward-only source table support branch-scoped batting and bowling records for selected Match Players. The service validates Match membership, numeric cricket fields, status-dependent totals, boundary totals, bowling limits, correction reasons, and permissions. It exposes a dedicated Match performance page and keeps completed Match corrections behind existing `performance.update` authorization. Scorecard and innings policy beyond the explicit record grouping fields remains NEEDS_DECISION.

## Match Player Fielding Details direction (2026-10-03)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** Fielding fields are stored alongside the existing Match Player Performance row using a forward-only migration. The current one-record-per-Match/Player/innings/team grouping is retained; no separate event table is introduced. `Did not field` rejects positive fielding totals, and zero or missing records remain visibly unavailable until saved.

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

## Opponent catalog direction (2026-10-05)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** Opponents are managed as a small branch-scoped catalog service and selected by ID in Match workflows. Controllers remain thin and branch authorization is enforced in the service.
## Achievements implementation gate (2026-10-06)

Achievements has no client-approved architecture. The questionnaire contains no completed client approval fields, so all broader Achievement areas remain `NEEDS_DECISION`. Only the bounded catalog foundation is recorded as user direction; recipient, award, approval, certificate, portal, reporting, notification, and retention workflows remain gated. Future work must use the existing module architecture, thin controllers, services for workflows, centralized RBAC, server-side branch isolation, transactions for multi-record writes, and append-only audit logging.

The gated scope and dependencies are listed in `docs/ACHIEVEMENTS-IMPLEMENTATION-MATRIX.md`.

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL (2026-10-06):** Future Achievements work should use centralized RBAC, branch-scoped services, thin controllers, transactions, CSRF-protected forms, and append-only audit history. This is a secure architectural default only; no module may be created until decisions are approved.

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL (2026-10-06):** The bounded foundation uses `app/Modules/Achievements`, explicit routes, a thin Browser controller, `AchievementManagementService`, `AchievementModel`, shared staff views, centralized authorization, and audit persistence. It intentionally has no recipient or award workflow.

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL (2026-10-06):** The selected slice extends the module with a draft award service and recipient resolver for active same-branch Players or Coaches. Controllers remain thin; writes remain transactional; draft edits remain reasoned and audited. No approval, publication, portal, certificate, reporting, notification, retention, or automatic eligibility service is authorized.
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
## Phase 3 secure-default direction — USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL

Secure defaults were recorded for A3-01 Certificates, A3-02 Parent/Student portal visibility, A3-03 Reports and exports, A3-04 Notifications, A3-05 Retention and archival, and A3-06 Automatic eligibility. Each remains NEEDS_DECISION because no client approver, approval date, or effective date is provided. No code, route, permission, migration, table, menu, or test is authorized.
## A3-01 certificate implementation evidence — USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL

A bounded certificate slice is implemented for published Achievement awards only. It uses private LocalPrivateStorage, safe deterministic generated filenames, branch-scoped generate/download permissions, idempotent award-to-certificate linkage, and append-only generation audit events. No certificate portal visibility, replacement, retirement, or public URL workflow is introduced.

A3-02 portal visibility, A3-03 reports/exports, A3-04 notifications, A3-05 retention/archival, and A3-06 automatic eligibility remain NEEDS_DECISION and are not implemented.

### Achievement A3-03 implementation record (2026-10-06)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** Published Achievement reports are implemented as a read-only service-backed route with existing report authorization. CSV export keeps the same validated filters and records a non-sensitive export audit event. No new permission or schema is introduced.
### Achievement schema drift reconciliation (2026-10-07)

Schema repair is isolated in one forward-only migration after the canonical archival migration. The migration uses live metadata inspection and CodeIgniter Forge index processing, so it adds only missing archival indexes and foreign keys. It does not repair drift by rewriting migration history or by creating alternate tables. Repeated execution is a no-op after the canonical indexes and relationships exist.

### Achievement A3-06 eligibility architecture (2026-10-07)

`AchievementEligibilityService` owns source validation, category rule evaluation, branch/active-recipient checks, duplicate draft prevention, retries, transactions, and audit events. Controllers expose only explicit permission-filtered evaluate and retry routes. Automatic evaluation cannot call approval or publication workflows.


## Director Dashboard secure-default direction (2026-10-11)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** DASH-01 through DASH-20 use branch-scoped access with an explicit authorized “All Branches” option for Super Admin only; MCC-local date/month inputs with UTC conversion for stored timestamps; active, authorized source records; no sensitive medical, document, guardian, or private contact data; no invented KPI or performance formulas outside approved existing metrics; clear safe empty states; no caching until freshness and invalidation rules are defined; existing centralized permissions and branch authorization; exports inheriting dashboard filters and authorization; and audit limited to sensitive administrative actions without private data. Exact numeric formulas remain unresolved and every DASH item remains NEEDS_DECISION. No implementation, permission grant, migration, cache, export, or UI change is authorized by this direction.

## Individual Session Skill taxonomy direction (2026-10-11)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** The SKILL-01 through SKILL-13 secure defaults do not authorize a new skill architecture. Keep Scheduling, Attendance, Performance, Development, Documents, portal, RBAC, and audit boundaries unchanged until each item is explicitly approved.
## Match-Tournament ownership architecture direction (2026-10-11)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** The planning relationship is
Tournament -> Fixture -> optional Match. Standalone Matches remain valid. A
Fixture owns Tournament scheduling, round, date/time, Ground, and Fixture
status; the linked Match owns cricket result and completion details. A Fixture
may link to one Match, and a Match may link to only one Tournament Fixture.

Verified completed Match results are planned as the source for winner, tie,
no-result, Match completion, and Tournament standings points. Scheduled,
postponed, cancelled, and incomplete Fixtures contribute no points. Corrections
are planned as authorized, reasoned, version-checked, transactional, and
audited; ordinary editing does not change completed results or awarded points.

All referenced records remain same-authorized-branch relationships. No implicit
cross-branch link, new aggregate, scorecard, innings, automatic qualification,
or selection-policy architecture is approved. Every item remains
`NEEDS_DECISION`; approver, approval date, and effective date are blank.
## Admission form extension

USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL. Admission controllers remain
thin; `AdmissionService` normalizes and validates the extended intake payload,
the Admission model owns persistence, and shared views render the field groups.
No separate payment, hostel, or signature subsystem is introduced.

## Admission Follow-ups module (2026-10-16)

Admission follow-up workflows live in `AdmissionFollowUpService` and the
Admissions module `FollowUps` controller. The service derives `branch_id` from
the authorized Admission, rechecks permissions for every read/write, validates
controlled options and date/time values, and owns transaction and audit
behavior. List and detail queries include the Admission/branch relationship in
their branch scope, and Admission detail obtains newest-first follow-up history
through the service. The existing CRM Lead follow-up implementation is reused
as a convention only; its Lead-owned table is not reused.
