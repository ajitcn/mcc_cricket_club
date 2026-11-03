# Tournament Implementation Matrix

**Date:** 2026-10-03  
**Scope:** Evidence-based T-01–T-20 implementation gate  
**Status:** Planning matrix only. No unresolved Tournament policy is authorized by this document.

## Decision gate

`docs/TOURNAMENTS-IMPLEMENTATION-PLAN.md` and `docs/TOURNAMENTS-REQUIREMENTS-APPROVAL-REQUEST.md` record T-05 through T-20 as `NEEDS_DECISION`. T-01 through T-04 are recorded below as `USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL` for the bounded implementation only. Existing CRUD code is implementation evidence and does not approve broader competition workflows.

No secure default was applied as a business rule because the unresolved items require client choices about scope, authority, formulas, visibility, retention, and external delivery. Each blocked row below identifies the smallest evidence and test work that can begin after an explicit approval.

## Existing implementation baseline

| Capability | Evidence | Current status |
| --- | --- | --- |
| List, create, edit, and detail | `app/Modules/Tournaments/Controllers/Browser.php`, `TournamentManagementService.php`, `Views/index.php`, `form.php`, and `show.php` | Implemented CRUD baseline; focused Unit/Feature coverage exists |
| Branch-scoped viewing and writes | `tournament.view`, `tournament.create`, and `tournament.update` route filters plus service branch authorization | Implemented baseline; broader shared/multi-branch policy is blocked by T-03/T-17 |
| Participant rows | `tournament_participants` stores active branch Players and free-text teams | Partially implemented; team/squad policy is blocked by T-05/T-06/T-08 |
| Match-linked fixtures | `tournament_fixtures`, optional Match/Ground links, transactional fixture service | Partially implemented; full scheduling and progression policy is blocked by T-09/T-10 |
| Simple standings | Service initializes every Tournament team, aggregates each valid completed fixture once, excludes scheduled/cancelled/postponed rows, and applies Win 2/Loss 0/Tie 1/No result 1 sorting | Implemented bounded user-direction slice; broader formulas and exceptional outcomes remain blocked by T-12/T-13 |
| Audit reads and writes | `auditPersistence` records Tournament and fixture changes; detail view renders readable history | Implemented baseline; correction/locking policy is blocked by T-16 |
| Tournament schema | `20260904000400_CreateTournamentManagement.php` | Existing migration; no new migration authorized |

## T-01–T-20 matrix

| ID / area | Current evidence | Status | Dependencies and branch scope | Migration impact | Permissions | Required tests after approval |
| --- | --- | --- | --- | --- | --- | --- |
| **T-01 Scope and competition types** | Fixed type allowlist; standalone branch-owned records; no hierarchy | `USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL` | Explicitly authorized Match links only; no parent hierarchy | None for bounded scope | Existing `tournament.view/create/update`; no new permission | Type allowlist, Match-link, branch, authorization, CSRF, audit tests |
| **T-02 Lifecycle and transitions** | CRUD statuses plus new service transition matrix | `USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL` | Planned/active/completed/cancelled transitions; terminal completed/cancelled; reasoned exceptional changes | None; existing status/audit columns suffice | Existing `tournament.update` | Transition matrix, reason, terminal, idempotency, CSRF, rollback, audit tests |
| **T-03 Branch ownership and multi-branch access** | `branch_id`; service and related Ground/Player/Match checks are branch-scoped | `USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL` | Single owning branch; no implicit shared visibility or transfer | None for bounded scope | Existing branch-scoped Tournament permissions | Cross-branch Tournament, Ground, Player, Match, fixture, report/read denial tests |
| **T-04 Dates and timezone** | DATE boundaries; fixture local date/time converted to UTC and bounded by Tournament dates | `USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL` | MCC-local inputs; service conversion; UTC timestamps; no suffixes | None for bounded scope | Existing CRUD/update permissions | Date/time normalization, boundary, round-trip, invalid input, preservation tests |
| **T-05 Registration and entry** | No registration entity or approval workflow; participant rows are direct writes | `NEEDS_DECISION` | Players, consent, eligibility, branch and withdrawal rules | Registration, approval, and history tables likely required | New registration/approval permissions require approval | Unit entry rules; Feature CSRF/RBAC; insert-only duplicate/withdrawal/audit tests |
| **T-06 Player eligibility and categories** | Current participant validation checks active Player and matching branch only | `NEEDS_DECISION` | Player lifecycle, age/category source, effective date, exceptions | Possibly eligibility snapshot/category fields | Existing update permission is not enough for new approvals | Unit eligibility boundary tests; Feature forged/inactive/cross-branch IDs; fixture tests |
| **T-07 Coach and official assignments** | No Tournament Coach/official fields; fixtures link Match/Ground only | `NEEDS_DECISION` | Coach module, branch assignment, conflict and reassignment policy | Assignment tables and audit history likely required | New assignment permissions require approval | Unit assignment rules; Feature visibility/CSRF; branch and reassignment audit tests |
| **T-08 Team and squad structure** | Teams are free-text participant rows; no team membership or squad/XI model | `NEEDS_DECISION` | Players, eligibility, selection authority, limits, locking | Team, membership, squad, and snapshot tables likely required | New team/selection permissions require approval | Unit membership/limits; Feature forged IDs; insert-only membership and rollback tests |
| **T-09 Match scheduling and fixture generation** | Manual fixture POST exists and can link a same-branch Match/Ground | `NEEDS_DECISION` | Matches, Scheduling, Ground availability, date/time and generation rules | Possibly rounds/fixture metadata and links | Existing `tournament.update` is not approval for generation workflows | Unit conflict/date rules; Feature route/CSRF; fixture transaction and idempotency tests |
| **T-10 Rounds and progression** | No round/bracket/progression model | `NEEDS_DECISION` | Fixture outcomes, tie-breaks, progression and recalculation | Round/group/bracket tables required | New progression permissions require approval | Unit progression examples; Feature authorization; insert-only recalculation tests |
| **T-11 Scorecards and results** | Tournament stores no scorecard or innings data; Match has separate result fields | `NEEDS_DECISION` | Match scorecard model, verification, locking, result source | Scorecard/innings/ball or approved summary tables | New scorecard/result permissions require approval | Unit score validation; Feature correction/lock paths; transactional scorecard tests |
| **T-12 Standings formulas** | Existing service calculates simple played/won/lost/draw/points from fixture rows | `NEEDS_DECISION` | Approved points, tie-breakers, rounding, missing data, recalculation | Formula version/snapshot fields may be required | Visibility and correction permissions require approval | Unit published examples; Feature branch visibility; recalculation and correction tests |
| **T-13 Exceptional outcomes** | Match logic recognizes `no_result`; Tournament has no tie/abandoned/rain workflow | `NEEDS_DECISION` | Match status/result policy and downstream standings | Possibly outcome/reason fields | New correction/status permissions require approval | Unit outcome matrix; Feature authorization; persistence/audit tests |
| **T-14 Player/Coach performance integration** | No Tournament performance records; existing Performance/Coach KPI sources are separately gated | `NEEDS_DECISION` | Performance, Coach KPI governance, approved formulas and recalculation | Source-link or performance snapshot tables may be required | Existing performance/KPI permissions do not authorize Tournament source data | Unit source/formula tests; Feature visibility; branch and recalculation fixtures |
| **T-15 Attendance integration** | No Tournament attendance relation or roster snapshot; Attendance is session/snapshot based | `NEEDS_DECISION` | Attendance AT-01–AT-06, roster source, denominator, corrections | Tournament roster/attendance relation likely required | Existing attendance permissions are not sufficient for Tournament scope | Unit snapshot/status tests; Feature read/write/CSRF; branch and audit persistence tests |
| **T-16 Corrections, approval, locking, audit** | CRUD and fixture writes audit; participant replacement deletes/recreates rows | `NEEDS_DECISION` | All dependent workflows and append-only correction policy | Version/history/lock fields or tables likely required | Approval/correction/lock permissions require approval | Unit idempotency; Feature reason/CSRF; rollback, before/after, and audit tests |
| **T-17 Permissions and role visibility** | Route filters and service checks use Tournament view/create/update; migration grants baseline permissions | `NEEDS_DECISION` | RBAC, branch scope, Parent/Player/Coach visibility, actions | Permission rows only if approved matrix adds them | Current permissions must not be broadened implicitly | Unit permission matrix; Feature hidden-action and denial tests; branch fixtures |
| **T-18 Reports and exports** | No Tournament report/export route or service | `NEEDS_DECISION` | Report definitions, filters, formats, freshness, branch visibility | Possibly export job/audit tables | New export permission requires approval | Unit column/filter contracts; Feature authorization; export scope tests |
| **T-19 Retention and recalculation** | No Tournament retirement, retention, versioning, or historical recalculation policy | `NEEDS_DECISION` | Audit, operational ownership, dependent Performance/Standings data | Retirement/version/retention metadata may be required | Archive/delete/recalculate permissions require approval | Unit retention matrix; Feature safe-not-found/denial; historical fixture tests |
| **T-20 Notifications and external delivery** | No Tournament trigger, recipient, channel, retry, or delivery audit | `NEEDS_DECISION` | Notifications policy, consent, WhatsApp/email/SMS boundaries | Delivery/outbox tables may be required | New notification permissions and recipient rules require approval | Unit trigger/recipient tests; Feature consent/branch scope; failure/retry audit tests |

## Safe work that can proceed now

### User-provided fixture and standings slice (2026-10-03)

**Status:** USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.

The existing `tournament_fixtures` table and legacy fixture method are extended only for manual branch-scoped fixtures: Round, same-Tournament Home/Away team validation, active branch Ground validation, separate MCC-local date/time inputs stored through existing UTC `scheduled_at`, Scheduled/Completed/Cancelled/Postponed statuses, reasoned status changes, audited corrections, and completed-fixture standings. Standings use existing completed Match result values with Win 2, Loss 0, Tie 1 each, and No result 1 each. Scorecards, innings, net run rate, registration, selection policy, notifications, exports, and all other unresolved T-01–T-20 items remain blocked.

The following are bounded verification or maintenance tasks and do not require a new Tournament policy:

1. Keep existing CRUD route, service, and UI contract tests passing.
2. Replace the destructive legacy Tournament integration fixture with isolated insert-only fixtures before persistent database verification.
3. Add read-only contract coverage for branch authorization, CSRF markup, selected Player preservation, responsive tables, and readable history.
4. Continue fixing confirmed CRUD presentation or error-handling defects without changing statuses, permissions, formulas, or workflows.

## Verification evidence added

The current CRUD baseline now has focused evidence for:

- Shared list, create, edit, and detail layout classes, action placement, datepicker markup, CSRF, field errors, and submitted-value preservation.
- Full active authorized Player option loading on create and edit, with existing IDs separated from the available option collection.
- Shared btSelect search, Select all/Clear controls, selected count, checkbox sizing/alignment, branch refresh, focus metadata, and responsive overflow rules.
- Readable Tournament detail cards, participant/fixture/standings tables, empty states, responsive wrappers, and history values without raw JSON or visible timezone suffixes.
- An insert-only persistent CRUD round-trip fixture that verifies branch lineage, list visibility, detail participants, and audit creation.

Evidence files:

- `tests/Unit/TournamentManagementContractTest.php`
- `tests/Feature/TournamentManagementRoutesTest.php`
- `tests/Feature/TournamentListRoutesTest.php`
- `tests/Feature/TournamentRoutesTest.php`
- `tests/database/TournamentManagementSafeTest.php`

These checks verify existing CRUD behavior and the bounded T-01–T-04 direction. They do not approve or implement T-05–T-20 policy.

## Blocked work

No T-05–T-20 workflow expansion is authorized until an approver explicitly reconciles the corresponding item as `Approved`. In particular, registration, team/squad entities, generated fixtures, rounds, scorecards, standings formulas, exceptional outcomes, performance, Tournament attendance, correction locks beyond the bounded lifecycle, new permissions, exports, retention, and notifications remain deferred.

No migration should be added or applied for these blocked areas. No database records should be deleted or rewritten to simulate approval.

## Evidence distinction

- **Implementation evidence:** current code, migrations, routes, and passing focused tests described above.
- **User direction:** none recorded in this matrix beyond the instruction to avoid silent invention and document assumptions.
- **Client approval:** no T-01–T-20 client approval is present; T-01–T-04 are user-provided direction only.
- **Unresolved decisions:** T-05–T-20 remain `NEEDS_DECISION`; T-01–T-04 remain user-provided direction and are not client-approved.

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
