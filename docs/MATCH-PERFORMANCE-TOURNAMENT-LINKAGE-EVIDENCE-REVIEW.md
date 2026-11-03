# Match Performance and Tournament Linkage Evidence Review

Review date: 2026-10-11  
Review type: Documentation-only, read-only evidence review  
Scope: Existing Match, Match Player Performance, Tournament, Fixture, Ground, Opponent, Player, and standings implementation

## Executive summary

The existing implementation provides a branch-scoped Match workflow, Playing XI persistence, dedicated per-Match batting/bowling/fielding records, Tournament fixtures, fixture status actions, and read-time standings from completed fixtures. The bounded Match ↔ Tournament linkage slice is now verified by the completion evidence: 55 tests, 523 assertions, exit code 0.

The evidence does not establish a complete scorecard, competition-registration, or approved broader standings policy. The implemented linkage remains explicitly **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL**. No additional application or data changes were made for this documentation reconciliation.

## Current bounded linkage reconciliation

The bounded slice is classified as **Verified** for implementation evidence,
while its policy status remains **USER-PROVIDED DIRECTION — NOT CLIENT
APPROVAL**:

- Fixture/Match linkage is optional and one-to-one: a Fixture may link to one
  Match, and a Match may link to one Fixture.
- Fixture owns Tournament scheduling fields and status. Match owns the
  verified completed result and completion state.
- Only completed Fixtures linked to completed Matches with verified results
  contribute standings. Scheduled, postponed, cancelled, and incomplete
  Fixtures contribute no points.
- Same-branch validation, existing permissions, CSRF, transactions, audit
  metadata, and optimistic concurrency are preserved.
- Migration `20261011000300_AddMatchTournamentLinkageVersion` is forward-only
  and idempotent.
- PHP lint passed for the changed implementation, migration, and focused test
  files.
- Verification passed: **55 tests, 523 assertions, exit code 0**.
- The legacy destructive Tournament integration suite is excluded because its
  cleanup fixture is incompatible with persistent append-only protections.

Deferred and still `NEEDS_DECISION`: scorecards, innings, automatic
qualification/brackets, registration, selection governance, exports,
notifications, portal projections, and other unrelated policy areas.

## Evidence boundary

This review inspected the current source, routes, services, models, views, focused tests, and existing completion/audit reports. Tests were not rerun because the requested work is documentation-only. Reported test results below are quoted as existing project evidence, not as a new verification run.

The authoritative requirements file, `docs/SRS-original.md`, was not modified.

## 1. Match creation, branch ownership, references, and Playing XI

### Routes and controllers

The Match routes are defined in `app/Modules/Matches/Config/Routes.php` and handled by `app/Modules/Matches/Controllers/Browser.php`:

- `GET /matches` lists branch-scoped Matches.
- `GET /matches/new` renders the create form.
- `POST /matches` creates a Match.
- `GET /matches/{id}` renders Match detail.
- `GET /matches/{id}/edit` and `POST /matches/{id}` support editing.
- `POST /matches/{id}/complete` completes a scheduled Match.
- `GET /matches/{id}/performance` and the two performance POST routes manage Match Player Performance.

The route definitions use explicit permission filters for Match list, create, update, and detail actions. Performance writes currently use the authenticated route filter and rely on service-level authorization.

### Services, models, and relationships

`MatchManagementService` owns Match list, create, detail, edit, completion, validation, selection persistence, conflict checks, and audit behavior. `MatchModel` represents the Match record; `MatchPlayerModel` represents the Match-to-Player selection rows; `OpponentModel` represents the branch-owned Opponent reference.

A Match stores its branch, optional Ground, optional Coach, Opponent, type, status, local-input-derived UTC start/end values, result, score summary, and remarks. `match_players` stores selected squad members and identifies `playing_xi` rows separately from the squad. The service enforces the Playing XI subset rule and the maximum of 11 Playing XI Players.

Ground and Opponent references are validated against the Match branch. Coach scheduling and Ground scheduling conflicts are checked while preserving the transaction around the write. The current implementation does not add a `tournament_id` directly to Matches; Tournament linkage is represented by an optional `tournament_fixtures.match_id` reference.

### Security and branch controls

Evidence confirms:

- Branch-scoped Match queries and detail/history reads.
- Server-side permission checks for Match viewing, creation, and updates.
- CSRF-protected browser forms.
- Branch validation for Ground, Coach, Opponent, and selected Players.
- Transactional Match and selection writes with rollback on failure.
- Audit entries for Match creation, updates, and completion.
- UTC persistence with MCC-local date/time display and input conversion.

### Existing evidence

`docs/MATCHES-COMPLETION-REPORT.md` records the focused result:

```text
OK (21 tests, 243 assertions)
```

The reported command used `MatchManagementContractTest`, `MatchManagementRoutesTest`, and `MatchManagementSafeTest`. The report describes the database fixture as unique, persistent, and insert-only. `MatchBrowserRoutesTest` and `MatchManagementIntegrationTest` provide additional source and browser/database evidence, but no new consolidated result was established in this review.

### Missing or conflicting evidence

- There is no approved policy evidence for Match ownership by a Tournament, competition registration, or cross-branch competition.
- The Playing XI persistence rule is evidenced, but approval, lock, correction, and historical snapshot policy are not established.
- Match status can be edited through the general Match workflow while a dedicated completion action also exists. A complete transition matrix, cancellation reason policy, and reopening/correction policy are not documented.
- The older read-only audit identifies broader scorecard, innings, attendance, export, media, and notification gaps. Those are outside the currently verified Match CRUD scope.

### Historical recommendation (superseded)

Record an approved Match lifecycle and Playing XI correction policy before expanding the Match workflow or linking it more deeply to Tournament competition rules.

## 2. Match Player Performance: batting, bowling, and fielding

### Routes and controllers

`GET /matches/{id}/performance` renders the selected Match Players and existing records. Player-specific and bulk POST routes persist performance. `Matches\\Controllers\\Browser` translates service exceptions into field-level browser feedback and redirects back to the performance page.

### Services, models, and relationships

`MatchPlayerPerformanceService` is the dedicated source for Match Player Performance. It loads Players through the Match selection rows, validates that each submitted Player is a member of the Match, and persists one performance row per Player, innings, and team side.

The service covers:

- Batting status, runs, balls, fours, sixes, and dismissal references.
- Bowling status, legal balls, maidens, runs conceded, and wickets.
- Fielding status, catches, stumpings, run-outs, assists, direct-hit run-outs, and dropped catches.
- Derived metrics such as strike rate, batting average, overs, economy, and fielding contributions.
- Zero-denominator handling and non-negative integer validation.
- Dismissal and fielder references restricted to valid Match members.

The separate generic `PerformanceService` records broader Player/Coach performance and is not the same persistence path. No direct Match relationship was established for that generic service.

### Security, correction, and transaction controls

Evidence in the service and tests shows:

- Branch and Match authorization before reads and writes.
- Completed Matches are read-only for ordinary writes; corrections require the existing performance update permission and a correction reason.
- Cancelled Matches reject performance entry.
- Bulk writes require entries for all selected Match Players.
- Duplicate batting positions and invalid status/metric combinations are rejected.
- Inserts, corrections, and audit rows are transactional.
- No-change corrections are treated as a no-op.
- Audit entries distinguish creation from correction.

### Existing evidence

`tests/Unit/MatchManagementContractTest.php` and `tests/Feature/MatchManagementRoutesTest.php` cover the performance routes, form contracts, permission/CSRF expectations, and calculation/source contracts. `tests/database/MatchPlayerPerformanceSafeTest.php` is the insert-only database evidence for batting, bowling, fielding, validation, correction, transaction, branch, and audit behavior. The inspected project reports do not provide a separate consolidated assertion count for that file.

### Missing or conflicting evidence

- The performance POST routes do not declare a dedicated route-level `performance.update` permission filter; service authorization remains the security authority. This is a defense-in-depth consistency gap to review, not evidence that service authorization is absent.
- There is no approved policy connecting Match Player Performance to the generic `PerformanceService`, Attendance, awards, selection, or Tournament standings.
- The source supports correction reasons, but no version/optimistic-concurrency policy comparable to the separately verified Attendance feedback workflow was established here.
- Scorecard/innings/team-side semantics and statistical source-of-truth policy remain incomplete.

### Historical recommendation (superseded)

Approve the canonical Match Performance source, correction/version policy, and relationship to generic Performance before adding integrations or reporting.

## 3. Tournament and Fixture linkage

### Routes and controllers

`app/Modules/Tournaments/Config/Routes.php` and `Tournaments\\Controllers\\Browser` provide:

- Tournament list, create, detail, and edit routes.
- Fixture list, create, detail, and edit pages.
- Fixture create/update POST routes.
- POST actions for postponement, cancellation, and completion.

Current fixture routes are protected by `tournament.view` for reads and `tournament.update` for writes. The current views include fixture creation, editing, status-action forms, result Match selection, and correction messaging.

### Services, models, and relationships

`TournamentManagementService` owns Tournament and Fixture reads/writes, participant persistence, status changes, standings preparation, branch validation, and audit history. `tournament_fixtures` stores the Tournament relationship, fixture number, home/away text, optional `match_id`, optional `ground_id`, scheduled time, status, and home/away points.

Fixture-to-Match linkage is optional until completion. Completing a Fixture requires a completed Match result reference from the same branch. The service validates linked Match and Ground branch ownership and validates participant Player branch membership.

Supported fixture statuses in the current source include `scheduled`, `completed`, `cancelled`, and `postponed`. Postponement and cancellation require a reason. Completed Fixture corrections require a reason and are shown as correction mode in the Fixture view.

### Security and branch controls

Evidence confirms:

- Explicit Tournament view/update permission filters.
- Branch-scoped Tournament, Fixture, Match, Ground, and Player validation.
- CSRF forms for Tournament and Fixture writes.
- Transactions for related Tournament, participant, Fixture, and status writes.
- Audit history for Tournament and Fixture changes, including status reasons.
- Ground and Player form options are constrained to the Tournament branch; current source includes active-state filtering for these options.

### Existing evidence

`docs/TOURNAMENTS-CRUD-COMPLETION-REPORT.md` records:

```text
13 tests, 85 assertions, passed
```

That report covers the Tournament Unit/Feature verification. `tests/database/TournamentManagementSafeTest.php` provides insert-only evidence for fixture creation, branch protection, conflicts, postponement, cancellation, completion, idempotent completion, audit history, and standings. `tests/database/TournamentManagementIntegrationTest.php` is legacy destructive evidence because its setup/teardown calls `emptyTable()` and must not be treated as safe persistent-database evidence.

### Missing or conflicting evidence

- Older Tournament reports state that a rendered fixture-create form or fixture status workflow was missing. The current routes, controller, and views now contain these workflows. This is a documentation conflict, not a confirmed current application defect.
- `validatedFixture` does not appear to enforce that a fixture scheduled date lies within the Tournament start/end dates. Whether that is required is a policy decision, but the validation evidence is currently incomplete.
- The bounded Fixture scheduling path now consumes the configured application timezone; the earlier hard-coded `Asia/Kolkata` finding is superseded by the completed implementation.
- Participant replacement uses delete/recreate persistence, so participant-level history is not preserved even though the operation is transactional and Tournament audit exists.
- The bounded direction records the verified completed Match result as authoritative for the implemented standings path. Broader scorecard and formula policy remains `NEEDS_DECISION`.

### Historical bounded action (completed)

The bounded Fixture/Match linkage, configured timezone handling, result source,
concurrency, audit, and safe verification were reconciled and implemented.

## 4. Tournament standings and completed Fixtures

### Routes/controllers and data flow

Tournament detail and Fixture detail views render standings prepared by `TournamentManagementService`. The service derives standings from completed Fixtures and the stored home/away points, with the linked completed Match used as the result reference. The current evidence does not establish a separate standings write workflow.

### Controls and evidence

Branch-scoped Tournament and Fixture loading, Match branch validation, explicit Tournament permissions, CSRF-protected forms, transactions, and audit history are evidenced by the Tournament source contracts and safe database test.

The safe test covers completed Fixture creation/completion and standings output. The destructive integration test also contains standings assertions but is excluded from safe verification because it clears Tournament tables.

### Missing or conflicting evidence

- Wins, losses, draws, no-results, ties, net run rate, tiebreakers, abandoned Matches, and postponed/cancelled treatment are not defined by an approved policy in the inspected evidence.
- The bounded implementation uses a completed verified Match result as the
  standings source. Future scorecards/innings and any broader formula remain
  outside this verified slice.
- Recalculation, correction history, retention, and standings versioning are not documented.

### Historical recommendation (superseded)

Approve the standings formula and result/points source-of-truth before implementing scorecards, recalculation, exports, or competition reporting.

## 5. Cross-module relationship review

| Relationship | Current implementation evidence | Boundary or gap |
| --- | --- | --- |
| Match -> Branch | Match branch column and branch-scoped service queries | Cross-branch competition policy is not approved |
| Match -> Ground | Optional branch-owned Ground reference and conflict validation | Ground sharing across competition scopes is undecided |
| Match -> Opponent | Branch-owned Opponent reference and active validation | Opponent identity/history policy is limited |
| Match -> Player | `match_players` squad and `playing_xi` selection rows | Registration, approval, locking, and snapshot policy is open |
| Match -> Performance | `match_player_performances.match_id` plus Match membership validation | Generic PerformanceService is a separate source |
| Tournament -> Fixture | `tournament_fixtures.tournament_id` with unique fixture number | Tournament lifecycle and fixture ownership policy is incomplete |
| Fixture -> Match | Optional one-to-one `match_id`, required completed Match reference for completion | One-to-one cardinality is enforced by service validation and the unique index; linkage remains Fixture-owned |
| Fixture -> Ground | Optional branch-owned Ground reference | Date-bound and timezone validation gaps remain |
| Tournament -> Player | Participant rows validate branch membership | Participant replacement lacks participant-level history |
| Fixture -> Standings | Completed Fixtures linked to completed, verified Matches feed read-time standings | Broader formulas remain `NEEDS_DECISION`; bounded result source is verified |

The cross-module model is therefore structurally connected but not yet a complete competition domain. Match, Fixture, and Performance records are related by references and service validation, not by a single approved scorecard/competition aggregate.

## 6. Common security and operational review

### Evidenced controls

- Permission-based browser routes for Match and Tournament reads/writes.
- Service-level authorization and branch checks for referenced records.
- CSRF-protected browser forms.
- Transactions around related writes and status changes.
- Audit logging for Match, Performance, Tournament, Fixture, and status operations.
- Idempotent Match completion and Tournament Fixture completion behavior in the existing safe tests.
- UTC persistence with MCC-local display/input handling in Match workflows.
- Escaped output in the inspected detail and performance views.

### Evidence limitations

- The legacy Tournament integration suite is destructive and cannot safely run against the persistent test database.
- No new combined test result was generated for this documentation review.
- Existing reports have different scopes and dates; they should not be read as proof of functionality that their commands did not execute.
- The current Fixture timezone helper and Match performance route-filter asymmetry need a bounded security/timezone review.

## 7. Test and completion-report evidence matrix

| Area | Unit evidence | Feature evidence | Database evidence | Existing report evidence |
| --- | --- | --- | --- | --- |
| Match CRUD and completion | `MatchManagementContractTest.php` | `MatchManagementRoutesTest.php`, `MatchBrowserRoutesTest.php` | `MatchManagementSafeTest.php`, `MatchManagementIntegrationTest.php` | 21 tests, 243 assertions for the focused Match command |
| Match Player Performance | Match contract assertions | Match route/view assertions | `MatchPlayerPerformanceSafeTest.php` | No separate consolidated count inspected |
| Tournament CRUD and routes | `TournamentManagementContractTest.php` | `TournamentManagementRoutesTest.php`, `TournamentRoutesTest.php`, `TournamentListRoutesTest.php` | `TournamentManagementSafeTest.php` | 13 tests, 85 assertions for the reported Unit/Feature command |
| Tournament Fixture/status/standings | Tournament contract assertions | Fixture routes/views in Tournament route tests | Safe Tournament database workflow | Safe evidence exists; destructive integration excluded |
| Grounds and references | Ground contract tests | Ground route tests | Ground safe database test | 12 tests, 53 assertions in the Ground report |
| Generic Performance | Performance contract/route/database tests | Existing module evidence | Existing module evidence | Not established as Match linkage evidence |

## 8. Confirmed defects and evidence conflicts

### Confirmed implementation or evidence gaps

1. No bounded Fixture timezone defect remains; the implementation reads the configured application timezone while preserving UTC storage.
2. Fixture validation evidence does not show a check that the scheduled Fixture date is inside the Tournament date window.
3. Participant replacement does not preserve participant-row history because it deletes and recreates rows.
4. Match performance write routes rely on service authorization without a matching dedicated route-level performance permission filter.
5. The bounded Match/Tournament result and standings source is verified; scorecard/innings integration and broader standings policy remain undefined.

These findings are bounded evidence or policy gaps. They do not authorize application changes in this review.

### Documentation conflicts

The current Tournament source has fixture list/create/show/edit routes and postponement, cancellation, and completion actions. Statements in older Tournament audit/completion documents that describe these UI workflows as absent are stale and should be reconciled in a future documentation pass.

## 9. NEEDS_DECISION policy areas

The following decisions are required before broader implementation:

- Whether Matches are standalone, Tournament-owned, or both, and whether a Match may belong to multiple Fixtures.
- Tournament and Match branch scope, shared competitions, cross-branch Players, Grounds, Coaches, and Opponents.
- Match lifecycle transitions, cancellation/postponement reasons, reopening, correction, and terminal locking.
- Squad and Playing XI registration, approval, lock, replacement, and historical snapshot rules.
- Scorecard and innings model, result authority, exceptional outcomes, and Match completion requirements.
- Match Player Performance versus generic PerformanceService as the canonical source, including formulas, approval, visibility, correction, and versioning.
- Fixture date bounds, timezone configuration, status transitions, correction retention, and audit detail.
- Tournament participant uniqueness, team representation, replacement history, and eligibility.
- Standings formulas, points, tiebreakers, no-result/abandoned treatment, recalculation, and correction authority.
- Attendance, awards, media, notifications, exports, and reporting integrations.

## 10. Deployment and migration blockers

- Match and Tournament schema dependencies must remain under migration control; no migration change is authorized by this review.
- Existing project reports identify migration-history/group mismatches in the test environment. Migration status must be reconciled before any future schema deployment, without rewriting history.
- Safe insert-only fixtures are available for the principal Match and Tournament workflows, but the legacy Tournament integration suite is not safe for persistent database execution because it calls `emptyTable()`.
- A deployment requires approved branches, users, roles, permissions, Grounds, Opponents, Players, and Match/Tournament fixture reference data.
- Timezone configuration must be confirmed before relying on Fixture scheduling timestamps in production.
- No production-data, schema, trigger, route, permission, or migration changes were made for this review.

## 11. Single recommended bounded next action

Obtain formal client approval for the recorded Match ↔ Tournament ownership and
Match-result/Fixture-points source-of-truth direction. This is documentation
and decision work only; it does not authorize scorecards, innings,
qualification, registration, selection governance, exports, notifications, or
new permissions.

