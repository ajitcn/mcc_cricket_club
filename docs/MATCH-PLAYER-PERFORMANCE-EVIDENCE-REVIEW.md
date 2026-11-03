# Match Player Performance Evidence Review

**Review date:** 2026-10-11  
**Review type:** Documentation-only, read-only source and evidence review  
**Scope:** Existing Match Player Performance batting, bowling, and fielding
workflow

## Boundary and policy status

This review does not authorize new Match business rules. The seven Match ↔
Tournament ownership and source-of-truth decisions remain `NEEDS_DECISION` and
are preserved as:

`USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL`

No Match ↔ Tournament ownership, Fixture/Match linkage, standings,
permissions, routes, migrations, schema, database records, application
behavior, scorecards, innings, qualification, registration, selection
governance, exports, notifications, portal behavior, or
`docs/SRS-original.md` was changed.

## Completed verification reconciliation

The existing relevant files were run separately with synthetic insert-only
fixtures. The correction/version completion evidence is recorded in
`docs/MATCH-PLAYER-PERFORMANCE-CORRECTION-COMPLETION-REPORT.md`:

| Evidence group | Result |
| --- | --- |
| Unit: `tests/Unit/MatchManagementContractTest.php` | 13 tests, 179 assertions, exit code 0 |
| Feature: `tests/Feature/MatchManagementRoutesTest.php` | 6 tests, 59 assertions, exit code 0 |
| Database: `tests/database/MatchPlayerPerformanceSafeTest.php` | 4 tests, 38 assertions, exit code 0 |
| Total | **23 tests, 276 assertions, exit code 0** |

No tests were skipped. PHP lint passed for the Match Player Performance
service, controller, view, migration, and the three executed test files. The
database was `mcc_cricket_club_test`; migration `20261011000400_AddMatchPlayerPerformanceVersion`
was applied as batch 56.

The implemented correction/version slice is verified: one shared version per
Match Player Performance record covers batting, bowling, and fielding; version
increments are atomic; stale writes are rejected with a safe reload message;
correction reasons remain required; and permissions, branch isolation, CSRF,
transactions, before/after audit metadata, rollback, idempotent first-time
submissions, and completed-Match restrictions remain enforced.

## Workflow implementation evidence

### Routes and controllers — Verified

Explicit authenticated routes are defined in
`app/Modules/Matches/Config/Routes.php`:

- `GET /matches/{id}/performance` renders the performance page.
- `POST /matches/{id}/performance` saves all selected Player entries.
- `POST /matches/{id}/performance/{playerId}` saves one Player entry.

`app/Modules/Matches/Controllers/Browser.php` keeps the browser layer thin,
delegates writes to `MatchPlayerPerformanceService`, redirects successful
writes back to the Match performance page, preserves submitted input on error,
and maps service errors to safe browser messages.

The performance POST routes use the authenticated route filter while the
service performs the required branch and permission checks. The views include
CSRF fields.

### Services and models — Verified

`app/Services/MatchPlayerPerformanceService.php` is the dedicated persistence
service. It loads the Match, selected Players, existing performance rows,
calculated metrics, and scoped audit history. It persists one row per Match,
Player, innings number, and team side.

The migration-backed `match_player_performances` table stores batting,
bowling, and correction fields. The fielding migration adds fielding status,
catches, stumpings, completed and assisted run-outs, direct-hit run-outs,
dropped catches, position, and notes.

The generic `PerformanceService` is a separate Player/Coach performance path.
No approved relationship between it and Match Player Performance is inferred.

### Batting — Verified

The workflow supports batting status, runs, balls faced, fours, sixes, batting
position, dismissal type, and member-only dismissal references. It validates
non-negative integer values, duplicate batting positions, did-not-bat totals,
boundary totals, and rendered metric calculations such as average, strike
rate, and boundary runs.

### Bowling — Verified

The workflow supports bowling status, legal balls, maidens, runs conceded,
wickets, wides, no-balls, byes, and leg-byes. It validates non-negative
integer values, did-not-bowl totals, maidens versus legal balls, wickets versus
legal balls, and renders overs, economy, average, and strike-rate metrics.

### Fielding — Verified

The workflow supports fielding status, catches, stumpings, completed and
assisted run-outs, direct-hit run-outs, dropped catches, position, and notes.
It validates did-not-field totals and renders fielding dismissal and
contribution metrics. Fielding schema support is provided by the existing
forward-only fielding migration.

## Control classification

| Area | Classification | Evidence and limitation |
| --- | --- | --- |
| Match Player membership validation | **Verified** | Submitted Players must exist in the Match selection rows; dismissal and fielder references are restricted to Match members. |
| Branch isolation | **Verified** | Match authorization uses the persisted Match branch; Player joins require matching branch IDs; performance rows carry `branch_id`; audit history is filtered to the Match branch. |
| Permissions | **Verified** | Scheduled/non-completed writes use existing `match.update`; completed Match corrections use existing `performance.update`; reads use `match.view`. No new permission is introduced. |
| CSRF | **Verified for bounded browser evidence** | Performance forms include CSRF fields and browser route contracts check them; global CSRF enforcement and service authorization remain the runtime controls. |
| Completed-Match restrictions | **Verified** | Completed Matches render read-only for ordinary editing; correction mode requires `performance.update` and a reason. Cancelled Matches reject performance writes. |
| Correction reasons | **Verified** | Changed existing records require `correction_reason`; the browser maps the missing-reason error to a field-level message. |
| Optimistic concurrency | **Verified** | Migration `20261011000400_AddMatchPlayerPerformanceVersion` adds one shared per-record version; corrections require the submitted version, increment atomically, and reject stale writes without overwriting current data. |
| Transaction rollback | **Verified** | Batch writes use a transaction; the insert-only database test forces an out-of-range database write after an earlier valid insert and confirms that no partial records remain. |
| Idempotent writes | **Verified for repeated identical payloads** | Re-saving unchanged values skips the update and does not add another audit event. No separate idempotency-key protocol exists. |
| Audit metadata | **Verified** | Create and correction events record actor, branch, entity, before/after summaries, and correction reason through the central audit persistence service. |
| Sensitive-data protection | **Verified for bounded output handling** | Output values, names, notes, reasons, and audit display values are escaped and access is branch/permission scoped; separate masking/retention policy for notes remains unresolved. |
| Unit evidence | **Verified** | `tests/Unit/MatchManagementContractTest.php` covers performance source fields, service calculations, migration markers, fielding UI, correction reason, and route/view contracts. |
| Feature evidence | **Verified** | `tests/Feature/MatchManagementRoutesTest.php` covers performance routes, CSRF fields, return targets, correction messaging, and batting/bowling/fielding UI contracts. |
| Database evidence | **Verified** | `tests/database/MatchPlayerPerformanceSafeTest.php` is insert-only and covers persistence, metrics, audit, repeated identical save, correction, version increment, stale rejection, missing correction reason, genuine database-error rollback, and cross-branch rejection: 4 tests, 38 assertions. |
| Completion-report evidence | **Verified** | `docs/MATCH-PLAYER-PERFORMANCE-CORRECTION-COMPLETION-REPORT.md` records 23 tests, 276 assertions, exit code 0, PHP lint success, database, and batch-56 migration evidence. |
| Generic Performance relationship | **NEEDS_DECISION** | The generic `PerformanceService` remains a separate source; no canonical relationship, formula, approval, or reporting rule is approved. |
| Scorecards and innings | **NEEDS_DECISION** | No scorecard or innings workflow is implemented or authorized by this review. |
| Production deployment | **Deployment blocker** | Production migration/application of the Match Performance schema and operational permission/data readiness require deployment-owner verification; this review performed no migration or database operation. |

## Confirmed defects and evidence conflicts

No confirmed application defect was established by this documentation-only
review.

The earlier Match read-only audit stated that Match-specific performance records
were absent. That statement is stale relative to the current
`MatchPlayerPerformanceService`, routes, views, migrations, and safe database
test. It is treated as an evidence conflict, not as a reason to change
application behavior.

The correction/version implementation is verified for the bounded slice. Its
direction remains user-provided and is not client approval; no broader
performance governance or downstream reporting policy is inferred.

## Deferred and unresolved boundaries

The following remain outside this review and require separate decisions where
applicable:

- Match ↔ Tournament ownership, Fixture/Match linkage, standings, and result
  source of truth.
- Scorecards, innings, qualification or brackets.
- Registration and selection governance.
- Generic PerformanceService integration, awards, attendance, or reporting
  formulas.
- Exports, notifications, portal behavior, media, retention, and new
  permissions.
- Broader performance governance, reopening, and historical retention policy.

## Evidence inventory

| Evidence | Finding |
| --- | --- |
| `app/Modules/Matches/Config/Routes.php` | Explicit authenticated performance read/write routes. |
| `app/Modules/Matches/Controllers/Browser.php` | Thin controller delegation, safe redirects, input preservation, and error mapping. |
| `app/Services/MatchPlayerPerformanceService.php` | Validation, membership/branch checks, transactions, metrics, idempotent unchanged-save behavior, and audit calls. |
| `app/Modules/Matches/Views/performance.php` | Batting, bowling, fielding forms, read-only/correction states, CSRF, escaped output, and audit display. |
| `20261003000300_CreateMatchPlayerPerformances.php` | Core Match Player Performance schema, keys, branch field, and foreign keys. |
| `20261003000400_AddMatchPlayerFieldingDetails.php` | Forward-only fielding columns. |
| `tests/Unit/MatchManagementContractTest.php` | Unit/source contracts and calculation checks. |
| `tests/Feature/MatchManagementRoutesTest.php` | Route, CSRF, UI, and controller contracts. |
| `tests/database/MatchPlayerPerformanceSafeTest.php` | Insert-only persistence, audit, correction, and retry evidence. |
| `docs/MATCHES-COMPLETION-REPORT.md` | Broader Match focused result: 21 tests, 243 assertions. |

## Historical next action (completed)

The previously listed evidence action is complete: rollback and negative
cross-branch write coverage now pass in the insert-only database suite.

## Single next safe action

**Obtain deployment-owner approval and production-readiness evidence for the
forward-only Match Player Performance version migration.**

This is one bounded deployment-evidence action. It does not authorize any
Match ↔ Tournament ownership or standings change, scorecards, innings,
qualification, exports, notifications, portal behavior, or new permissions.

