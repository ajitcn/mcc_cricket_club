# Match Player Performance Correction and Optimistic Concurrency Decision Request

**Status:** Awaiting client decision  
**Review date:** 2026-10-11  
**Scope:** Match Player Performance batting, bowling, and fielding correction
behavior

## Approval record

| Field | Value |
| --- | --- |
| Selected decision |  |
| Resolution |  |
| Approver |  |
| Approval date |  |
| Effective date |  |
| Approval comments |  |

No formal decision has been supplied. Every option below is a planning option
only. The secure defaults are explicitly labeled:

`USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL`

## Current implementation boundary

## Decision reconciliation

**Reconciliation date:** 2026-10-11  
**Formal approval status:** `NEEDS_DECISION`

No formal client approval has been supplied for the correction or version
policy. The selected defaults below are recorded as user direction only. The
resolution, approver, approval date, effective date, and approval comments
remain blank. Accordingly, every correction/version item in this request
remains `NEEDS_DECISION`, including correction authority, correction reason,
optimistic concurrency, version granularity, stale-form behavior, idempotency,
permissions, branch rules, audit metadata, and rollback.

Secure defaults and alternatives remain planning options only and retain the
required label:

`USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL`

## Selected defaults recorded as user direction

The following defaults were selected by user instruction for planning purposes
only. They are not formal client approval, and each item remains
`NEEDS_DECISION` until the approval record is completed.

| Area | Selected default | Formal status |
| --- | --- | --- |
| Correction authority | Authorized users with the existing Match Player Performance update permission may correct records within their authorized branch. | `NEEDS_DECISION` |
| Correction reason | Require a correction reason for every update to an existing batting, bowling, or fielding record. | `NEEDS_DECISION` |
| Optimistic concurrency | Require the submitted version to match the current record version for every correction. | `NEEDS_DECISION` |
| Version granularity | Use one shared version per Match Player Performance record across batting, bowling, and fielding changes. | `NEEDS_DECISION` |
| Stale form behavior | Reject stale submissions, preserve the current record, and ask the user to reload the latest data before retrying. | `NEEDS_DECISION` |
| Idempotency | Keep first-time submissions idempotent; repeated submissions must not create duplicate performance records. | `NEEDS_DECISION` |
| Permissions, branch, audit, and rollback | Preserve existing permissions and strict branch validation; require CSRF, transactions, before/after audit metadata, and rollback on failure. | `NEEDS_DECISION` |

**Direction:** `USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL`  
**Approver:**  
**Approval date:**  
**Effective date:**  
**Comments:** Secure defaults selected by user instruction.

The current Match Player Performance service persists batting, bowling, and
fielding rows and requires a correction reason when an existing row changes.
It uses transactions and audit metadata, but the current performance rows do
not have a version field or compare-and-swap update. No optimistic-concurrency
behavior is claimed or authorized by this request.

First-time submissions are effectively idempotent for an unchanged repeated
payload: no duplicate performance row or additional audit event is created.
This request does not approve a new idempotency protocol.

## Decisions requested

### 1. Completed-Match correction

**Question:** May performance records for a completed Match be corrected?

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL — Secure default:** Yes, but
only through an authorized correction workflow with a reason, version check,
transaction, and audit history.

**Alternative:** Completed-Match performance records are immutable; any change
requires a separate administrative amendment or replacement record.

**Selected decision:**   
**Resolution:**

### 2. Correction reason

**Question:** Must every correction include a reason?

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL — Secure default:** Yes. Reject
changed existing records when the correction reason is missing or blank.

**Alternative:** Require a reason only for selected fields or privileged
corrections.

**Selected decision:**   
**Resolution:**

### 3. Optimistic version checking

**Question:** Must a correction include the version observed by the editor?

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL — Secure default:** Yes. Reject
the write when the submitted version does not match the stored version.

**Alternative:** Last-write-wins, server-side merge, or an administrative
override workflow.

**Selected decision:**   
**Resolution:**

### 4. Version granularity

**Question:** Should batting, bowling, and fielding use one shared version or
separate versions?

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL — Secure default:** One shared
version per Match Player Performance row, so any correction to batting,
bowling, or fielding protects the complete record from stale overwrites.

**Alternative:** Separate batting, bowling, and fielding versions, or one
version for the whole Match.

**Selected decision:**   
**Resolution:**

### 5. Stale submitted version

**Question:** What should happen when the submitted version is stale?

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL — Secure default:** Reject the
write, preserve the stored record, return a safe stale-form message, and ask
the user to reload before retrying. Do not silently merge or overwrite.

**Alternative:** Automatic merge, forced reload without field recovery, or
authorized override with a separate reason and audit event.

**Selected decision:**   
**Resolution:**

### 6. First-time submission idempotency

**Question:** Should repeated identical first-time submissions remain
idempotent?

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL — Secure default:** Yes. A
retry for the same Match, Player, innings, and team side must not create a
duplicate row or duplicate audit event.

**Alternative:** Require a client idempotency key or permit a new correction
event for every retry.

**Selected decision:**   
**Resolution:**

### 7. Permissions

**Question:** Which existing permission controls corrections?

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL — Secure default:** Preserve
`performance.update` for completed-Match corrections and keep `match.update`
for non-completed performance entry. Do not add a new permission without a
separate RBAC decision.

**Alternative:** Add a dedicated `performance.correct` permission or restrict
correction to an administrative permission.

**Selected decision:**   
**Resolution:**

### 8. Branch isolation

**Question:** Which records must remain in the authorized branch?

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL — Secure default:** Match,
Player, Match Player Performance, dismissal references, fielder references,
actor authorization, and audit metadata must remain within the authorized
branch. Reject implicit cross-branch references.

**Alternative:** Explicitly approved shared competition or cross-branch Player
participation with a separate relationship model.

**Selected decision:**   
**Resolution:**

### 9. CSRF and transaction requirements

**Question:** What controls are mandatory for browser corrections?

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL — Secure default:** Keep CSRF
protection on every browser state-changing form, reauthorize server-side, and
commit the performance update, version increment, and audit event atomically.

**Alternative:** API-only correction, separate audit transaction, or a
non-transactional update.

**Selected decision:**   
**Resolution:**

### 10. Audit metadata

**Question:** What must a correction audit event contain?

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL — Secure default:** Record the
actor, branch, Match, Player, performance row, correction reason, submitted
version, stored version, and safe before/after metadata. Never log passwords,
tokens, credentials, or unnecessary sensitive data.

**Alternative:** Record only a summary event, or retain full field history in a
separate approved audit structure.

**Selected decision:**   
**Resolution:**

### 11. Correction failure and rollback

**Question:** What happens when validation, authorization, stale-version, or
database persistence fails?

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL — Secure default:** Reject the
entire correction, preserve the prior performance row and version, write no
partial audit event, and return a safe error. A successful correction commits
all related writes atomically.

**Alternative:** Partial field updates, compensating records, or an
administrative repair queue.

**Selected decision:**   
**Resolution:**

### 12. UI behavior for stale forms

**Question:** How should the browser handle a stale form?

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL — Secure default:** Return to
the Match Player Performance page with a clear stale-form message, preserve
safe submitted values where appropriate, do not expose database details, and
require reload before correction.

**Alternative:** Redirect to the Match detail page, discard all submitted
values, or provide a privileged overwrite action.

**Selected decision:**   
**Resolution:**

### 13. Migration impact

**Question:** If version checking is approved, what schema change is allowed?

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL — Secure default:** Use a
forward-only, idempotent migration only after the version granularity and
backfill/default-version behavior are approved. Do not rewrite migration
history or destructively alter existing performance data.

**Alternative:** No schema version field, database timestamp comparison, or a
separate correction-history table.

**Selected decision:**   
**Resolution:**

### 14. Reporting, exports, portal, and notifications

**Question:** What downstream behavior should corrections trigger?

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL — Secure default:** Keep
corrections within the authorized Match Player Performance workflow. Do not
add reports, exports, portal projections, notifications, or new recipient
policies as part of this decision.

**Alternative:** Publish correction history to reports, exports, portals, or
notifications under separately approved visibility and privacy rules.

**Selected decision:**   
**Resolution:**

## Test acceptance criteria

If a decision is approved, the focused test plan must cover:

- Batting, bowling, and fielding correction behavior.
- Missing and valid correction reasons.
- Shared or separate version behavior, as approved.
- Stale-version rejection with no data or audit partial write.
- First-time idempotent submission and repeated identical correction.
- Genuine database-failure rollback.
- Cross-branch Match, Player, dismissal, and fielder rejection.
- Existing permissions, CSRF, completed-Match restrictions, and safe errors.
- Before/after audit metadata and correction reason.
- Escaped output and sensitive-data exclusions.
- Any approved migration status, forward-only behavior, and data preservation.

## Current non-authorization

This request does not authorize a version field, migration, route, permission,
test, schema, database change, or application behavior change. Until the
selected decisions, resolution, approver, approval date, effective date, and
approval comments are supplied, the correction and optimistic-concurrency
questions remain unresolved.
