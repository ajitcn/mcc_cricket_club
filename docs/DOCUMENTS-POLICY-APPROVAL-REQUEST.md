# Documents Policy Approval Request

**Prepared:** 2026-09-27  
**Purpose:** Obtain explicit approval for the remaining Documents policies before implementing verification, expiry, sensitive-document access, scanning, retention, or deployment changes.  
**Status:** All items below are `NEEDS_DECISION` until an authorized approver records a resolution. Existing code and user direction are implementation evidence only; they do not establish client approval.  
**SRS authority:** `docs/SRS-original.md` is unavailable and must not be reconstructed or modified.

## Approval instructions

For each item, select exactly one response and complete the resolution fields.

- **APPROVED** — the stated policy is accepted for implementation.
- **REJECTED** — the stated policy is not accepted; record the replacement direction in comments.
- **NEEDS_DISCUSSION** — discussion or additional evidence is required; the item remains `NEEDS_DECISION`.

The approver must record identity, approval date, and effective date. A completed response should be reconciled into `docs/REQUIREMENTS.md`, `docs/DECISIONS.md`, `docs/ARCHITECTURE.md`, `docs/DATABASE.md`, and `docs/RBAC.md` before dependent coding begins.

---

## DOC-01 — Document categories, upload authority, and allowed file types

**Current implementation evidence:** `DocumentManagementService::TYPES` currently allows `aadhaar`, `birth_certificate`, `school_id`, `mca_card`, `medical_certificate`, `passport`, `visa`, `consent_form`, and `player_photo`. Upload validation accepts PDF, JPEG, and PNG, with a 10 MiB limit and additional signature/dimension/content checks. Upload authority is currently the broad `document.upload` permission; no category-specific matrix exists.

**Related records:** `REQ-DOC-001`, `REQ-DOC-002`, `SEC-06`, `DOC-01`, `OPS-03`.

**Policy to approve:** Confirm the category list and, for each category, the allowed MIME types/extensions and roles permitted to upload, replace, and view it. Specify whether additional categories are prohibited until separately approved.

**Response:**

- [ ] APPROVED
- [ ] REJECTED
- [ ] NEEDS_DISCUSSION

**Resolution/comments:**

______________________________________________________________________________

**Approver identity:** __________________________  **Approval date:** __________________

**Effective date:** _____________________________

## DOC-02 — Verification states and authorized verifier roles

**Current implementation evidence:** Documents are written `active` immediately. The schema has no verification state, verifier, verification timestamp, rejection reason, or verification route/UI. The detail view shows metadata and history only.

**Related records:** `REQ-DOC-001`, `REQ-DOC-002`, `SEC-07`, `DOC-02`, `DOC-03`, `OPS-01`.

**Policy to approve:** Define the state set (for example, pending, verified, rejected, expired, retired), allowed transitions, verifier roles/permissions, rejection and correction reasons, and visibility before verification. Confirm whether upload availability must wait for verification.

**Response:**

- [ ] APPROVED
- [ ] REJECTED
- [ ] NEEDS_DISCUSSION

**Resolution/comments:**

______________________________________________________________________________

**Approver identity:** __________________________  **Approval date:** __________________

**Effective date:** _____________________________

## DOC-03 — Expiry rules and enforcement behavior

**Current implementation evidence:** `documents.expires_on` is an optional date and is displayed on the detail page. `find()` and `read()` currently require `status = active` but do not compare `expires_on` with the current date or transition expired rows. Upload input does not have an explicit application date-format contract.

**Related records:** `REQ-DOC-002`, `SEC-07`, `DOC-03`, `OPS-09`, `OPS-10`.

**Policy to approve:** Define which categories can expire, whether expiry is inclusive or exclusive, the application timezone/date boundary, read-time versus scheduled enforcement, visibility of expired documents, renewal behavior, and whether expiry changes are audited.

**Response:**

- [ ] APPROVED
- [ ] REJECTED
- [ ] NEEDS_DISCUSSION

**Resolution/comments:**

______________________________________________________________________________

**Approver identity:** __________________________  **Approval date:** __________________

**Effective date:** _____________________________

## DOC-04 — Parent/guardian and Player visibility

**Current implementation evidence:** Reviewed Documents routes are staff-facing. Player profiles expose only an authorized private profile-photo preview. No parent portal document route, Player self-service collection, guardian relationship verification, consent check, or revocation workflow was found.

**Related records:** `REQ-DOC-002`, `REQ-PORTAL-001`, `SEC-09`, `SEC-13`, `DOC-04`, `REL-03`, `REL-04`, `REL-05`.

**Policy to approve:** Define whether Parents/Guardians and Players may view or download documents, which categories are visible, how verified relationships and guardian authority are established, minor eligibility rules, consent/withdrawal requirements, and revocation behavior. Confirm whether staff-only access remains the interim policy.

**Response:**

- [ ] APPROVED
- [ ] REJECTED
- [ ] NEEDS_DISCUSSION

**Resolution/comments:**

______________________________________________________________________________

**Approver identity:** __________________________  **Approval date:** __________________

**Effective date:** _____________________________

## DOC-05 — Medical-document visibility

**Current implementation evidence:** `medical_certificate` is in the same type allowlist as ordinary identity and admission documents. The current migration grants the same `document.view`, `document.upload`, and `document.download` permissions to Super Admin, Branch Admin, Coach, and Fitness Coach, with Admission Team receiving the same grants. No type-sensitive service rule exists.

**Related records:** `REQ-DOC-001`, `REQ-DOC-002`, `SEC-05`, `SEC-06`, `DOC-01`, `DOC-05`, `RBAC.md`.

**Policy to approve:** Define roles allowed to upload, verify, view, download, replace, and retire medical documents; whether medical access requires a separate permission; whether access is branch-scoped, assignment-scoped, or case-specific; and which audit events are required.

**Response:**

- [ ] APPROVED
- [ ] REJECTED
- [ ] NEEDS_DISCUSSION

**Resolution/comments:**

______________________________________________________________________________

**Approver identity:** __________________________  **Approval date:** __________________

**Effective date:** _____________________________

## DOC-06 — Replacement and version-retention rules

**Current implementation evidence:** Replacement updates the existing document row, generates a new private storage key, writes a `replaced` history/audit event, and removes the prior storage object. No version rows, restore action, or version comparison exists. Admission removal is a separate retirement path.

**Related records:** `REQ-DOC-001`, `REQ-DOC-002`, `SEC-08`, `DOC-08`, `OPS-01`, `OPS-10`.

**Policy to approve:** Decide whether replacement preserves immutable versions, how long prior versions remain available, who may restore or view them, whether replacement requires verification again, and how previous metadata and reasons are recorded. Specify legal-hold behavior.

**Response:**

- [ ] APPROVED
- [ ] REJECTED
- [ ] NEEDS_DISCUSSION

**Resolution/comments:**

______________________________________________________________________________

**Approver identity:** __________________________  **Approval date:** __________________

**Effective date:** _____________________________

## DOC-07 — Retirement and deletion behavior

**Current implementation evidence:** Admission removal marks a document `retired`, records history/audit, and deletes its private storage object. Retired documents are not returned by active detail/read paths. There is no general retirement, restore, legal-hold, or scheduled physical-deletion workflow.

**Related records:** `REQ-DOC-001`, `REQ-DOC-002`, `SEC-08`, `DOC-07`, `DOC-08`, `OPS-10`.

**Policy to approve:** Define who may retire documents, whether retirement is reversible, when physical deletion is allowed, how legal holds and conversion lineage affect deletion, whether storage objects must be retained for a period, and which audit/history records are immutable.

**Response:**

- [ ] APPROVED
- [ ] REJECTED
- [ ] NEEDS_DISCUSSION

**Resolution/comments:**

______________________________________________________________________________

**Approver identity:** __________________________  **Approval date:** __________________

**Effective date:** _____________________________

## SEC-12 / DOC-09 — Malware scanning and quarantine

**Current implementation evidence:** The service performs MIME/signature, image-dimension, PDF-marker, and script-marker checks. There is no scanner integration, quarantine state, scan result, failed-scan quarantine, or scan audit event. Files become active after local checks.

**Related records:** `REQ-DOC-001`, `REQ-DOC-002`, `SEC-12`, `DOC-01`, `OPS-03`.

**Policy to approve:** Confirm the required scan gate, accepted scanner/provider boundary, synchronous versus asynchronous processing, quarantine storage and access rules, retry/failure behavior, scan result retention, and the audit fields for scan outcomes. Confirm whether documents remain unavailable until a successful scan.

**Response:**

- [ ] APPROVED
- [ ] REJECTED
- [ ] NEEDS_DISCUSSION

**Resolution/comments:**

______________________________________________________________________________

**Approver identity:** __________________________  **Approval date:** __________________

**Effective date:** _____________________________

## OPS-03 — Private-storage deployment boundary

**Current implementation evidence:** `LocalPrivateStorage` uses `WRITEPATH/private`, rejects public-root storage and unsafe keys, writes temporary files, and applies restrictive permissions. No approved production provider, encryption/key-management integration, backup, restore, or deployment-owner evidence is recorded.

**Related records:** `REQ-DOC-001`, `SEC-06`, `DOC-06`, `OPS-03`, `OPS-02`, `OPS-11`, `OPS-12`.

**Policy to approve:** Identify the approved deployment storage provider/abstraction, whether local storage is permitted in production, encryption at rest, key ownership/rotation/recovery, backup and restore responsibility, network exposure boundary, and required deployment evidence. Repository code must not contain provider secrets.

**Response:**

- [ ] APPROVED
- [ ] REJECTED
- [ ] NEEDS_DISCUSSION

**Resolution/comments:**

______________________________________________________________________________

**Approver identity:** __________________________  **Approval date:** __________________

**Effective date:** _____________________________

## DOC-10 / OPS-10 — Retention periods and audit requirements

**Current implementation evidence:** Document writes, replacements, Admission linking, and Admission removal create history/audit records with actor and timestamps. No document-specific retention schedule, legal hold, read/download audit policy, backup retention, or physical-deletion schedule is implemented.

**Related records:** `REQ-AUDIT-001`, `REQ-DOC-001`, `REQ-DOC-002`, `SEC-08`, `OPS-01`, `OPS-10`, `DOC-07`, `DOC-08`.

**Policy to approve:** Define retention periods by category and lifecycle, legal holds, archival and physical deletion, audit retention, whether reads/downloads are audited, required before/after metadata, access to audit records, and historical recalculation or migration behavior.

**Response:**

- [ ] APPROVED
- [ ] REJECTED
- [ ] NEEDS_DISCUSSION

**Resolution/comments:**

______________________________________________________________________________

**Approver identity:** __________________________  **Approval date:** __________________

**Effective date:** _____________________________

## SEC-10 / SEC-13 — Consent for sensitive documents and media

**Current implementation evidence:** The type list includes `consent_form` and `player_photo`, and Player photos are served only through authorized private preview. No consent/legal-basis register fields, guardian authority evidence, expiry, withdrawal, revocation, or category-level media policy exists in the Documents schema or routes.

**Related records:** `REQ-DOC-001`, `REQ-DOC-002`, `REQ-PORTAL-001`, `SEC-09`, `SEC-10`, `SEC-13`, `DOC-04`, `DOC-05`.

**Policy to approve:** Define when consent or another legal basis is required, who may provide it for minors, required evidence and authority fields, expiry and withdrawal behavior, effect on existing document visibility, and audit/history requirements for consent changes.

**Response:**

- [ ] APPROVED
- [ ] REJECTED
- [ ] NEEDS_DISCUSSION

**Resolution/comments:**

______________________________________________________________________________

**Approver identity:** __________________________  **Approval date:** __________________

**Effective date:** _____________________________

---

## Reconciliation record

**Approver identity:** __________________________  **Approval date:** __________________

**Effective baseline version/date:** ________________________________________________

**Conflicts or follow-up required:**

______________________________________________________________________________

Until this reconciliation is completed, all listed items remain `NEEDS_DECISION`; no code, migration, permission, storage, scanner, verification, expiry, retention, or portal behavior should be added by assumption.

## User-provided secure-default direction (2026-09-27; not client approval)

The following defaults are supplied for approver consideration. They do not check any approval box above and every affected item remains `NEEDS_DECISION` until an authorized approver records identity, approval date, and effective date:

- Sensitive documents use private storage. Server-side validation covers MIME, extension, size, and upload errors, and filenames are generated safely.
- Missing and unauthorized requests return the same generic `Not found.` response.
- Verification states are `PENDING`, `VERIFIED`, and `REJECTED`; verification is limited to authorized staff with branch access.
- Expired documents are excluded from normal use.
- Medical documents are restricted to explicitly authorized roles.
- Parent/Guardian and Player visibility follows approved relationship and branch rules.
- Replacement creates a new version and retires the previous version.
- Retired documents remain auditable and are not downloadable.
- Malware scanning is required before verification or download; failed scans are quarantined.
- Audit history is retained and file contents or credentials are never logged.
- Deployment-owner confirmation is required for scanner, private storage, retention, and encryption settings.

This is user-provided direction — not client approval — and authorizes no implementation by itself.
