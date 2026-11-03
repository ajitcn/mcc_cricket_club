# Achievements Phase 2 Decision Request

**Status:** NEEDS_DECISION for every item  
**Purpose:** Request explicit decisions for the next Achievement lifecycle slice.  
**Classification:** Recommended defaults are **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL**.  
**Scope:** Draft approval, publication, published lifecycle, revocation, and correction after publication.

No application code, route, permission, migration, table, menu, or test change is authorized by this document. docs/SRS-original.md is unchanged.

## Evidence and decision rules

- The current bounded implementation supports the Achievement catalog and draft-only manual awards for one active same-branch Player or Coach.
- Existing draft permissions are achievement.view, achievement.create, and achievement.update.
- No approval, publication, revocation, or post-publication correction workflow exists.
- No Achievement portal visibility, notification, certificate, export, or retention workflow is enabled.
- Blank resolution fields are not approval.
- Each item remains NEEDS_DECISION until an option, resolution, approver, approval date, and effective date are completed.

---

## APL-01 — Draft approval

**Status:** NEEDS_DECISION

**Current implementation evidence:** Draft awards can be created and edited with correction reasons. There is no approval route, service, approver field, timestamp, or approval permission.

**Recommended secure default — USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL:** Require a distinct branch-authorized approver and prohibit self-approval. Approval must be CSRF-protected, transactional, and audited.

**Decision choices:**

- [ ] A — Branch Admin approves drafts in authorized branches.
- [ ] B — Super Admin approves all drafts.
- [ ] C — Designated Achievement approver role approves within authorized branches.
- [ ] D — Two-person approval for selected categories.

**Required roles and permissions:** Decide whether to add achievement.approve separately from create/update and document affected roles.

**Branch visibility:** Approval is limited to the award owning branch and explicit branch authorization.

**Required status transitions:** Decide whether approval is draft to approved, or whether a pending-review state is required.

**Reason requirements:** Decide whether approval requires a note; rejection or return should require a reason under the recommended default.

**Audit fields:** Actor, branch, award ID, prior/new status, timestamp, reason/comment, and decision.

**Database impact:** Approval actor/time/status/reason fields or transition history; forward-only migration only after approval.

**Portal visibility impact:** No portal visibility is enabled by this decision alone. Decide separately whether approved awards can be shown.

**Test acceptance criteria:** Permission and branch denial, self-approval behavior, CSRF, valid/invalid transitions, required reason, rollback, append-only audit, and no draft portal leakage.

**Resolution:** [ ] NEEDS_DECISION — option: ______  
**Comments:** ______________________________________________  
**Approver:** ____________________  **Approval date:** __________  **Effective date:** __________

---

## APL-02 — Publication

**Status:** NEEDS_DECISION

**Current implementation evidence:** No publication route, status, timestamp, actor, or permission exists. Draft-only behavior remains enforced.

**Recommended secure default — USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL:** Publish only an approved award through a distinct audited action. Draft, revoked, and archived records remain hidden.

**Decision choices:**

- [ ] A — Approval automatically publishes the award.
- [ ] B — Approval and publication are separate actions.
- [ ] C — Branch Admin publishes approved awards.
- [ ] D — Super Admin publishes approved awards.

**Required roles and permissions:** Decide whether to add achievement.publish separately from achievement.approve.

**Branch visibility:** Publication retains owning-branch scope; central visibility requires explicit authorization.

**Required status transitions:** Decide whether approved to published is required or approved is already visible.

**Reason requirements:** Require a publication note for a separate publication action; require reasons for unpublishing.

**Audit fields:** Actor, branch, prior/new status, timestamp, publication note/reason, and before/after values.

**Database impact:** Publication status/time/actor fields or a publication history table; forward-only migration after approval.

**Portal visibility impact:** Decide whether publication is the gate for Parent/Student visibility and whether Player/Parent visibility differs.

**Test acceptance criteria:** Only approved records publish; unauthorized users are denied; drafts and revoked records stay hidden; branch, CSRF, idempotency, and audit checks pass.

**Resolution:** [ ] NEEDS_DECISION — option: ______  
**Comments:** ______________________________________________  
**Approver:** ____________________  **Approval date:** __________  **Effective date:** __________

---

## APL-03 — Published lifecycle

**Status:** NEEDS_DECISION

**Current implementation evidence:** The current schema and service support draft only. No approved, published, revoked, archived, or transition guard exists.

**Recommended secure default — USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL:** Use draft, approved, published, revoked, and archived statuses. Lock published records, require reasoned correction/revocation, and do not reopen revoked or archived records.

**Decision choices:**

- [ ] A — draft to approved to published; published to revoked or archived; terminal revoked/archived.
- [ ] B — draft to approved; approved is visible; approved to revoked or archived.
- [ ] C — Add pending review before approval.
- [ ] D — Allow Super Admin reopening of published records.

**Required roles and permissions:** Decide permissions for approve, publish, revoke, archive, and post-publication correction.

**Branch visibility:** Every transition remains branch-scoped and server-authorized.

**Required status transitions:** Select the complete transition graph, terminal states, reopening rule, and effective-date behavior.

**Reason requirements:** Require reasons for rejection, revocation, archival, reopening, and correction.

**Audit fields:** Actor, branch, status before/after, timestamp, reason, effective date, and previous/new values.

**Database impact:** Status checks, transition history or audit fields, lock/effective-date fields, and indexes.

**Portal visibility impact:** Decide which statuses are visible; recommended default is published only.

**Test acceptance criteria:** Transition matrix, terminal-state enforcement, reopening denial, RBAC/CSRF, rollback, idempotency, audit, and portal visibility tests.

**Resolution:** [ ] NEEDS_DECISION — option: ______  
**Comments:** ______________________________________________  
**Approver:** ____________________  **Approval date:** __________  **Effective date:** __________

---

## APL-04 — Revocation

**Status:** NEEDS_DECISION

**Current implementation evidence:** No revocation route, status, permission, reason field, or revocation audit event exists. Physical deletion is not implemented.

**Recommended secure default — USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL:** Revoke by status only; never delete. Require reason, branch authorization, CSRF, transaction, and append-only audit. Hide revoked records from portal users.

**Decision choices:**

- [ ] A — Branch Admin revokes published awards in authorized branches.
- [ ] B — Super Admin only revokes.
- [ ] C — Designated Achievement approver revokes.
- [ ] D — Two-person revocation approval.

**Required roles and permissions:** Decide whether to add achievement.revoke and affected roles.

**Branch visibility:** Revocation requires authorization for the award owning branch.

**Required status transitions:** Decide whether approved and/or published awards may be revoked and whether revoked is terminal.

**Reason requirements:** Revocation reason is required, bounded, plain text, and retained in audit history.

**Audit fields:** Actor, branch, award ID, prior/new status, timestamp, reason, and previous/new values.

**Database impact:** Revocation status/reason/time/actor fields or transition history; no destructive delete migration.

**Portal visibility impact:** Revoked awards must not appear in Parent/Student views unless separately approved.

**Test acceptance criteria:** Permission/branch denial, required reason, valid transition, terminal behavior, no physical deletion, portal exclusion, audit, and rollback.

**Resolution:** [ ] NEEDS_DECISION — option: ______  
**Comments:** ______________________________________________  
**Approver:** ____________________  **Approval date:** __________  **Effective date:** __________

---

## APL-05 — Correction after publication

**Status:** NEEDS_DECISION

**Current implementation evidence:** Draft edits require a correction reason. No published-award correction route, versioning, lock, or correction permission exists.

**Recommended secure default — USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL:** Published records are immutable through ordinary edits. Corrections require a distinct permission, reason, before/after audit, transaction, preserved prior version, and re-approval.

**Decision choices:**

- [ ] A — Correct in place with versioned audit history and re-approval.
- [ ] B — Create a replacement award and revoke the original.
- [ ] C — Super Admin correction only with mandatory re-approval.
- [ ] D — No correction; revoke and recreate only.

**Required roles and permissions:** Decide whether to add achievement.correct_published and whether correction requires re-approval.

**Branch visibility:** Correction requires authorization for the award owning branch.

**Required status transitions:** Decide whether published moves to draft, pending review, or a replacement record.

**Reason requirements:** Every correction requires a bounded reason identifying what changed and why.

**Audit fields:** Actor, branch, award/version, prior/new values, reason, timestamp, prior publication metadata, and re-approval metadata.

**Database impact:** Version/history or replacement-link fields and indexes; forward-only migration after approval.

**Portal visibility impact:** Decide whether the last approved version remains visible while correction is pending. Recommended default: show only the last approved published version.

**Test acceptance criteria:** Ordinary published edit denied; authorized correction, reason, previous-value recovery, re-approval, branch/RBAC/CSRF, rollback, and portal visibility enforced.

**Resolution:** [ ] NEEDS_DECISION — option: ______  
**Comments:** ______________________________________________  
**Approver:** ____________________  **Approval date:** __________  **Effective date:** __________

---

## Consolidated approval record

| ID | Decision area | Resolution | Comments | Approver | Approval date | Effective date | Status |
| --- | --- | --- | --- | --- | --- | --- | --- |
| APL-01 | Draft approval |  |  |  |  |  | NEEDS_DECISION |
| APL-02 | Publication |  |  |  |  |  | NEEDS_DECISION |
| APL-03 | Published lifecycle |  |  |  |  |  | NEEDS_DECISION |
| APL-04 | Revocation |  |  |  |  |  | NEEDS_DECISION |
| APL-05 | Correction after publication |  |  |  |  |  | NEEDS_DECISION |

## Implementation gate

Do not implement any item until its resolution, comments, approver, approval date, and effective date are completed. A recommended default is not approval. No code, route, permission, migration, table, menu item, or test change is authorized by this request.
## Phase 2 decision reconciliation

Reviewed 2026-10-06. No selected options, resolutions, comments, approver names, approval dates, or effective dates were recorded for APL-01 through APL-05. Blank fields are not approval. All five items remain NEEDS_DECISION; none is approved or rejected.

| ID | Workflow | Status |
| --- | --- | --- |
| APL-01 | Draft approval | NEEDS_DECISION |
| APL-02 | Publication | NEEDS_DECISION |
| APL-03 | Published lifecycle | NEEDS_DECISION |
| APL-04 | Revocation | NEEDS_DECISION |
| APL-05 | Correction after publication | NEEDS_DECISION |

Recommended defaults remain USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL. No Phase 2 code, route, permission, migration, table, menu item, or test is authorized.
## Applied secure defaults — USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL

Reviewed 2026-10-06. The following defaults were supplied by the user. They are recorded as implementation direction only; they are not client approval. No approver identity, approval date, or effective date was supplied.

### APL-01 — Draft approval

- **Selected option:** Explicit approval before publication.
- **Resolution:** Draft awards require approval through achievement.approve. Approval records actor, timestamp, owning branch, and reason.
- **Comments:** Approval is separate from draft creation/update. Self-approval remains disallowed unless separately decided.
- **Approver:** User-provided direction; client approver not recorded.
- **Approval date:** Not provided; not client-approved.
- **Effective date:** Not provided; not effective for implementation.
- **Status:** NEEDS_DECISION / USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.

### APL-02 — Publication

- **Selected option:** Only approved drafts may be published.
- **Resolution:** Publication requires achievement.publish, remains branch-scoped, and records an audit event.
- **Comments:** Draft, revoked, and archived records cannot be published.
- **Approver:** User-provided direction; client approver not recorded.
- **Approval date:** Not provided; not client-approved.
- **Effective date:** Not provided; not effective for implementation.
- **Status:** NEEDS_DECISION / USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.

### APL-03 — Published lifecycle

- **Selected option:** Published awards are read-only by default.
- **Resolution:** Published records cannot be silently edited. Corrections require achievement.update, a reason, before/after values, and an audit event.
- **Comments:** Ordinary edit routes must not change published records.
- **Approver:** User-provided direction; client approver not recorded.
- **Approval date:** Not provided; not client-approved.
- **Effective date:** Not provided; not effective for implementation.
- **Status:** NEEDS_DECISION / USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.

### APL-04 — Revocation

- **Selected option:** Reasoned, non-destructive revocation.
- **Resolution:** Revocation requires achievement.revoke and a reason. Revoked awards remain stored and visible in audit history; revocation is not deletion.
- **Comments:** Historical records must remain append-oriented and recoverable.
- **Approver:** User-provided direction; client approver not recorded.
- **Approval date:** Not provided; not client-approved.
- **Effective date:** Not provided; not effective for implementation.
- **Status:** NEEDS_DECISION / USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.

### APL-05 — Correction after publication

- **Selected option:** Explicit, reasoned correction with preserved history.
- **Resolution:** Corrections require explicit permission and reason. Preserve previous values and record actor, branch, timestamp, old values, new values, and reason. Revoked records cannot be corrected unless explicitly authorized.
- **Comments:** Correction behavior, versioning, and any re-approval gate remain implementation decisions after client approval.
- **Approver:** User-provided direction; client approver not recorded.
- **Approval date:** Not provided; not client-approved.
- **Effective date:** Not provided; not effective for implementation.
- **Status:** NEEDS_DECISION / USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.

### Reconciliation rule

These selections are recorded as user-provided defaults only. They do not authorize application code, permissions, routes, migrations, tables, tests, or deployment. Each item remains blocked until client approval fields are completed.
## Updated consolidated direction record

The earlier blank table records the pre-response state. The later user response selected the secure defaults below. These remain user-provided direction and not client approval.

| ID | Selected option | Resolution | Comments | Approver | Approval date | Effective date | Status |
| --- | --- | --- | --- | --- | --- | --- | --- |
| APL-01 | Explicit approval before publication | achievement.approve; actor, timestamp, branch, and reason audited | Separate approval from creation/update; self-approval not selected | User-provided direction; client approver not recorded | Not provided | Not provided | NEEDS_DECISION |
| APL-02 | Approved drafts only may publish | achievement.publish; branch-scoped and audited | Draft, revoked, and archived records cannot publish | User-provided direction; client approver not recorded | Not provided | Not provided | NEEDS_DECISION |
| APL-03 | Published read-only by default | Corrections require achievement.update, reason, before/after values, and audit | No silent edits | User-provided direction; client approver not recorded | Not provided | Not provided | NEEDS_DECISION |
| APL-04 | Reasoned non-destructive revocation | achievement.revoke and reason; retain award and audit history | Revocation is not deletion | User-provided direction; client approver not recorded | Not provided | Not provided | NEEDS_DECISION |
| APL-05 | Explicit reasoned post-publication correction | Preserve old/new values and actor, branch, timestamp, reason; revoked correction restricted | Versioning/re-approval remains unresolved | User-provided direction; client approver not recorded | Not provided | Not provided | NEEDS_DECISION |
