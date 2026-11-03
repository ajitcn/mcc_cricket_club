# Achievements Implementation Matrix

**Status:** Gated; no implementation authorized.  
**Reconciled:** 2026-10-06  
**Source:** `docs/ACHIEVEMENTS-READ-ONLY-AUDIT.md` and `docs/ACHIEVEMENTS-REQUIREMENTS-DECISION-QUESTIONNAIRE.md`.

## Decision status

The questionnaire contains no completed option, resolution, approver, approval date, or effective date. Blank fields are not approval. Every Achievements decision area remains `NEEDS_DECISION`.

## User-provided secure defaults

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL (2026-10-06).** Conservative defaults are now recorded for all 18 areas in the questionnaire. They preserve branch isolation, centralized RBAC, approval before publication, append-only audit history, reasoned corrections/revocations, duplicate prevention, private certificate storage, staff-only portal visibility by default, manual award creation, non-destructive archival, existing timezone conventions, CSRF, and server-side validation. They do not authorize code, schema, permission, route, or data changes.

| Area | Current evidence | Status | Affected implementation if approved | Dependencies and tests |
| --- | --- | --- | --- | --- |
| Categories | Foundation catalog implemented by user direction; no award taxonomy workflow | USER-DIRECTED FOUNDATION; broader policy NEEDS_DECISION | Category field, catalog service/views, reference migration | Branch scope, active filtering, validation, audit tests |
| Title and description | Foundation fields implemented by user direction | USER-DIRECTED FOUNDATION; broader policy NEEDS_DECISION | Catalog model/service/form/list views | Required fields, escaping, validation tests |
| Player/Coach eligibility | No eligibility workflow | NEEDS_DECISION | Eligibility service and recipient relationships | Player/Coach authorization and branch tests |
| Branch ownership/visibility | Foundation uses one owning branch and authorized branch filtering | USER-DIRECTED FOUNDATION; broader policy NEEDS_DECISION | Branch column/index and scoped list/create service | Cross-branch denial tests |
| Source attribution | No Tournament/Match/Attendance/Performance/milestone links | NEEDS_DECISION | Source reference model and validation | Source integrity and authorization tests |
| Manual/automatic creation | No creation workflow or automation | NEEDS_DECISION | Thin controller, service workflow, idempotency support | Repeat-processing and authorization tests |
| Approval authority | No approver mapping | NEEDS_DECISION | Approval service, permissions, audit fields | Self-approval and transition tests |
| Lifecycle | No status or transition model | NEEDS_DECISION | Status fields, transition service, lock handling | Invalid transition and terminal-state tests |
| Duplicate prevention | No uniqueness rule | NEEDS_DECISION | Unique keys/idempotency strategy | Concurrent duplicate tests |
| Correction/revocation | No correction workflow | NEEDS_DECISION | Reasoned correction/revoke actions and history | Before/after and reason tests |
| Certificates/documents | No Achievement certificate workflow | NEEDS_DECISION | Private document integration, templates, numbering | Privacy, storage, replacement tests |
| Portal visibility | No portal Achievement query | NEEDS_DECISION | Portal read policy and relationship checks | Player/Parent isolation tests |
| Staff permissions | No `achievement.*` permissions | NEEDS_DECISION | RBAC catalog, role mapping, route filters | Permission matrix and route tests |
| Audit history | No Achievement events/history | NEEDS_DECISION | Shared audit events and/or history table | Append-only metadata tests |
| Effective dates/timezone | No Achievement date fields | NEEDS_DECISION | Date/time fields and service conversion | Boundary and UTC tests |
| Retention/archive | No retention or archive policy | NEEDS_DECISION | Archive metadata and controlled operations | Retention and non-deletion tests |
| Reports/exports | No Achievement report/export | NEEDS_DECISION | Authorized report queries and export service | Formula, branch, privacy tests |
| Notifications | No Achievement event delivery policy | NEEDS_DECISION | Notification events/templates/outbox if approved | Recipient, consent, idempotency tests |

## Explicit next-scope selection

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL (2026-10-06).** The next bounded scope is **draft-only manual award creation**, with Player and Coach recipient assignment as required supporting fields. It allows only `draft` records, owning-branch visibility, active recipient validation, CSRF, centralized RBAC, transactions, and append-only audit metadata. It does not approve or implement automatic eligibility, approval/publication, certificates, portal visibility, reports/exports, notifications, retention, or archival.

| Selected area | Status | Exact scope | Permissions | Migration/test impact |
| --- | --- | --- | --- | --- |
| Player recipient assignment | USER-DIRECTED FOUNDATION; broader eligibility NEEDS_DECISION | `recipient_type=player`, one active `player_id`, matching Achievement branch | `achievement.view`, `achievement.create`, `achievement.update` for draft records only; Super Admin/Branch Admin mapping | Recipient FK/index; forged/inactive/cross-branch and audit tests |
| Coach recipient assignment | USER-DIRECTED FOUNDATION; broader eligibility NEEDS_DECISION | `recipient_type=coach`, one active `coach_id`, matching Achievement branch | Same draft-only permissions | Recipient FK/index; forged/inactive/cross-branch and audit tests |
| Manual award creation | USER-DIRECTED FOUNDATION; approval NEEDS_DECISION | Draft-only award with optional bounded nomination note | Create/update only; no approval permission | Award-draft table/columns; validation, CSRF, rollback, idempotency tests |

**Foundation implementation evidence (2026-10-06):** Draft-only manual award records now use exactly one active same-branch Player or Coach recipient, `draft` status, bounded nomination notes, explicit create/detail/edit routes, `achievement.view/create/update`, CSRF, transactions, and append-only audit metadata. This remains **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL**. All publication and downstream workflows remain `NEEDS_DECISION`.

## Approved scope

None. These secure defaults are user direction only. No Achievement route, service, model, view, permission, migration, table, navigation entry, or placeholder page is approved.

## Bounded implementation evidence

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL (2026-10-06):** The first foundation slice is implemented as a branch-scoped catalog with title, category, description, active/inactive state, create/list screens, `achievement.view` and `achievement.create`, CSRF, server-side validation, transactions, branch isolation, and audit logging. No recipient assignment, eligibility, approval, revocation, certificates, portal visibility, reports, exports, notifications, or retention workflow is included. The broader decision areas remain `NEEDS_DECISION`.

## Rejected scope

None. No option has been selected as rejected in the questionnaire.

## Still blocked

All areas above remain `NEEDS_DECISION`. Existing Match, Tournament, Attendance, Performance, Documents, and Portal decisions do not resolve Achievement policy. Implementation must not begin until explicit choices, resolution, approver, approval date, and effective date are recorded.

## Safe implementation sequence after approval

1. Confirm scope, recipient model, branch ownership, lifecycle, and RBAC.
2. Confirm source attribution, duplicate/correction rules, and audit contract.
3. Confirm certificate/privacy, portal visibility, retention, reporting, and notification policies.
4. Design forward-only migrations and focused Unit, Feature, and database tests.
5. Implement only the approved slice with server-side authorization and branch isolation.
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
## Phase 2 secure-default direction — USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL

On 2026-10-06, user-provided defaults were recorded for APL-01 through APL-05: explicit approval before publication using achievement.approve; branch-scoped publication of approved drafts using achievement.publish; read-only published records with reasoned achievement.update corrections; non-destructive reasoned revocation using achievement.revoke; and explicit post-publication correction preserving actor, branch, timestamp, old values, new values, and reason, with revoked-record correction restricted.

These are not client approvals. Approver, approval date, and effective date were not provided. All five items remain NEEDS_DECISION and no implementation is authorized.
## Phase 2 lifecycle implementation evidence — USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL

The APL-01 through APL-05 defaults are implemented as a bounded lifecycle slice: draft approval with achievement.approve, publication with achievement.publish, read-only published records, reasoned revocation with achievement.revoke, and reasoned published corrections using achievement.update. Status changes are branch-scoped, transactional, version-checked, and audited with actor, branch, timestamp, reason, and before/after values. Revoked records are retained and no deletion workflow is introduced.

Certificates, portal visibility, reports, exports, notifications, retention, and automatic eligibility remain unavailable and unresolved.
## Phase 3 decision reconciliation

Reviewed 2026-10-06 against docs/ACHIEVEMENTS-PHASE-3-DECISION-REQUEST.md.

No explicit decision, resolution, comments, approver name, approval date, or effective date is recorded for any Phase 3 item. Blank fields are not approval. Secure defaults remain USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.

| ID | Workflow | Status | Implementation state |
| --- | --- | --- | --- |
| A3-01 | Certificates | NEEDS_DECISION | Not implemented; certificate templates, numbering, storage, approval, and retention remain unresolved. |
| A3-02 | Parent/Student portal visibility | NEEDS_DECISION | Not implemented; portal status, relationship, consent, and branch visibility remain unresolved. |
| A3-03 | Reports and exports | USER-DIRECTED IMPLEMENTED; CLIENT APPROVAL PENDING | Published-award read-only report and CSV export use existing `report.view`; unresolved broader export policy remains outside this bounded slice. |
| A3-04 | Notifications | NEEDS_DECISION | Not implemented; events, recipients, consent, channels, retries, and delivery remain unresolved. |
| A3-05 | Retention and archival | NEEDS_DECISION | Not implemented; retention periods, archive, legal hold, restore, and deletion authority remain unresolved. |
| A3-06 | Automatic eligibility | NEEDS_DECISION | Not implemented; source rules, formulas, thresholds, idempotency, and human approval remain unresolved. |

No Phase 3 code, route, permission, migration, table, menu item, or test may be added until all required approval fields are complete.
## Phase 3 secure-default direction — USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL

Secure defaults were recorded for A3-01 Certificates, A3-02 Parent/Student portal visibility, A3-03 Reports and exports, A3-04 Notifications, A3-05 Retention and archival, and A3-06 Automatic eligibility. Each remains NEEDS_DECISION because no client approver, approval date, or effective date is provided. No code, route, permission, migration, table, menu, or test is authorized.
## A3-01 certificate implementation evidence — USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL

A bounded certificate slice is implemented for published Achievement awards only. It uses private LocalPrivateStorage, safe deterministic generated filenames, branch-scoped generate/download permissions, idempotent award-to-certificate linkage, and append-only generation audit events. No certificate portal visibility, replacement, retirement, or public URL workflow is introduced.

A3-02 portal visibility, A3-03 reports/exports, A3-04 notifications, A3-05 retention/archival, and A3-06 automatic eligibility remain NEEDS_DECISION and are not implemented.

### A3-02 bounded implementation (2026-10-06)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** Portal routes `student-achievements` and `parent-achievements` render published Player awards only. Server-side filtering uses the portal-authenticated Player or verified guardian-linked active Players and requires the award branch to match the Player branch. No new portal permission, migration, or visibility flag was added. Drafts, revoked records, coach awards, internal notes, approval metadata, and audit data are not returned. A3-02 remains a user-directed implementation pending client approval; A3-03 through A3-06 remain `NEEDS_DECISION`.

### A3-03 bounded implementation (2026-10-06)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** Routes `achievements/reports` and `achievements/reports/csv` use the existing `report.view` permission. Queries include only published awards and enforce authorized branch scope plus matching Achievement, Player, and Coach branch relationships. Filters cover branch, Player, Coach, category, and publication date range. CSV export audits row count and filter presence only. No schema change was required. A3-04 through A3-06 remain `NEEDS_DECISION`.
