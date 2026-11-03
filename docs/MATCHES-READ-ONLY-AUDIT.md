# Matches Module — Read-Only Audit

**Audit date:** 2026-09-30  
**Scope:** Source and documentation review only. No application code, tests, migrations, seeders, database records, permissions, or existing decisions were changed. No migrations or destructive commands were run.

## Evidence and governance boundary

This audit distinguishes repository evidence from authorization:

- **Implementation evidence** is limited to the current source, migrations, seeders, and tests.
- **Documented approval** is reported only where the decision register explicitly records it. Existing code is not treated as approval.
- **User direction** is not inferred from implementation. No new Match direction was supplied during this audit.
- **Unresolved items** remain `NEEDS_DECISION`.

The requested `docs/SRS-original.md` file is not present in the current tree. The available SRS evidence is `docs/SRS.md`, `docs/REQUIREMENTS-REVIEW.md`, the requirements/decision registers, and the traceability map. This missing-file condition is recorded as an audit limitation; the original SRS was not created or modified.

## 1. Implemented Match functionality

The Matches module currently provides a narrow match-record workflow:

- Branch-scoped list with search, local-calendar date, status, and match-type filters, pagination, and an empty state.
- Create and edit forms for branch, optional ground, optional Coach, opponent, match type, status, start/end, squad, Playing XI, score/result summary, and Coach remarks.
- Match detail page with branch, ground, Coach, type/status, start, result/score summary, squad/Playing XI, remarks, and audit history.
- Squad and Playing XI are persisted in `match_players`; Playing XI must be a subset of the squad and is capped at 11.
- Ground availability and scheduled session/match conflicts are checked. Coach assignment is checked against branch role assignment, SchedulingService availability/leave/conflict rules, and match conflicts.
- Match writes use a transaction, resource locks for Coach/ground, branch authorization, and the central audit persistence service.
- Match audit history is branch-filtered and presented on the detail page.

The implementation does **not** provide scorecards, per-player statistics, MVP handling, media, tournament ownership, match attendance, match-specific performance records, cancellation workflow, deletion/retirement, or export functionality.

## 2. Existing routes and screens

`app/Modules/Matches/Config/Routes.php` defines explicit authenticated routes:

| Method | Route | Handler | Filter |
|---|---|---|---|
| GET | `/matches` | `Browser::index` | `permission:match.view` |
| GET | `/matches/new` | `Browser::createPage` | `permission:match.create` |
| POST | `/matches` | `Browser::create` | `permission:match.create` |
| GET | `/matches/{id}/edit` | `Browser::editPage/$1` | `permission:match.update` |
| GET | `/matches/{id}` | `Browser::showPage/$1` | `permission:match.view` |
| POST | `/matches/{id}` | `Browser::update/$1` | `permission:match.update` |

There are no explicit delete, cancel, status-transition, scorecard, selection-confirmation, attendance, export, or media routes. Navigation exposes Matches under both the Coaching group and the Matches & Tournaments group through `NavigationService`, subject to `match.view`.

The browser controller is thin and delegates persistence to `MatchManagementService`. It redirects successful writes to the match detail page and returns failures through a back redirect with input and a safe flash error.

## 3. Database tables and relationships

Migration `app/Database/Migrations/20260904000300_CreateMatchManagement.php` creates:

### `matches`

- `branch_id` → `branches.id` (`RESTRICT`)
- Optional `ground_id` → `grounds.id` (`SET NULL`)
- Optional `coach_user_id` → `users.id` (`SET NULL`)
- `created_by` → `users.id` (`RESTRICT`)
- Opponent, match type, status, UTC-style start/end `DATETIME`, optional result, score summary, Coach remarks, and timestamps.
- Indexes for branch/date and ground/time conflict queries.

### `match_players`

- Composite key `(match_id, player_id)`.
- `match_id` cascades from `matches`; `player_id` restricts deletion of a player.
- `selection_type` stores `squad` or `playing_xi`.

`MatchModel` and `MatchPlayerModel` use these columns. There is no tournament foreign key on `matches`; the tournament migration instead optionally links `tournament_fixtures.match_id` to a match. No match-specific attendance, scorecard, performance, MVP, media, or version/locking tables exist.

The migration is forward-only (`down()` intentionally does not remove schema). It also inserts `match.view`, `match.create`, and `match.update` for Super Admin, Branch Admin, and Coach when those roles exist. No Match-specific migration or seed evidence beyond this was found.

## 4. Permissions and branch-scope enforcement

The centralized route filters use `match.view`, `match.create`, and `match.update`. `MatchManagementService` repeats authorization with `AuthorizationService::can(permission, branch)`:

- List and form options are restricted to branches authorized for the relevant permission.
- Detail reads authorize the match branch before loading related data.
- Creates authorize the submitted branch; edits authorize the existing branch and reject branch changes.
- Ground joins and validation include both ground ID and match branch.
- Coach validation requires a current `user_branches` assignment and Coach/Fitness Coach role in the match branch.
- Player selection requires the Player’s `branch_id` to match the match branch.
- Audit history is filtered by entity type, entity ID, and branch.
- Player 360 match reads check `match.view` and the requested Player branch.
- Tournament fixture reads/writes constrain joined matches and grounds to the tournament branch.

`AuthorizationRestoreSeeder.php` includes Match permissions in its role catalogs. The code does not establish a separate Match-specific correction/verification permission. The migration grants Match create/update to Coach as well as administrators; whether that is the intended final authority is not resolved by the Match decision records.

## 5. Confirmed defects and gaps

The following are source-confirmed implementation gaps or inconsistencies, independent of unresolved product policy:

1. **Option queries are broader than the form labels imply.** `formOptions()` returns all branch Players without filtering active status or any approved match eligibility state. Coach options are filtered by role and branch assignment but do not explicitly filter an active/enabled user field. The write path repeats branch and role checks, but accepts any branch-matching Player. If active/eligible selection is required, the current implementation does not enforce it.
2. **Generic validation responses lose field-level detail.** `Browser::save()` catches every exception and flashes only the service message. `validatedMatch()` collapses several malformed inputs into `Invalid match.`; there is no field-level error data or preservation contract specific to each field.
3. **The form uses combined start/end datetime text inputs.** `form.php` labels them `Start` and `End`, uses a datepicker-preserve-time attribute, and submits one value per field. This is inconsistent with the project’s later separate date/time form convention and makes the service require an explicit timezone suffix. The implementation’s hard-coded `Asia/Kolkata` calendar helper is not the same as reading the configured application timezone.
4. **Match status is directly editable.** Any user with `match.update` can submit `scheduled`, `completed`, or `cancelled`; there is no transition matrix, cancellation reason, completion rule, locking, or correction reason. This may be a policy gap rather than a defect until MT-04 and lifecycle decisions are resolved.
5. **Selection replacement is destructive within a transaction.** When selection keys are submitted, the service deletes all existing `match_players` rows and recreates them. There is no append-only selection history or correction audit. Whether replacement is allowed is unresolved; this is a data-integrity risk if historical selection must be preserved.
6. **Audit metadata is only a before/after summary.** The detail view exposes a disclosure containing encoded before/after values, but there is no Match-specific reason requirement, field-level history, or scorecard locking evidence.
7. **No Match feature-test layer exists.** Only a unit contract test and a database integration test were found for Matches. There is no Match Feature test covering route filters, rendered forms, CSRF, redirects, or authorization responses.

The following are **not** claimed as defects because the governing decisions are unresolved: tournament ownership, cross-branch participation, scorecard authority, lifecycle transitions, selection approval, awards, media, and Match performance formulas.

## 6. UI inconsistencies

Source review found these Match-specific inconsistencies against the shared application patterns:

- `index.php` places Filter and Create controls together in the filter row rather than using the standard page-header action toolbar used by newer detail/list pages.
- `form.php` uses a plain page heading, compact inline grid controls, combined datetime fields, large native multi-selects for squad/Playing XI, and no consistently rendered field-level validation blocks.
- `show.php` uses a plain definition list and an inline Edit link rather than the shared page header, cards, badges, action grouping, and responsive detail layout.
- Match history renders a disclosure with raw JSON-like before/after summaries rather than the application’s readable metadata presentation convention. Values are escaped, but the presentation remains difficult to scan and has no explicit sensitive-key treatment at this module boundary.
- Tables and action links rely on shared classes in places, but the form/detail sections do not consistently use shared cards, button variants, empty-state structure, or responsive action groups.

These are UI/source findings only. No browser inspection was performed and no CSS was changed during this audit.

## 7. Missing validation and security checks

Confirmed from source:

- Active Player status/eligibility is not checked when loading or persisting squad members.
- Coach active/enabled state is not explicitly checked; role and branch assignment are checked.
- The form’s datetime parser requires an explicit timezone suffix, while the visible form presents ordinary date/time-style values and the rest of MCC uses configured local date/time handling. This can reject otherwise valid user input and is not aligned with the configured-timezone contract.
- Validation errors are not mapped back to individual fields.
- There is no dedicated Match status-transition authorization or reason validation.
- There is no Match-specific attendance or scorecard integrity check.
- No explicit method-level CSRF assertion exists in the Match controller; browser POST protection depends on the project’s global CSRF filter/configuration and form token. A focused route test should verify this boundary.

Existing protections that should be preserved include centralized permission filters, service-level branch checks, query branch predicates, escaped output, transaction use, resource locks, and audit persistence.

## 8. Missing tests

Current Match coverage:

- `tests/Unit/MatchManagementContractTest.php`: route/service contract strings, branch-scoped joins, transaction/lock/audit indicators, and basic view markers.
- `tests/database/MatchManagementIntegrationTest.php`: CRUD, persisted Playing XI selection, invalid XI rejection, and cross-branch denial using persistent synthetic fixtures.

Missing focused coverage includes:

- Feature tests for every explicit route, permission filter, CSRF field, unauthorized response, validation redirect, and selected-value preservation.
- Active/inactive/eligible Player and Coach option filtering and forged-ID rejection.
- Ground authorization, inactive ground rejection, unavailability, and self-exclusion on edit.
- Local date/time input, configured-timezone conversion, malformed dates, and boundary filtering.
- Status transitions, cancellation/completion rules, reason requirements, and idempotency (currently unresolved policy).
- Selection replacement/history behavior and duplicate retry handling.
- Audit field semantics, sensitive metadata masking, and history authorization.
- Match/tournament relationship, cross-branch participation, scorecard locking, performance, attendance, exports, and reporting (all require decisions first).

## 9. Conflicts between code and documented requirements

- `REQUIREMENTS-REVIEW.md` describes a broader Match scope: venue, tournament, scorecard, player performance, MVP, gallery, and video. Current code implements only a subset (ground, selection, result text, and remarks).
- The requirements review explicitly says the Match/tournament relationship, scoring depth, editing rights, and selection rules are undefined. Current code nevertheless allows direct status/result/selection edits under `match.update` without a documented transition or verification layer.
- `DECISIONS.md` records BR-05 as approved: tournaments are global when shared and matches may support participating branches. The current schema has one required `branch_id` on `matches` and no participating-branches relation. This is an implementation/schema mismatch with the broad BR-05 statement; MT-02 remains unresolved in the decision index and the actual cross-branch model is not defined.
- `DECISIONS.md` marks MT-01, MT-02, MT-03, MT-04, MT-05, and MT-06 as `NEEDS_DECISION`. These unresolved decisions govern whether the current direct fields and branch model are sufficient for the intended product.
- `REQUIREMENTS.md` labels Matches & Tournaments as future scope, while traceability marks Match Management “Implemented with limitations.” This is a documented readiness/status conflict, not evidence that the remaining policies are approved.
- `docs/SRS-original.md` is absent from the tree, so direct comparison to the original file could not be completed.

## 10. Features still marked `NEEDS_DECISION`

The decision register and requirements review leave these Match-related policies unresolved:

- **MT-01:** Whether a Match must belong to a Tournament or may stand alone.
- **MT-02:** Shared competition scope and the data model for multi-branch participation.
- **MT-03:** Playing XI authority, selection workflow, approval, and correction.
- **MT-04:** Scorecard authority, verification, locking, correction, and history.
- **MT-05:** MVP and selection-status rules.
- **MT-06:** Tournament hierarchy and relationships among tournaments, fixtures, tours, events, and matches.
- Match status lifecycle, cancellation/completion timing and reasons, deletion/retirement, and historical edit policy.
- Scorecard fields, innings/metric grain, player-performance source data, formula/rounding, and correction authority (also linked to PF-01 through PF-04).
- Match attendance ownership and denominator behavior, if attendance is required for competitions.
- Media/photo/video storage, privacy, retention, scanning, and download policy.
- Match report/export formats, columns, audit requirements, and role visibility.
- Cross-branch Coach/Player participation and historical visibility beyond the current single-branch record.
- Final Match RBAC by role, including whether Coach may create/update, who verifies/completes/cancels, and any narrow permissions required.

## 11. Deployment and migration dependencies

- Migration `20260904000300_CreateMatchManagement` must be applied before the module can persist Matches. It creates both Match tables and inserts role permissions. This audit did not inspect or change a live migration status and did not apply it.
- The tournament migration depends on `matches` for `tournament_fixtures.match_id`; migration ordering must keep Match creation before Tournament creation.
- Seed data must provide roles, users, branches, grounds, and Players before the forms produce useful options. The integration test expects synthetic branch codes `TEST_MCC_VNS` and `TEST_MCC_DEL`, a converted test Player, and a Super Admin test user.
- Session/Coach availability checks depend on the existing Scheduling schema and service. A deployment must have those tables, assignments, and leave/availability data in a consistent state.
- Permission grants are loaded into sessions at login; after applying the Match permission migration or restoring grants, users may need to sign out and back in. No production or test database change was made during this audit.
- No deployment-owner evidence was found for Match scorecard, media, export, or cross-branch operational policy because those items remain unresolved.

## 12. Highest-priority safe next implementation slice

The smallest safe next step is **a Match decision reconciliation and security-boundary slice before expanding workflows**:

1. Obtain explicit decisions for MT-01/02/03/04/05/06 and the Match lifecycle, including status transition authority, selection correction/history, scorecard verification/locking, branch participation, and whether Matches integrate with attendance/performance.
2. Until those decisions are recorded, add no scorecard, tournament, performance, MVP, media, export, cancellation, or cross-branch workflow.
3. After approval, implement the narrowest confirmed security slice first: branch-scoped active/eligible Coach and Player option queries, server-side forged-ID rejection, configured-timezone separate date/time handling, field-level validation/redirect preservation, and Feature/database tests. This slice must preserve the existing transaction, lock, authorization, and audit behavior.

**Acceptance criteria for that first slice:**

- Every form option is limited to active, branch-authorized records under the approved eligibility policy.
- Submitted IDs are revalidated server-side; inactive, forged, cross-branch, and duplicate selections are rejected with field-specific errors.
- Date and time are separate local MCC inputs and round-trip through the configured timezone/storage convention.
- Create and edit errors return to the same form with values and errors preserved; successful writes remain transactional and audited.
- Route, CSRF, permission, branch-isolation, and Feature/database tests cover both authorized and denied paths.

This is a recommendation based on current evidence, not a product approval and not an implementation authorization.
