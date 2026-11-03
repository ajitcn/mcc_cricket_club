# Cross-Module Reference Flow Evidence Review

**Review date:** 2026-10-11  
**Mode:** Read-only source, route, test, and completion-report review  

No application code, migrations, permissions, database records, routes,
business rules, or `docs/SRS-original.md` were changed.

## Evidence method

The Match ↔ Tournament ownership, result source-of-truth, and standings policy
decisions remain `NEEDS_DECISION`; bounded implementation evidence does not
constitute formal client approval.

The review traced route definitions, controller entry points, service/model
relationships, authorization calls, POST/CSRF boundaries, transaction and audit
calls, not-found handling, date/time helpers, focused tests, and available
completion reports. Existing user-provided directions are evidence of intended
scope only and are not client approval.

## Reconciled Match ↔ Tournament bounded slice

The bounded linkage slice is classified **Verified** for implementation
evidence, while remaining explicitly **USER-PROVIDED DIRECTION — NOT CLIENT
APPROVAL**:

- Fixture/Match linkage is optional and one-to-one.
- Fixture owns scheduling and status; Match owns the verified result and
  completion state.
- Only completed verified Matches linked to completed Fixtures feed standings.
- Branch validation, existing permissions, CSRF, transactions, audit metadata,
  and optimistic concurrency are preserved.
- Migration `20261011000300_AddMatchTournamentLinkageVersion` is forward-only
  and idempotent.
- PHP lint passed and the bounded verification passed: **55 tests, 523
  assertions, exit code 0**.
- The legacy destructive Tournament integration suite remains excluded.

## Match Player Performance correction/version completion reconciliation

The bounded Match Player Performance correction/version evidence is now
separately counted and is recorded in
`docs/MATCH-PLAYER-PERFORMANCE-CORRECTION-COMPLETION-REPORT.md`:

- Unit: 13 tests, 179 assertions, exit code 0.
- Feature: 6 tests, 59 assertions, exit code 0.
- Database: 4 tests, 38 assertions, exit code 0.
- Total: **23 tests, 276 assertions, exit code 0**.
- No tests were skipped.
- PHP lint passed for the relevant service, controller, view, and test files.
- Database: `mcc_cricket_club_test`; migration
  `20261011000400_AddMatchPlayerPerformanceVersion` applied as batch 56.

Batting, bowling, fielding, membership, branch validation, permissions, CSRF,
completed-Match restrictions, correction reasons, idempotent writes, audit
metadata, rollback, and bounded sensitive-data handling are verified. One
shared per-record version is used across batting, bowling, and fielding;
successful corrections increment it atomically, and stale writes are rejected
with reload messaging without overwriting current data. Negative cross-branch
write coverage also passes.

## Flow classification

| Flow | Entry point and relationship | Security and persistence evidence | Time/error evidence | Classification |
| --- | --- | --- | --- | --- |
| Lead/CRM → Trial | `crm/leads/{id}`, `crm/trials/from-lead/{id}` and Trial routes enter CRM/Trials Browser; `CrmService` and `TrialService` link `leads`, `trials`, branch and assigned Coach/Ground | `lead.view/create/update`, `trial.book`; services recheck authorized branch and Coach/Ground; trial booking uses a transaction, row locking, Lead status update and audit; POST forms use existing CSRF filter | Trial date/time are validated through TrialService; invalid/cross-branch records return safe errors; Lead create/edit/follow-up failures use explicit CRM targets; the Lead/Trial/Admission completion report records 26 tests and 365 assertions, and the CRM redirect report records 2 tests and 14 assertions | **Verified** |
| Trial → Admission | `admissions/from-lead/{lead}` and Admission Browser call `AdmissionService::createFromTrialPending`; Admission detail projects the linked Trial | Branch equality is checked for Lead, Trial and Admission; create/complete/convert operations are transactional and audited; `admission.create/update` and `player.convert` remain server-side | Trial date/time and Admission dates use MCC helpers and existing UTC conventions; safe not-found/authorization handling is present | **Verified** |
| Admission → Player conversion | `admissions/{id}/convert` enters Admissions Browser and `AdmissionService::convert` creates or resolves the Player | `player.convert` plus Admission branch authorization; conversion uses transaction/locking, preserves checklist and audit history, and is idempotent for retries | Successful conversion and explicit exception redirects are verified by the Admission redirect completion report; conversion failures return to `/admissions/{id}/page` | **Verified** |
| Scheduling/Sessions → Attendance | Sessions routes enter Scheduling Browser; Attendance routes enter Attendance Browser; `SchedulingService` owns Session/Player roster and `AttendanceService` owns snapshot and attendance rows | Session/attendance permissions, branch checks, immutable snapshots, transactions, version checks, audit history and CSRF-protected POST routes are present | Separate MCC-local date/time inputs convert to UTC; stale attendance versions are rejected; missing/cross-branch roster records return safe errors; Group Coaching and Skill reports provide focused evidence | **Verified** |
| Attendance → Coach feedback | Session Details and Attendance pages expose feedback actions; Coach Browser and `CoachManagementService::saveFeedback` link Coach, Session, Player, attendance and branch | Existing Coach/session update permissions, snapshot membership, attendance-first validation, correction reason and audit metadata are enforced server-side | Explicit return targets preserve the originating page; validation values/errors are retained; no completion-status requirement remains; no single completion report covers both pages | **Verified** |
| Sessions → Documents/media | Existing Documents routes and `DocumentManagementService` provide private upload/versioning, preview and download; Admission integration is present | `document.view/upload/download`, private storage, safe filenames, scanner/quarantine, branch checks, CSRF and audit are present; no Session-to-document relationship route is evidenced | Expiry/retired/quarantine states return generic not-found or unavailable responses; media linkage, visibility and retention remain policy-gated | **NEEDS_DECISION** |
| Matches → Match Player Performance | Match routes enter Matches Browser; `MatchManagementService` projects Match/Players and `MatchPlayerPerformanceService` saves per-Player batting, bowling and fielding | `match.view/update` and completed-match service authorization; Match/Player/branch membership, CSRF, correction reason, transactions, idempotent per-Player writes, shared version checks, audit, rollback, and escaped output are evidenced | Migration `20261011000400_AddMatchPlayerPerformanceVersion` is applied in the test database; bounded result is 23 tests and 276 assertions; generic PerformanceService relationship remains separate | **Verified for bounded correction/version slice** |
| Tournaments → Fixtures/Standings | Tournament routes enter Tournaments Browser; `TournamentManagementService` links Tournament, branch, Ground, Players, Fixtures, Matches and read-time standings | `tournament.view/update`; branch matching, fixture validation, transaction/audit, status actions, correction reasons, and optimistic version checks are evidenced | Bounded linkage uses configured MCC timezone and includes only completed Fixtures linked to completed verified Matches; broader formulas remain `NEEDS_DECISION` | **Verified for bounded linkage slice** |
| Achievements catalog → draft awards → lifecycle | Achievements routes enter Achievements Browser; `AchievementManagementService` links catalog, awards, Player/Coach recipients, branch and audit; approval/publication/revoke/correct routes exist | Centralized achievement permissions, exact-one recipient, active same-branch validation, draft correction reasons, status/version checks and audit are evidenced; certificate/report routes also exist | Published/revoked handling and safe detail responses exist; Phase 2/3 directions remain user-provided and not client-approved | **NEEDS_DECISION** |
| Achievements → Parent/Student portal | Portal routes `student-achievements` and `parent-achievements` enter Portal Browser; `PortalAuthService::publishedAchievements` joins published awards to the authenticated Player or guardian relationship | Portal session separation, OTP authentication, Player/guardian relationship and branch joins are present; staff permissions are not reused | Portal output omits drafts/revoked/internal metadata; broader portal visibility and consent policy remains unresolved | **NEEDS_DECISION** |
| Documents → Admission/Player records | Admission document POST routes and Player/document integrations call the private Document service | Branch-scoped document permissions, private storage, controlled preview/download, version retirement, scanner state and audit are present; no public URLs or raw metadata | Generic unauthorized/not-found behavior and expiry/retired rules are preserved; metadata/browser report records 12 tests and 97 assertions | **Verified** |
| Parent/Student portal session flow | `student-login`, portal logout, student/parent dashboards and achievement routes use the `portal` filter; `PortalAuthService` handles OTP and linked Player/guardian scope | Portal sessions are separate from staff sessions; relationship and branch checks are server-side; OTP events are audited | Dashboard/session projections use existing UTC/date helpers; full document, feedback, attendance and sensitive-field visibility is not approved | **Verified** |

## Reconciled classification register

The statuses above reconcile the source review with
`CLIENT-REFERENCE-REMAINING-WORK-AUDIT.md`. `Verified` means the bounded link
has source evidence and focused completion evidence. `Partially verified` means
the bounded link exists but the cross-module evidence package or policy is
incomplete. Match/Tournament gaps below remain classified rather than silently
promoted to verified behavior.

## Match and Tournament linkage classification register

| Linkage | Classification | Evidence, gap, or blocker |
| --- | --- | --- |
| Match -> Branch | Verified | Branch-scoped Match service queries, authorization, transactions, audit, and existing Match completion evidence. |
| Match -> Ground/Opponent/Coach | Verified | Branch-owned references, active/reference validation, scheduling conflict checks, and focused Match evidence. |
| Match -> Player squad/Playing XI | Partially verified | Squad/XI persistence and branch validation are tested; registration, approval, locking, and correction policy remain undecided. |
| Match -> Match Player Performance | Verified for bounded correction/version slice | Batting, bowling, fielding, membership, correction reason, shared per-record versioning, atomic increments, stale rejection, transaction rollback, idempotency, audit, permissions, CSRF, branch isolation, and escaped output are covered by 23 tests and 276 assertions. |
| Match -> Tournament Fixture | Verified for bounded linkage slice | Optional one-to-one Fixture/Match linkage is enforced; Fixture owns scheduling/status and Match owns verified result/completion. Formal client approval remains absent. |
| Tournament -> Fixture | Verified | Explicit Fixture routes, statuses, branch/permission checks, CSRF, transactions, audit, and insert-only safe tests exist. |
| Fixture -> Ground/Player | Partially verified | Branch and active-state checks exist; participant history and complete schedule-boundary policy are not established. |
| Fixture -> Standings | Verified for bounded linkage slice | Only completed Fixtures linked to completed, verified Matches feed standings; broader formulas, tiebreakers, and exceptional outcomes remain open. |
| Fixture local time -> UTC/MCC timezone | Verified for bounded linkage slice | Fixture conversion uses the configured MCC application timezone and preserves UTC storage. |
| Match/Tournament migration deployment | Deployment blocker | `20261011000300_AddMatchTournamentLinkageVersion` is verified in the test database; production migration approval and deployment evidence remain required. |
| Match scorecard/innings -> Performance/standings | Missing evidence | No scorecard or innings workflow is evidenced. Do not infer automatic qualification or standings formulas. |

Preserved scope: no scorecards or innings, no automatic Tournament
qualification, no unapproved standings formulas, no registration or selection
policy changes, and no exports, notifications, or new permissions.

### Resolved defect

| Defect | Exact route/controller/service | Module | Security and branch impact | Migration impact | Required tests | Existing evidence | Fix without new policy? |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Admission browser failures could fall through to the previous page | `POST /admissions/from-lead/{lead}` → `App\Modules\Admissions\Controllers\Browser::create()`; `POST /admissions/{id}/checklist` → `Browser::checklist()`; `POST /admissions/{id}/convert` → `Browser::convert()` | Admissions/CRM | Service authorization, branch checks, CSRF and transactions remain enforced; explicit targets now preserve the correct Admission context | None | Unit redirect contracts; Feature validation/permission/branch/CSRF redirects; safe rollback and idempotent conversion tests | `docs/ADMISSION-REDIRECT-COMPLETION-REPORT.md`; 22 tests, 311 assertions, exit code 0. Create returns to `/admissions/new`; checklist and conversion return to `/admissions/{id}/page`; flash errors and submitted input are preserved where applicable | **Resolved without new policy** |
| CRM browser failures could fall through to the previous page | `POST /crm/leads`, `/crm/leads/{id}/edit`, lead actions and follow-up actions → `App\Modules\CRM\Controllers\Browser` | CRM | Existing service authorization, branch checks, CSRF, transactions and audit remain enforced; explicit targets preserve the owning Lead context | None | Unit explicit-target contract; Feature route/target contract; existing CRM/Trial branch, permission and success suites | `docs/CRM-REDIRECT-COMPLETION-REPORT.md`; 2 tests, 14 assertions, exit code 0. Lead create uses `/crm/leads/new`; edit uses `/crm/leads/{id}/edit`; lead/follow-up failures use `/crm/leads/{id}`; Trial failure paths remain explicit | **Resolved without new policy** |

### Missing evidence or partial verification

| Flow | Exact route/controller/service | Affected module | Security and branch-scope impact | Migration impact | Required tests | Existing completion evidence | Fix without new policy? |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Attendance → Coach feedback evidence | Session detail/attendance views → Coach Browser → `CoachManagementService::saveFeedback()` | Scheduling/Attendance/Coaches | Existing attendance-first rule, snapshot membership, branch and update permissions must remain | None expected | Unit eligibility; Feature scheduled/active/completed, missing attendance, branch/permission/CSRF; safe audit/idempotency tests | Existing workflow direction and route code; no consolidated completion report | Yes, verification/tests only |
| Matches → Performance evidence | `GET/POST /matches/{id}/performance` → Matches Browser → `MatchManagementService`, `MatchPlayerPerformanceService` | Matches/Performance | Match/Player/branch membership, `match.*`, `performance.update`, correction reasons, CSRF, transactions, shared version checks, idempotency, audit, rollback, and escaped output remain authoritative | `20261011000400_AddMatchPlayerPerformanceVersion` applied forward-only as batch 56 | Unit, Feature, and insert-only safe tests separately pass: 23 tests, 276 assertions | Correction/version slice verified; broader PerformanceService relationship and governance remain separate | Yes, deployment evidence only |
| Tournaments → Fixtures/Standings evidence | `/tournaments/{id}` and `/tournaments/{id}/fixtures/*` → Tournaments Browser → `TournamentManagementService` | Tournaments/Matches | `tournament.view/update`, branch/team/Ground checks and fixture lifecycle rules remain authoritative | None expected | Unit standings/fixture validation; Feature route/branch/CSRF; safe fixture/audit/idempotency tests | Tournament fixture/standings work is documented; no consolidated report | Yes, verification/tests only |
| Documents → Admission/Player evidence | `/admissions/{id}/documents`, `/documents/{id}/preview`, `/documents/{id}/download` → Admissions/Documents Browser → `AdmissionService`, `DocumentManagementService` | Admissions/Documents | Private storage, `document.*`, branch authorization, safe filenames, expiry/retired/scanner rules remain unchanged | None expected | Unit private metadata; Feature generic 404/branch/permission/CSRF; safe version/audit tests | Documents browser report: 12 tests, 97 assertions; Admission reports cover integration | Yes, verification/tests only |
| Parent/Student portal coverage | `/student-login`, `/student-dashboard`, `/parent-dashboard`, achievement routes → Portal Browser → `PortalAuthService` | Portal/Achievements | Portal session separation and relationship/branch checks remain required; sensitive visibility is not inferred | Unknown until portal policy | Unit relationship projection; Feature OTP/session/branch/privacy; safe portal scope tests | PortalAuthService and Achievement portal routes exist; visibility policy remains unresolved | No, policy decisions required for broader data |

### Needs-decision and deployment classifications

The following remain `NEEDS_DECISION`: Session-to-document media linkage; Trial
mode/scoring/media; Admission meeting, fees, hostel, ratings, cricket ability,
signatures/consent, medical/certificate requirements; Achievement lifecycle and
certificate/report/portal extensions; portal document/feedback visibility;
notifications; retention/archival; and unresolved Dashboard formulas. The
existing implementation evidence does not authorize these workflows.

Deployment blockers remain: normal PowerShell Composer/bootstrap readability;
private storage, scanner/quarantine, encryption, backup and retention
configuration; external payment/notification providers; and production
migration approval. These require deployment-owner evidence and no application
workaround.

## Previous recommended next action (superseded)

**Create the bounded Attendance → Coach feedback completion-evidence record.**

Trace the existing Session Details and Attendance feedback entry points,
attendance-first rule, snapshot membership, branch and permission checks, CSRF,
audit, return targets and focused tests. Record the evidence without changing
workflows, migrations, permissions or data. This is documentation/test evidence
only and requires no new policy decision.

## Recommended next action

**Obtain deployment-owner approval and production-readiness evidence for the
forward-only Match Player Performance version migration.**

This is the single safe backlog action. It must not change Match ↔ Tournament
ownership or standings decisions, and it does not authorize scorecards, innings,
qualification, registration, selection governance, exports, notifications, or
new permissions.

## Cross-cutting checks

### Route and controller coverage

The route groups use explicit controller targets. State-changing operations are
POST routes protected by the existing auth/permission filters and application
CSRF configuration. The main gaps are missing evidence packages, not missing
route declarations, except for Session-to-document media linkage, which has no
approved route or relationship.

### Branch authorization and permissions

Services consistently resolve authorized branches through `BranchContextService`
or the centralized authorization service and recheck the persisted record
branch. The reviewed flows use existing permissions such as `lead.*`,
`trial.book`, `admission.*`, `player.convert`, `session.*`, `attendance.*`,
`coach.*`, `document.*`, `match.*`, `performance.update`, `tournament.*`,
`achievement.*`, and portal relationship filters. Menu visibility is not relied
upon as authorization.

### CSRF, transactions, audit, and idempotency

The Admission, Trial, Scheduling, Attendance, Documents, Match, Tournament and
Achievement services contain transaction/audit paths for their bounded writes.
Attendance versions, Match Performance per-Player saves, Admission conversion,
Trial booking, document versioning and Achievement lifecycle writes have explicit
idempotency or optimistic-concurrency protections. Feedback and some legacy CRM
redirect paths have less consolidated evidence and remain partially verified.

### Timezone and error handling

MCC-local date/time inputs are converted in services and persisted using the
existing UTC convention for Sessions, Trials, Matches and Tournament fixtures.
Document and detail routes use generic `Not found.` responses for unauthorized,
missing, retired or unavailable records. Cross-flow evidence does not prove every
legacy redirect and validation path, so those paths remain partially verified.

## Missing references and inconsistencies

- No approved Session-to-document/media relationship is present.
- Trial mode/scoring/media, Admission meeting/financial/hostel fields, and
  portal document/feedback visibility are not defined by approved policy.
- Achievement lifecycle, certificates, reports, notifications, retention and
  automatic eligibility contain user-provided direction but lack client
  approval fields.
- Completion reports are module-scoped; there is no shared end-to-end fixture
  proving a single Lead through Trial, Admission, Player, Session, Attendance,
  Match/Tournament and portal projection.
- The Admission browser exception redirect defect is resolved and evidenced in
  `docs/ADMISSION-REDIRECT-COMPLETION-REPORT.md`.
- The CRM browser exception redirect defect is resolved and evidenced in
  `docs/CRM-REDIRECT-COMPLETION-REPORT.md`.

## Excluded scope

Fees, hostel, ratings, cricket ability, signatures, consent, medical data,
certificate requirements, guardian visibility changes, trial/session media,
notifications, retention/archival and financial workflows remain excluded
unless explicit client decisions are recorded. No unsupported data or policy was
inferred during this review.

## Deployment blockers

- Normal PowerShell must verify `.env`, Composer autoload, and the CodeIgniter
  PHPUnit bootstrap readability before executable migration/test verification.
- Private storage, scanner/quarantine, encryption, backup, retention and any
  payment/notification provider require deployment-owner configuration evidence.
- Production migrations and database writes remain outside this read-only review.
