# Tournaments Module — Read-Only Audit

**Audit date:** 2026-10-01  
**Scope:** Source, schema, test, and project-document review only. No application code, tests, migrations, seeders, permissions, database records, or existing decisions were changed. No migrations or destructive commands were run.

## Evidence and authorization boundary

- **Implementation evidence** below comes from the current source tree, migrations, seeders, and tests.
- Existing code and role grants are not treated as client approval.
- User direction and client approval are not inferred from the presence of routes, migrations, or tests.
- Unresolved policies remain NEEDS_DECISION.
- docs/SRS-original.md is not present in this checkout. Available SRS evidence is docs/SRS.md, docs/REQUIREMENTS-REVIEW.md, docs/REQUIREMENTS.md, the decision register, Match decision documents, and the UI architecture document. The missing original file is an audit limitation and was not created or modified.

## 1. Existing Tournament functionality

The module currently provides:

- Branch-scoped Tournament list with search, status/type filters, date filter, pagination, and empty state.
- Create and edit forms for name, Tournament type, status, start/end dates, optional Ground, description, team names, and Players.
- Tournament detail view with branch/Ground, participants, fixtures, standings, and audit history.
- Participant persistence for free-text teams and branch-matching Players.
- Fixture creation service supporting fixture number, home/away names, optional existing Match linkage, optional Ground, optional scheduled time, planned/completed/cancelled fixture status, and points.
- Optional linkage from a fixture to one existing Match in the same branch.
- Read-time standings derived from completed fixture status, linked Match result, and stored home/away points.
- Transactional Tournament and fixture writes, resource locks for Ground and linked Match, branch authorization, and central audit persistence.

It does not provide scorecards, innings, deliveries, batting/bowling/fielding/extras, tournament hierarchy, registration approval, fixture editing/cancellation, awards, certificates, media, match attendance, Match performance integration, exports, or Tournament-specific status history.

## 2. Current routes and screens

app/Modules/Tournaments/Config/Routes.php uses explicit authenticated routes:

| Method | Route | Handler | Filter |
|---|---|---|---|
| GET | /tournaments | Browser::index | permission:tournament.view |
| GET | /tournaments/new | Browser::createPage | permission:tournament.create |
| POST | /tournaments | Browser::create | permission:tournament.create |
| GET | /tournaments/{id} | Browser::showPage/$1 | permission:tournament.view |
| GET | /tournaments/{id}/edit | Browser::editPage/$1 | permission:tournament.update |
| POST | /tournaments/{id} | Browser::update/$1 | permission:tournament.update |
| POST | /tournaments/{id}/fixtures | Browser::fixture/$1 | permission:tournament.update |

Screens:

- Views/index.php: list, GET filters, create link, table, pagination.
- Views/form.php: create/edit Tournament form with CSRF, branch/Ground/Player options, date pickers, team textarea, and multi-select Players.
- Views/show.php: detail, participants, fixtures, standings, audit table, and edit link.
- There is no rendered fixture-create form on the detail page even though the POST fixture route and service exist. The fixture workflow is therefore not reachable through the provided Tournament UI without another caller.

The module is exposed in the shared navigation through the Matches & Tournaments group when tournament.view is available.

## 3. Database tables and relationships

Migration 20260904000400_CreateTournamentManagement.php creates:

### tournaments

- branch_id to branches.id with restrict delete behavior.
- name, tournament_type, status, starts_on, ends_on, optional ground_id, description, creator, and timestamps.
- Index on branch and start date.
- No version, lock, status-history, approval, retention, hierarchy, participating-branch, or tournament-owner relation.

### tournament_participants

- Tournament foreign key with cascade.
- Participant type, optional team name, optional Player ID, and creation timestamp.
- Player foreign key restricts Player deletion.
- No uniqueness constraint preventing duplicate team or Player rows.

### tournament_fixtures

- Tournament foreign key with cascade.
- Unique (tournament_id, fixture_number).
- Home/away free-text names.
- Optional match_id to matches.id, optional ground_id to grounds.id.
- Optional scheduled UTC-style DATETIME, status, home/away points, and timestamps.
- No innings, scorecard, result-verification, correction, lock, weather, cancellation-reason, or branch-participant relation.

The migration is forward-only; down() intentionally does not remove the schema. It also inserts tournament.view, tournament.create, and tournament.update for Super Admin, Branch Admin, and Coach roles when those roles exist.

## 4. Permissions and branch-scope rules

Current route filters use tournament.view, tournament.create, and tournament.update.

TournamentManagementService repeats branch authorization using the centralized authorization service:

- List reads only branches where the current context has tournament.view.
- Create authorizes the submitted branch with tournament.create.
- Edit authorizes the existing Tournament branch with tournament.update and rejects a submitted branch change.
- Detail reads authorize the Tournament branch.
- Ground and linked Match reads are constrained to the Tournament branch.
- Player participants are accepted only when the Player branch_id equals the Tournament branch.
- Fixture writes authorize the Tournament branch and constrain linked Match/Ground lineage.

The current data model is single-branch: every Tournament has one branch_id. The code does not implement shared or multi-branch Tournament access. The migration grants create/update to Coaches; the final role authority for Tournament writes is not resolved by current Tournament decisions.

## 5. Match integration status

Implemented:

- A fixture may optionally reference one existing Match through tournament_fixtures.match_id.
- The service locks the Match resource, requires the Match to exist in the Tournament branch, and rejects a Match already linked to another fixture.
- Detail and standings queries join Match rows with a branch predicate.
- Linked Match result and score summary are displayed and used for standings.

Missing or unresolved:

- matches has no tournament_id; the relationship is only through the fixture table.
- No policy defines whether Matches may stand alone, must belong to a Tournament, or may be shared across branches.
- No scorecard, innings, player metrics, Match attendance, performance, selection, winner derivation, or Tournament hierarchy integration exists.
- Match and Tournament decision records conflict with broad future requirements: BR-05 describes global shared Tournaments and participating branches, while current Tournament and Match schemas are single-branch.

## 6. Confirmed defects and implementation gaps

These are source-confirmed gaps, independent of unresolved product decisions:

1. **Fixture workflow has no UI entry point.** The explicit fixture POST route and service exist, but show.php renders no fixture form or action.
2. **Fixture dates are not checked against Tournament dates.** fixture() validates Ground conflicts but does not reject a scheduled fixture outside starts_on–ends_on. Edit validation only protects existing fixtures when Tournament dates change.
3. **Option queries omit active-state checks.** formOptions() returns all authorized Grounds and all branch Players without is_active/Player-status eligibility filtering. The write path checks branch equality but not the approved active/eligible policy.
4. **Participant replacement is destructive within the transaction.** Saving a Tournament deletes all existing participant rows and recreates them. No participant change history or before/after audit is recorded.
5. **Duplicate participant values are not fully constrained.** Player IDs are de-duplicated in memory, but team names are not normalized/uniqued and the schema has no participant uniqueness constraint.
6. **Validation errors are generic.** The controller flashes one exception message on redirect; there is no field-level error map or explicit submitted-option reconstruction.
7. **Fixture status and Tournament status are directly editable.** There is no transition matrix, reason policy, lock boundary, correction workflow, or idempotent status action.
8. **Fixture result and standings trust manual fields.** Stored points and linked Match result are combined without a verification or source-of-truth policy.
9. **History presentation is a raw metadata disclosure.** Values are escaped, but the view exposes encoded before/after JSON and has no Tournament-specific sensitive-key policy.
10. **No Tournament Feature test layer exists.** Only a Unit contract test and a destructive database integration test were found.
11. **The integration test is unsafe for the persistent test database.** TournamentManagementIntegrationTest calls emptyTable() in setup and teardown for Tournament tables.

These are not claimed as authorization to expand the module.

## 7. UI gaps

Source review found:

- Tournament list and form use the older staff_header/Bootstrap container pattern rather than the newer shared page-header/action-toolbar layout.
- Create is placed beside filters instead of in a consistent page-header action group.
- Detail uses plain headings, paragraphs, lists, and an inline Edit link rather than shared cards, badges, action groups, and responsive detail sections.
- Participants and fixtures are unordered lists with limited status/date formatting.
- The fixture route has no visible form, empty-state action, or error presentation.
- The form uses a native multi-select rather than the shared btSelect pattern used elsewhere.
- Date inputs use the shared datepicker attributes, but fixture scheduled time has no UI.
- Audit metadata is shown through a raw JSON disclosure; long values and sensitive-key masking are not handled by the newer history conventions.
- No source/browser visual verification was performed during this audit.

## 8. Missing validation and security checks

Confirmed or unverified from source:

- Active Ground and Player status/eligibility are not enforced in option loading or participant writes.
- Fixture scheduled time requires an explicit timezone suffix and is parsed by a hard-coded DateTimeImmutable path rather than the configured MCC timezone convention.
- Fixture scheduled date is not bounded by Tournament dates.
- Fixture home/away values are not checked against registered participant teams.
- Fixture updates, cancellation, correction, and reason requirements do not exist.
- No method-level CSRF assertion exists in the controller; forms include CSRF, so browser POST protection depends on global filters/configuration.
- No explicit field-level authorization or validation errors are prepared by the controller.
- No immutable roster/selection snapshot exists.
- No scorecard, result verification, audit correction, lock, or concurrent-edit policy exists.
- Team and Player visibility policies for Parents, Players, and Coaches are not defined.

Existing protections to preserve include explicit route filters, service-level branch checks, escaped output, transactional writes, Ground/Match resource locks, and central audit persistence.

## 9. Missing tests

Current tests:

- tests/Unit/TournamentManagementContractTest.php: route, branch-lineage, transaction/lock/audit, and view/service marker assertions.
- tests/database/TournamentManagementIntegrationTest.php: CRUD, team/Player persistence, optional Match fixture linkage, standings, and one cross-branch denial.

Missing coverage:

- Feature tests for every route, CSRF, redirects, unauthorized responses, rendered forms, fixture UI reachability, and validation preservation.
- Active/inactive Ground and Player filtering, forged IDs, duplicate participants, and branch isolation.
- Tournament date boundaries, configured-timezone input, fixture conflicts, and Ground self-exclusion.
- Lifecycle transitions, cancellation/reopening, locking, correction reasons, and idempotency.
- Participant history, selection approval, Match linkage correction, and audit metadata.
- Parent/Player/Coach visibility, reports/exports, attendance, scorecards, performance, and shared-branch behavior.

The database test's destructive cleanup makes it unsuitable for the persistent mcc_cricket_club_test dataset without an approved safe replacement.

## 10. Conflicts between code and documented requirements

- REQUIREMENTS.md keeps Tournaments under future/advanced scope and marks REQ-ADV-001 as NEEDS_DECISION, while routes, migrations, and tests present a working CRUD slice.
- REQUIREMENTS-REVIEW.md describes a Tournament owning competition details, fixtures, awards, certificates, and selection status; current code implements only basic Tournament/participant/fixture/standings data.
- REQUIREMENTS-REVIEW.md explicitly leaves Tournament hierarchy, shared Tournaments, selection approval, and certificate ownership undefined.
- DECISIONS.md BR-05 says Tournaments are global when shared and Matches may support participating branches, but current tables require one Tournament branch and constrain linked Matches/Grounds to that branch.
- DECISIONS.md leaves MT-01 through MT-06 as NEEDS_DECISION; current direct status, participant, fixture, and result writes should not be treated as approved lifecycle policy.
- Current role grants give Coaches Tournament create/update access, but no decision record establishes that final authority.
- Direct comparison with docs/SRS-original.md is unavailable because the file is absent.

## 11. Remaining NEEDS_DECISION items

At minimum:

- MT-01: Tournament scope and competition types; standalone versus Tournament-owned Match.
- MT-02: Shared/global Tournament scope, participating branches, and historical visibility.
- MT-03: Match/Tournament relationship and hierarchy.
- MT-04: Opponent/team representation and uniqueness.
- MT-05: Player registration, eligibility, selection authority, Playing XI approval, correction, and locking.
- MT-06: Coach/official assignment authority, branch scope, reassignment, and history.
- Tournament lifecycle/status transitions, cancellation, reopening, completion timing, reasons, and terminal behavior.
- Venue/Ground ownership, external venue support, and conflict/override rules.
- Date/time input, timezone, boundaries, and fixture-in-Tournament-date policy.
- Scorecard/innings structure; batting, bowling, fielding, extras, precision, and missing-data rules.
- Result/winner, tie, abandoned, rain-cancelled, and no-result behavior.
- Player/Coach performance and Attendance integration, source approval, formulas, visibility, and recalculation.
- Awards, MVP, certificates, media, retention, and document linkage.
- Reports, exports, freshness, audit, and role visibility.
- Correction, locking, retention, and historical recalculation policy.
- Final role permissions, including whether Coach may create/update Tournaments and fixtures.

No client approval for these items was found.

## 12. Migration and deployment dependencies

- 20260904000400_CreateTournamentManagement must run after its branch, Ground, Player, Match, role, and permission dependencies.
- Match schema migration must precede the Tournament migration because tournament_fixtures.match_id references matches.
- Existing deployment must have branches, Grounds, Players, users, roles, and role permissions before options are useful.
- Applying the migration grants Tournament permissions to selected roles; sessions may need refresh after permission changes.
- The only discovered Tournament database test is destructive and must not run against the persistent test database.
- No safe Tournament-specific seeder, scanner, export, backup, or production deployment evidence was found.
- Scorecard, selection, cross-branch, performance, attendance, exports, and media work would require additional schema and deployment decisions.

## 13. Prioritized implementation sequence

1. Reconcile MT-01–MT-06 and the Tournament lifecycle/role decisions; keep all unresolved workflows blocked.
2. Replace the destructive Tournament integration test with an insert-only, uniquely identified test fixture strategy.
3. If CRUD remains authorized, fix the narrow baseline defects first: active/eligible option filtering, field-specific validation preservation, configured-timezone date handling, fixture date bounds, and a real fixture action/form.
4. Reconcile participant replacement and audit/history policy before changing persistence semantics.
5. Implement only approved Tournament/Match relationship and branch model.
6. Add approved lifecycle/locking/correction workflows.
7. Add scorecard and performance/attendance integrations only after their sources, formulas, visibility, and audit rules are approved.
8. Add reports/exports/media/certificates only after retention, sensitive-data, and deployment policies are approved.

## 14. Smallest safe first coding slice

The smallest safe slice is **not a new competition feature**. It is a decision-gated CRUD hardening slice:

- Replace the destructive Tournament database test with a persistent unique-fixture test.
- Add server-side active/eligible filtering for existing Ground and Player options.
- Preserve form values and return field-specific errors for existing create/edit fields.
- Validate fixture scheduled dates against the existing Tournament date range, only if that existing baseline is confirmed as intended.
- Add focused Feature and database coverage for route permissions, CSRF, branch isolation, forged IDs, and validation redirects.

This recommendation does not approve any unresolved Tournament policy. Until the applicable decisions are explicitly reconciled, do not implement scorecards, innings, selection approval, performance, attendance, shared competition, advanced statuses, exports, awards, certificates, or media.

