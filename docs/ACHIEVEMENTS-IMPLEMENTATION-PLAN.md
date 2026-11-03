# Achievements Implementation Plan

**Status:** Gated; planning only.  
**Direction:** **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL (2026-10-06).**  
**Sources:** `docs/ACHIEVEMENTS-REQUIREMENTS-DECISION-QUESTIONNAIRE.md`, `docs/ACHIEVEMENTS-IMPLEMENTATION-MATRIX.md`, and the read-only audit.

## Implementation gate

The user has authorized only the bounded catalog foundation as **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL**. That slice is limited to branch-scoped title/category/description/active state, create/list screens, `achievement.view`/`achievement.create`, CSRF, validation, transactions, and audit logging. All other areas remain `NEEDS_DECISION`; do not create recipient, approval, certificate, portal, report, export, notification, or retention workflows.

## Planned dependency order after approval

1. Approve categories, fields, recipients, branch ownership, sources, creation mode, approval authority, lifecycle, duplicate, and correction rules.
2. Approve RBAC role mappings, branch scope, audit events, effective-date/timezone behavior, retention, and archival ownership.
3. Approve certificate/document, portal visibility, report/export, and notification policies.
4. Produce a reviewed data model and forward-only migration plan.
5. Implement the smallest approved catalog and award slice with thin controllers and service-owned workflows.
6. Add private certificates, portal visibility, reports/exports, and notifications only after their separate approvals.

## Selected next slice

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL (2026-10-06).** The next implementation slice is draft-only manual award creation with Player or Coach recipient assignment. It must use `achievement_id`, `recipient_type`, exactly one recipient foreign key, `branch_id`, `draft` status, bounded nomination note, creator/timestamps, centralized `achievement.view/create/update`, owning-branch isolation, CSRF, transactions, and append-only audit metadata. No approval/publication or automatic eligibility is included.

### Acceptance gate for that slice

- Achievement is active and belongs to an authorized branch.
- Exactly one active Player or Coach recipient is selected and belongs to the same branch.
- Draft-only records are created or updated transactionally.
- Draft edits require `achievement.update`; approved/revoked/archived states are not accepted.
- Duplicate draft recipient/achievement combinations are rejected or updated idempotently according to the migration constraint.
- Audit records include actor, branch, before/after values, timestamp, and reason for edits.
- Parent/Student portal, automatic award creation, certificates, reports, exports, notifications, retention, and archival remain unavailable.

**Implementation evidence (2026-10-06):** The draft-only Player/Coach manual award slice is implemented under the user-provided direction. Its forward-only migration adds recipient and draft fields only; no publication or downstream workflow is enabled. The migration remains pending in this environment because the local CodeIgniter bootstrap is unreadable.

## Affected implementation areas after approval

| Area | Status | Likely routes/controllers/services/models/views | Migration/permission impact | Required tests |
| --- | --- | --- | --- | --- |
| Categories and award fields | NEEDS_DECISION | Catalog and award CRUD, validation service, list/form/detail views | Category/award fields; least-privilege create/update | Unit validation, Feature RBAC, database scope/audit |
| Eligibility and sources | NEEDS_DECISION | Recipient/source resolver services and selectors | Recipient/source references and integrity constraints | Forged, inactive, cross-branch source/recipient tests |
| Approval and lifecycle | NEEDS_DECISION | Approval/transition service and guarded actions | Status/lock/approval metadata; separate approval permission | Invalid transition, terminal-state, CSRF, audit tests |
| Duplicate/correction/revocation | NEEDS_DECISION | Transactional idempotency and correction services | Unique keys, history/reason fields | Concurrent duplicate and before/after tests |
| Certificates | NEEDS_DECISION | Private document service integration and preview/issue actions | Template/version/storage metadata; certificate permissions | Private storage, authorization, replacement tests |
| Portal visibility | NEEDS_DECISION | Portal read service/view | Visibility/consent metadata | Player/Parent relationship and branch isolation tests |
| Audit and retention | NEEDS_DECISION | Shared audit events and archive service/job | Append-only history and archive metadata | Retention, non-deletion, audit completeness tests |
| Reports/exports | NEEDS_DECISION | Authorized query/report/export service | Optional indexes; report/export permissions | Formula, branch, privacy, export tests |
| Notifications | NEEDS_DECISION | Event/template/outbox integration | Event/outbox/template data and delivery permissions | Recipient, consent, retry, idempotency tests |

## Secure implementation requirements

- Enforce centralized RBAC and branch isolation server-side on every route and service method.
- Use CSRF-protected forms, server-side validation, transactions for multi-record writes, and append-only audit metadata.
- Keep published records approval-gated; require reasons for correction, revocation, replacement, and archival.
- Prevent duplicate awards transactionally and retain historical records without destructive deletion.
- Keep certificates private with safe filenames and authorized access only if that scope is approved.
- Use existing application timezone conventions: MCC-local input, service conversion, UTC storage where timestamps are required, and no visible timezone suffixes.

## Deployment evidence required

Client approval entries must include selected option, resolution, comments, approver, approval date, and effective date. Before release, provide reviewed migrations, role-permission mappings, branch-scope evidence, private-storage/scanner/retention evidence where applicable, and focused Unit, Feature, and safe database test results.

## Explicit exclusions

No automatic award formulas, certificate generation, portal exposure, report/export, notification delivery, retention deletion, menu entry, permission grant, or database change is included in this plan's current executable scope.

## Foundation implementation evidence

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL (2026-10-06):** The bounded catalog slice now covers branch-scoped Achievement title, category, description, active/inactive state, create/list routes and views, centralized `achievement.view`/`achievement.create` permission checks, CSRF, server validation, transactions, and audit events. All award workflows remain deferred and `NEEDS_DECISION`.
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
## Phase 3 secure-default direction — USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL

Secure defaults were recorded for A3-01 Certificates, A3-02 Parent/Student portal visibility, A3-03 Reports and exports, A3-04 Notifications, A3-05 Retention and archival, and A3-06 Automatic eligibility. Each remains NEEDS_DECISION because no client approver, approval date, or effective date is provided. No code, route, permission, migration, table, menu, or test is authorized.
## A3-01 certificate implementation evidence — USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL

A bounded certificate slice is implemented for published Achievement awards only. It uses private LocalPrivateStorage, safe deterministic generated filenames, branch-scoped generate/download permissions, idempotent award-to-certificate linkage, and append-only generation audit events. No certificate portal visibility, replacement, retirement, or public URL workflow is introduced.

A3-02 portal visibility and A3-03 reports/exports have bounded user-directed slices implemented; A3-04 notifications, A3-05 retention/archival, and A3-06 automatic eligibility remain NEEDS_DECISION and are not implemented.

### A3-02 implementation record (2026-10-06)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** Implemented only the read-only Parent/Student portal visibility slice for published Player awards. Portal session separation and OTP authentication remain unchanged. Staff Achievement permissions are not reused by portal routes. A3-03 reports/exports, A3-04 notifications, A3-05 retention/archival, and A3-06 automatic eligibility remain `NEEDS_DECISION`.

### A3-03 reports and exports (2026-10-06)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** The bounded implementation provides published-award read-only reporting and CSV export under the existing `report.view` permission. It is branch-scoped, validates filters server-side, preserves active filters in CSV links, and audits exports without sensitive data. No new migration or permission was added. A3-04 notifications, A3-05 retention/archival, and A3-06 automatic eligibility remain `NEEDS_DECISION`.
