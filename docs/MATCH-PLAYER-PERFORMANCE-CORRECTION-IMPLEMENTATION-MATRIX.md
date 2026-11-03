# Match Player Performance Correction Implementation Matrix

**Status:** Decision-gated planning artifact  
**Review date:** 2026-10-11  
**Approval status:** `NEEDS_DECISION`  
**Decision request:** `docs/MATCH-PLAYER-PERFORMANCE-CORRECTION-DECISION-REQUEST.md`

This matrix translates the correction decision request into implementation
options and acceptance checks. It is not implementation authorization.

Every secure default and alternative is explicitly labeled:

`USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL`

## Approval record

| Field | Value |
| --- | --- |
| Selected decision |  |
| Resolution |  |
| Approver |  |
| Approval date |  |
| Effective date |  |
| Approval comments |  |

## Decision reconciliation

**Reconciliation date:** 2026-10-11  
**Formal approval status:** `NEEDS_DECISION`

No formal client approval has been supplied for the correction or version
policy. The selected defaults below are recorded as user direction only.
Selected decision, resolution, approver, approval date, effective date, and
approval comments have not been supplied. All rows in the matrix therefore
remain `NEEDS_DECISION`, including correction authority, correction reason,
optimistic concurrency, shared or separate versions, stale-form behavior,
idempotency, permissions, branch rules, audit metadata, and rollback.

Secure defaults and alternatives are not approval and remain labeled:

`USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL`

## Selected defaults recorded as user direction

These selections are planning direction only. They do not complete the formal
approval record, and the related matrix rows remain `NEEDS_DECISION`.

| Area | Selected default | Status |
| --- | --- | --- |
| Correction authority | Existing Match Player Performance update permission within the authorized branch. | `NEEDS_DECISION` |
| Correction reason | Required for every update to an existing batting, bowling, or fielding record. | `NEEDS_DECISION` |
| Optimistic concurrency | Submitted version must match the current record version for every correction. | `NEEDS_DECISION` |
| Version granularity | One shared version per Match Player Performance record. | `NEEDS_DECISION` |
| Stale form behavior | Reject safely, preserve the current record, and require reload before retry. | `NEEDS_DECISION` |
| Idempotency | Repeated first-time submissions do not create duplicate performance records. | `NEEDS_DECISION` |
| Permissions, branch, audit, and rollback | Preserve existing permissions and strict branch checks; use CSRF, transactions, before/after audit metadata, and rollback on failure. | `NEEDS_DECISION` |

**Direction:** `USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL`  
**Approver:**  
**Approval date:**  
**Effective date:**  
**Comments:** Secure defaults selected by user instruction.

## Decision matrix

| Decision area | Secure default — USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL | Alternative | Implementation impact if approved | Migration/data impact | Acceptance evidence | Status |
| --- | --- | --- | --- | --- | --- | --- |
| Completed-Match correction | Allow authorized corrections | Immutable rows or administrative amendment | Add explicit correction path; ordinary editing remains restricted | Depends on approved history/version model | Authorized correction and terminal-state tests | `NEEDS_DECISION` |
| Correction reason | Require a non-blank reason for every changed existing row | Field-specific or privileged-only reason | Preserve reason validation and browser field feedback | None | Missing/valid reason tests | `NEEDS_DECISION` |
| Optimistic concurrency | Require submitted version to equal stored version | Last-write-wins or merge | Compare-and-swap update and stale rejection | Version field or approved alternative | Two-writer stale test | `NEEDS_DECISION` |
| Version granularity | One shared version per Match Player Performance row | Batting/bowling/fielding versions or Match-wide version | Define increment and form payload semantics | One or multiple fields/tables | Version increment and stale field tests | `NEEDS_DECISION` |
| Stale version | Reject, preserve data, return safe reload message | Merge or privileged override | Controller/view stale-state handling | None beyond version storage | No-partial-write stale test | `NEEDS_DECISION` |
| First-time idempotency | Same first-time payload creates one row and one audit event | Client idempotency key or repeat event | Preserve existing unchanged-save behavior | Possible key/index only if approved | Retry/idempotency test | `NEEDS_DECISION` |
| Permissions | Preserve `match.update` for active Matches and `performance.update` for completed corrections | Add `performance.correct` or admin-only permission | Update RBAC only if explicitly approved | Permission seed/migration possible | Permission denial and grant tests | `NEEDS_DECISION` |
| Branch isolation | Match, Player, performance, references, actor, and audit remain same-branch | Explicit shared-competition model | Keep service branch predicates and reject forged IDs | New relationship schema possible | Cross-branch batting/bowling/fielding tests | `NEEDS_DECISION` |
| CSRF and transactions | CSRF-protected POST plus atomic performance/version/audit transaction | API-specific or split transactions | Preserve controller/filter and service transaction boundary | None normally | CSRF and rollback tests | `NEEDS_DECISION` |
| Audit metadata | Actor, branch, reason, versions, and safe before/after metadata | Summary-only or separate history table | Extend existing audit event only within approved data limits | New history table only if approved | Audit completeness and sensitive-data tests | `NEEDS_DECISION` |
| Correction rollback | Any failure preserves the original row/version and writes no partial audit | Compensating repair or partial update | Ensure transaction catches validation, stale, and DB failures | None or repair structure if approved | Genuine DB-failure rollback test | `NEEDS_DECISION` |
| Stale-form UI | Preserve safe input, show stale message, require reload | Discard input or override action | Add stale error mapping and form state | None | Feature stale-form route test | `NEEDS_DECISION` |
| Migration | Forward-only, idempotent version migration after schema approval | No version field or separate history | Add only approved schema changes | Backfill/default and deployment plan required | Migration contract/status/data-preservation tests | `NEEDS_DECISION` |
| Reports/exports | No new outputs | Publish correction details | No bounded reporting change | None | Existing authorized output remains unchanged | `NEEDS_DECISION` |
| Portal/notifications | No new projections or notifications | Publish to approved recipients | Requires separate privacy, recipient, and consent rules | Provider/config/schema impact possible | Explicit downstream tests | `NEEDS_DECISION` |

## Security acceptance matrix

| Control | Required acceptance if approved |
| --- | --- |
| Authorization | Server-side permission checks are applied to every read and correction. |
| Branch isolation | Cross-branch Match, Player, dismissal, fielder, performance, actor, and audit references are rejected without data leakage. |
| CSRF | Browser state-changing forms remain CSRF protected. |
| Transactionality | Performance data, version changes, and audit metadata commit or roll back together. |
| Concurrency | Stale submissions are rejected according to the approved version model. |
| Idempotency | Repeated identical first-time submissions do not duplicate rows or audit events. |
| Audit | Before/after metadata and reason are recorded without passwords, tokens, or unnecessary sensitive data. |
| Output safety | Stale messages, submitted values, notes, reasons, and history remain escaped and authorized. |

## Deferred boundaries

This matrix does not authorize Match ↔ Tournament ownership, Fixture/Match
linkage, standings, scorecards, innings, qualification, registration,
selection governance, exports, notifications, portal behavior, or new
permissions. Those remain governed by their own decision records and retain
`NEEDS_DECISION` where approval is absent.

No selected decision, resolution, approver, approval date, effective date, or
approval comments have been supplied. The current implementation remains
unchanged until formal approval is recorded.
