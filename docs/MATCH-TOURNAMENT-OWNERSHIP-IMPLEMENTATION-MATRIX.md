# Match-Tournament Ownership Implementation Matrix

Status: Bounded implementation evidence; user direction remains non-approval  
Review date: 2026-10-11  
Prepared: 2026-10-11  
Approval status: `NEEDS_DECISION`

This matrix records the bounded implementation completed from the recorded
user direction. It is not client approval and does not authorize deferred
scope.

Every proposed default and alternative is explicitly labeled:

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL**

## Approval record

| Field | Value |
| --- | --- |
| Decision request | `docs/MATCH-TOURNAMENT-OWNERSHIP-DECISION-REQUEST.md` |
| Decision status | `NEEDS_DECISION` |
| Decision |  |
| Approval choice |  |
| Approver |  |
| Approval date |  |
| Effective date |  |
| Approval comments |  |

User-provided direction has now been supplied, but formal client approval has
not been provided. The required approval choice, approver name, approval date,
effective date, and approval comments are missing. Secure defaults and the
recorded direction in this matrix are planning assumptions only and must not be
treated as approval. Every ownership and source-of-truth item remains
`NEEDS_DECISION`.

## Recorded planning direction

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL**

The planning flow is: a Tournament schedules Fixtures, Fixtures may lead to
Matches, and verified completed Match results feed Tournament standings.

| Item | Recorded direction | Status |
| --- | --- | --- |
| Match ownership | Standalone Matches are allowed; a Fixture may link to one Match; a Match may link to only one Tournament Fixture. | `NEEDS_DECISION` |
| Fixture ownership | Fixture owns Tournament scheduling, round, date/time, Ground, and Fixture status; linked Match owns cricket result and completion details. | `NEEDS_DECISION` |
| Result source | Verified completed Match result is authoritative for winner, tie, no-result, and Match completion; Fixture status does not replace it. | `NEEDS_DECISION` |
| Tournament points | Points are calculated only from a completed, verified Match linked to the Fixture; scheduled, postponed, cancelled, and incomplete Fixtures contribute no points. | `NEEDS_DECISION` |
| Postponed/cancelled Fixtures | Postponed Fixtures may be rescheduled with audit history; cancelled Fixtures are retained and contribute no result or standings points. | `NEEDS_DECISION` |
| Corrections/reopening | Corrections require authorized permission, reason, optimistic version check, before/after audit metadata, and transaction; ordinary editing cannot change completed results or awarded points. | `NEEDS_DECISION` |
| Branch ownership | Tournament, Fixture, Match, Ground, Opponent, and Player references remain within the authorized branch; no implicit cross-branch links. | `NEEDS_DECISION` |

**Approver:**   
**Approval date:**   
**Effective date:**   
**Approval choice:**   
**Approval comments:**

## Current implementation baseline

| Area | Current evidence | Current limitation |
| --- | --- | --- |
| Match lifecycle | `MatchManagementService` creates, updates, and completes branch-scoped Matches | Match/Tournament ownership is not defined |
| Fixture linkage | Optional one-to-one `tournament_fixtures.match_id` linkage is enforced by the Tournament service and a unique index | Formal client approval remains absent |
| Fixture statuses | Scheduled, completed, cancelled, and postponed paths exist; rescheduling/cancellation reasons and audit are enforced | Reopening terminal records remains out of scope |
| Standings | Only Fixtures linked to completed Matches with verified results feed read-time standings | Broader formulas, scorecards, and qualification remain out of scope |
| Match Performance | Dedicated batting, bowling, and fielding records link to Match Players | No approved relationship to standings or generic Performance |
| Time handling | Match and Fixture input/display use the configured MCC application timezone and UTC persistence conventions | Broader cross-module timezone evidence remains bounded |
| Safe evidence | Focused Match/Tournament suites plus insert-only linkage tests pass: 55 tests, 523 assertions, exit code 0 | Legacy Tournament integration test remains outside the safe suite |

## Decision-to-implementation matrix

Every row remains `NEEDS_DECISION` until the corresponding decision request is
completed by an authorized approver. The secure-default column describes a
safe option; it does not select that option.

| Decision area | Secure default | Alternative | Required implementation impact if default is approved | Migration/data-repair impact | Acceptance evidence |
| --- | --- | --- | --- | --- | --- |
| Match independent of Tournament | **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL:** Allow standalone Matches | Require every Match to belong to a Fixture | Preserve nullable Fixture linkage and independent Match permissions | None expected unless existing constraints conflict | Standalone Match create/detail/complete and optional Fixture link tests |
| Ownership direction | **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL:** Fixture owns competition scheduling; Match remains independent | Match owns Fixture | Keep linkage initiated and authorized by Fixture service; prevent implicit Tournament ownership | None expected | Same-branch link, unlink/correction, and audit tests |
| Fixture cardinality | **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL:** One Fixture to at most one Match | Multiple Matches per Fixture | Enforce service-level single-link rule and explicit replacement correction | Possible unique/index review only if not already guaranteed | Duplicate-link rejection and idempotent completion tests |
| Match cardinality | **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL:** One Match to at most one Fixture/Tournament | Reuse across Fixtures/Tournaments | Reject second link; prevent duplicate standings credit | Possible unique/index review; no history rewrite | Cross-Tournament reuse rejection and branch tests |
| Status authority | **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL:** Match owns Match status; Fixture completion requires completed Match | Fixture completes Match atomically | Keep Match completion separate; validate status before Fixture completion | None expected | Status matrix, rollback, idempotency, and audit tests |
| Postponed Fixture | **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL:** Do not change Match status automatically | Automatically postpone Match | Require explicit Match action if Match changes; preserve reason/audit | None expected | Postponement leaves Match unchanged and excludes standings |
| Cancelled Fixture | **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL:** Do not change Match status automatically; cancelled Fixture is excluded | Automatically cancel Match | Reject completion and standings contribution; require explicit Match cancellation if desired | None expected | Cancellation exclusion and cross-record status tests |
| Result authority | **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL:** Completed Match result is authoritative; Fixture stores authorized points projection | Fixture result/points authoritative | Validate Match result and points at completion/correction; define mismatch handling | Possible result snapshot only if approved | Winner/tie/no-result/points consistency tests |
| Standings update | **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL:** Completed, non-cancelled Fixtures feed read-time standings | Recalculate from future scorecard/innings | Keep current bounded projection until formula is approved | None for default | Inclusion/exclusion, duplicate-credit, correction, and branch tests |
| Corrections | **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL:** Authorized correction with reason and audit | Last-write-wins or reopening | Add explicit correction path without ordinary reopening | Version field/constraint only if approved | Reason required, audit, rollback, and correction tests |
| Optimistic concurrency | **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL:** Compare expected version and reject stale writes | Last-write-wins | Add compare-and-swap to affected correction writes | Possible version-column migration | Two-writer stale rejection and atomicity tests |
| Reopening | **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL:** No ordinary reopening | Authorized completed-to-scheduled transition | Define transition, reason, standings recalculation, and audit before coding | Possible status/history migration | Reopening denial or fully specified transition tests |
| Branch ownership | **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL:** Same authorized branch for Match, Fixture, Ground, Opponent, Coach, and Players | Approved global/cross-branch Tournament | Preserve service checks; no cross-branch fallback | None expected for default | Cross-branch create, link, view, update, and completion denial tests |
| Permissions and CSRF | **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL:** Preserve existing `match.*` and `tournament.*` permissions and CSRF | New dedicated linkage permission | No new permission for default; service remains authoritative | None | Permission matrix and CSRF route tests |
| Audit | **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL:** Record link, complete, correct, postpone, cancel, and stale-rejection events | Minimal existing audit only | Define event names and metadata without sensitive data | None | Audit action, actor, branch, before/after, and reason assertions |
| Timezone | **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL:** Use configured MCC application timezone and store UTC | Retain hard-coded timezone | Implemented for the bounded Fixture path; preserve displayed local values | None expected | Existing Match/Tournament and bounded linkage tests |
| Migration strategy | **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL:** Prefer service rules and nullable existing linkage before schema changes | Add ownership/result snapshots immediately | No migration until approved data model and backfill plan exist | Production migration and repair approval required | Read-only status, forward-only migration, and insert-only data checks |
| Reporting/exports | **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL:** Keep current bounded outputs | Publish new result/standings exports | No new report/export behavior | None expected | Existing authorized output remains unchanged |
| Notifications | **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL:** No new notifications | Notify on completion/correction | Requires recipient, consent, retry, and audit policy | Provider/config impact possible | Deferred until separately approved |
| Portal | **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL:** No new Match/Tournament portal projection | Publish completed results to portal | Requires visibility, consent, branch, and sensitive-data policy | Possible projection/schema impact | Deferred until separately approved |

## Bounded implementation delivered

The following bounded slice was implemented from the recorded user direction.
It remains non-client-approved and must not be treated as a policy approval:

- Standalone Matches remain valid. A Fixture has at most one linked Match and a
  Match can be linked to at most one Fixture through a nullable link protected
  by an idempotent unique index.
- Fixture scheduling fields and status remain Fixture-owned. A linked Match
  must be completed and carry a verified result before Fixture completion.
- Standings include only completed, non-cancelled Fixtures whose linked Match
  is completed with a verified result. Repeated completion does not duplicate
  standings credit.
- Postponement and cancellation require reasons, preserve retained Fixtures,
  and write audit history. Rescheduling a postponed Fixture also requires a
  reason.
- Completed Match and Fixture corrections require existing authorization, a
  correction reason, an expected version, an atomic transaction, and audit
  metadata. Ordinary editing cannot reopen completed records.
- Same-branch checks remain enforced for Tournament, Fixture, Match, Ground,
  Opponent, Coach, and Player-related references. Existing permissions and
  CSRF controls remain in force.
- Migration `20261011000300_AddMatchTournamentLinkageVersion` adds the Match
  and Fixture version fields and the one-to-one Fixture Match index
  forward-only and idempotently. It was applied without rewriting migration
  history or destructive cleanup.

Verification: 55 tests, 523 assertions, exit code 0 across the bounded Match,
Tournament, linkage Unit, Feature, and insert-only database suites. The legacy
destructive Tournament integration suite remains excluded.

## Security acceptance matrix

| Control | Required acceptance |
| --- | --- |
| Authorization | Every read/write rechecks the authorized branch and required Match/Tournament permission server-side. |
| Branch isolation | Cross-branch Match, Fixture, Ground, Opponent, Coach, Player, and Performance references are rejected without data leakage. |
| CSRF | All browser state changes remain POST requests protected by the existing CSRF configuration. |
| Transactions | Link, completion, correction, points projection, and audit changes commit atomically or roll back together. |
| Idempotency | Repeated completion does not duplicate linkage, points, standings credit, or audit state beyond the defined event policy. |
| Concurrency | Stale corrections are rejected using an expected version or equivalent compare-and-swap; no partial writes remain. |
| Audit | Actor, branch, action, reason, relevant before/after values, and correlation context are recorded without sensitive credentials or raw secrets. |
| Timezone | Local MCC input/display and UTC storage remain consistent across cross-day boundaries. |
| Output safety | Detail, error, report, and portal projections remain escaped and authorized. |

## Test acceptance set

If implementation is later authorized, use existing focused test files where
applicable and add only bounded tests required by the approved decision:

- Match creation, completion, branch isolation, Ground/Opponent/Player
  validation, Playing XI limits, transactions, and audit.
- Match Player Performance batting, bowling, fielding, correction reason,
  membership, cancellation, transaction, branch, and audit behavior.
- Fixture create/update, one-to-one link cardinality, same-branch Match
  validation, status actions, reason validation, completion idempotency, and
  audit history.
- Result/winner/tie/no-result/points source-of-truth behavior and standings
  inclusion/exclusion.
- Postponed/cancelled Fixture behavior and linked Match preservation.
- Stale correction rejection, atomic version increments, rollback on genuine
  database failure, and duplicate-credit prevention.
- Permission denial, CSRF rejection, safe unauthorized/not-found behavior, and
  configured MCC timezone/UTC cross-day handling.

The legacy destructive Tournament integration suite must not be used against a
persistent database without separately approved insert-only fixture repair.

## Explicit non-scope and deployment gates

No scorecards, innings, automatic Tournament qualification, unapproved
standings formulas, registration or selection-policy changes, exports,
notifications, or new permissions are included.

No additional application, route, permission, migration, database, trigger,
test, or production-data expansion is authorized by this bounded matrix.
Further migration or data repair requires an approved schema decision,
forward-only migration plan, read-only status verification, backup/recovery
evidence, and deployment-owner approval.
