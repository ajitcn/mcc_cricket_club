# Match-Tournament Ownership and Result Source-of-Truth Decision Request

Status: Awaiting client decision  
Review date: 2026-10-11  
Prepared: 2026-10-11  
Scope: Match, Tournament Fixture, Match Player Performance, and standings linkage

## Approval record

| Field | Value |
| --- | --- |
| Decision status | `NEEDS_DECISION` |
| Decision |  |
| Approval choice |  |
| Approver |  |
| Approval date |  |
| Effective date |  |
| Approval comments |  |

User-provided direction has now been supplied, but formal client approval has
not been provided. The required approval choice, approver name, approval date,
effective date, and approval comments are missing. All seven items remain
`NEEDS_DECISION`; the recorded direction is not client approval and does not
authorize implementation changes.

## Evidence and current boundary

The current implementation supports independent Match records and
`TournamentFixture -> Match` linkage through an optional `match_id`. Match
completion is managed by `MatchManagementService`; Fixture status and standings
are managed by `TournamentManagementService`. Completed Fixtures currently feed
read-time standings from stored points and a linked completed Match result.

Existing evidence does not establish a scorecard or innings workflow, automatic
Tournament qualification, an approved standings formula, registration or
selection policy, or a canonical relationship between Match Player Performance
and Tournament standings.

The following alternatives are secure-policy options only. Every option and
secure default below is labeled:

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL**

## Decisions requested

### Reconciliation statement

The reviewed documents contain no completed client approval for Match
ownership, Fixture ownership, Match/Fixture cardinality, Match status
authority, Match-result authority, Fixture-points authority, correction,
reopening, or cross-branch competition. Existing source, test evidence, and
the direction recorded below do not substitute for approval.

### Recorded direction

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL**

The requested flow direction is: a Tournament schedules Fixtures, Fixtures may
lead to Matches, and verified completed Match results feed Tournament
standings. The selected planning resolutions are:

1. Standalone Matches are allowed. A Fixture may link to one Match, and a
   Match may link to only one Tournament Fixture.
2. The Fixture owns Tournament scheduling, round, date/time, Ground, and
   Fixture status. The linked Match owns the cricket result and completion
   details.
3. A verified completed Match result is authoritative for winner, tie,
   no-result, and Match completion. Fixture status does not replace the Match
   result.
4. Standings points are calculated only from a completed, verified Match linked
   to the Fixture. Scheduled, postponed, cancelled, and incomplete Fixtures
   contribute no points.
5. Postponed Fixtures may be rescheduled with audit history. Cancelled
   Fixtures are retained and contribute no Match result or standings points.
6. Result corrections require authorized permission, a correction reason,
   optimistic version check, before/after audit metadata, and a transaction.
   Completed Match results and awarded points cannot be changed through
   ordinary editing.
7. Tournament, Fixture, Match, Ground, Opponent, and Player references remain
   within the authorized branch. No implicit cross-branch links are allowed.

**Decision status for items 1-7:** `NEEDS_DECISION`  
**Approval choice:**   
**Approver:**   
**Approval date:**   
**Effective date:**   
**Approval comments:**   

### 1. Match independence and ownership

**Question:** May a Match exist independently of a Tournament?

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL — Secure default:** Yes. A
Match may be a standalone friendly, practice, or other authorized Match. A
Fixture may optionally reference it only after same-branch validation.

**Alternative:** Every Match must belong to exactly one Tournament Fixture.

**Decision:**  
**Rationale:**  

**Question:** Does a Fixture own the Match, or does a Match own the Fixture?

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL — Secure default:** The Fixture
owns the competition scheduling relationship and may reference one completed
Match. The Match remains an independent domain record and does not gain an
implicit Tournament owner.

**Alternative:** The Match owns the Fixture through a required direct
Tournament/Fixture relationship.

**Decision:**  
**Rationale:**  

### 2. Cardinality and linkage

**Question:** May a Fixture link to only one Match?

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL — Secure default:** Yes. One
Fixture has at most one result Match. A second Match requires an explicit
correction/replacement workflow and an audit event; it must not silently
overwrite the existing reference.

**Alternative:** A Fixture may link to multiple Matches for abandoned/replayed,
two-leg, or multi-innings competition rules.

**Decision:**  
**Rationale:**  

**Question:** May a Match be linked to more than one Tournament?

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL — Secure default:** No. A Match
may be linked to at most one Fixture and therefore at most one Tournament.
Reuse requires a new explicitly approved relationship rather than duplicate
standings contribution.

**Alternative:** A Match may be reused by multiple Fixtures/Tournaments with
explicit participation identity and standings deduplication rules.

**Decision:**  
**Rationale:**  

### 3. Status and completion authority

**Question:** Which record is authoritative for Match status and completion?

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL — Secure default:** The Match is
authoritative for Match lifecycle status. A Fixture may be completed only when
it references a Match whose status is `completed`; completing a Fixture must
not silently complete the Match.

**Alternative:** Fixture completion is authoritative and atomically completes
the linked Match.

**Decision:**  
**Rationale:**  

**Question:** What is the allowed relationship between Fixture status and Match
status?

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL — Secure default:** A postponed
or cancelled Fixture does not change the linked Match status. A completed
Fixture requires a completed Match. A cancelled Fixture cannot contribute to
standings.

**Alternative:** Postponement or cancellation automatically postpones or
cancels the Match, subject to explicit transition and audit rules.

**Decision:**  
**Rationale:**  

### 4. Result and standings authority

**Question:** Which record is authoritative for result, winner, tie, no-result,
and points?

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL — Secure default:** The linked
completed Match is authoritative for the Match result and outcome. Fixture
points are a Tournament projection recorded only through an authorized Fixture
completion/correction action. Standings consume only completed, non-cancelled
Fixtures and do not infer formulas that have not been approved.

**Alternative:** Fixture points and outcome are authoritative, with the Match
result treated as descriptive evidence.

**Decision:**  
**Rationale:**  

**Question:** How do completed Match results update Tournament standings?

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL — Secure default:** Completion
of a valid Fixture reads the linked completed Match result, validates the
Fixture branch and outcome inputs, persists the authorized points projection,
and makes the Fixture eligible for read-time standings. Repeated completion is
idempotent; corrections require authorization, a reason, audit, and
optimistic-concurrency protection.

**Alternative:** Standings are recalculated from a future scorecard/innings
source rather than stored Fixture points.

**Decision:**  
**Rationale:**  

### 5. Corrections and reopening

**Question:** May a completed Match or Fixture be reopened?

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL — Secure default:** No ordinary
reopening. An authorized correction may update the result projection with a
required reason, expected current version, transaction, and audit event. A
correction must not create duplicate standings credit.

**Alternative:** Authorized users may transition a completed record back to
scheduled/in-progress with a reason and a complete recalculation workflow.

**Decision:**  
**Rationale:**  

**Question:** How are stale corrections rejected?

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL — Secure default:** Require an
expected version or equivalent compare-and-swap condition for completed Match,
Fixture, result, and points corrections. A stale request fails without partial
writes and reports a safe conflict message.

**Alternative:** Last-write-wins, with audit only.

**Decision:**  
**Rationale:**  

### 6. Branch ownership and cross-branch access

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL — Secure default:** Match,
Tournament, Fixture, Ground, Opponent, Coach, Player, and performance records
must share the authorized branch. Cross-branch Fixture-to-Match linkage is
rejected server-side. Any future shared Tournament must have an explicit
branch-scope policy and permission review.

**Alternative:** A global Tournament may link Matches from multiple branches
through an approved competition scope and explicit cross-branch permissions.

**Decision:**  
**Rationale:**  

### 7. Security, audit, and concurrency

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL — Secure default:** Preserve
server-side permissions, CSRF on browser POST forms, branch authorization,
transactions, and append-oriented audit records. Candidate existing permission
families are `match.view`, `match.create`, `match.update`, `tournament.view`,
and `tournament.update`; no new permission is approved by this request.

Candidate audit events include Match completed/corrected, Fixture linked,
Fixture completed/corrected, Fixture postponed, Fixture cancelled, standings
projection changed, and stale correction rejected. Exact names require the
approved implementation design.

**Decision:**  
**Rationale:**  

### 8. Migration and data-repair impact

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL — Secure default:** Prefer
service validation and existing nullable linkage semantics before schema
changes. Do not rewrite migration history or repair production data as part of
this decision request. Any new constraint, version column, uniqueness rule, or
backfill requires a separate migration and data-repair plan.

**Alternative:** Add a mandatory ownership relation, uniqueness constraint, or
result snapshot table immediately.

**Decision:**  
**Rationale:**  

### 9. Test acceptance criteria

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL — Secure default:** Acceptance
must include insert-only, branch-isolated fixtures and prove:

- Standalone Match creation remains possible if approved.
- Fixture-to-Match cardinality and same-branch restrictions.
- Match and Fixture status transition authority.
- Result, winner, tie, no-result, and points source-of-truth behavior.
- Postponement and cancellation behavior.
- Completed Fixture standings inclusion and cancelled/postponed exclusion.
- Idempotent completion and duplicate-credit prevention.
- Correction reason, optimistic concurrency, stale rejection, rollback, and audit.
- Permissions, CSRF, unauthorized branch access, and safe error handling.
- UTC persistence and configured MCC-local display/input behavior.

**Decision:**  
**Rationale:**  

### 10. Reporting, exports, notifications, and portal impact

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL — Secure default:** Do not
expand reports, exports, notifications, or Parent/Student portal projections
until ownership, result authority, standings formulas, privacy, and branch
visibility are approved. Existing bounded views may continue to display their
current authorized data.

**Alternative:** Publish completed Match/Tournament results immediately to
reports, exports, notifications, or portals with separate visibility rules.

**Decision:**  
**Rationale:**  

## Explicitly preserved exclusions

This request does not approve or authorize:

- Scorecards or innings.
- Automatic Tournament qualification.
- New or unapproved standings formulas.
- Registration, team selection, or Playing XI policy changes.
- Exports, notifications, or new permissions.
- Application code, routes, migrations, database changes, tests, or data repair.

## Decision outcome

Until the fields above are completed and approved, Match-Tournament linkage is
decision-gated. Existing bounded behavior may remain unchanged, but no new
ownership, standings, correction, or reporting behavior should be implemented.
