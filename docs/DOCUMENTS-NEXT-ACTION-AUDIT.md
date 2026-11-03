# Documents Next-Action Audit

**Review date:** 2026-09-27  
**Scope:** Read-only review after the recent download filename and protected-record response fixes.  
**Change status:** This document is the only file created by this review. No application code, tests, database records, migrations, or existing decision records were changed.  
**Authority:** `docs/SRS-original.md` is not present. `docs/SRS.md`, `docs/REQUIREMENTS.md`, `docs/DECISIONS.md`, `docs/RBAC.md`, `docs/ARCHITECTURE.md`, and `docs/DATABASE.md` are working records and are not treated as a replacement client-approved baseline.

## 1. Sources and authority classification

| Source | Use in this audit | Authority classification |
|---|---|---|
| `app/Services/DocumentManagementService.php`, `app/Modules/Documents/Controllers/Browser.php`, `app/Libraries/Storage/LocalPrivateStorage.php` | Current implementation evidence | Repository evidence only |
| `app/Modules/Documents/Config/Routes.php`, Admission and Player document routes/views | Request and presentation evidence | Repository evidence only |
| `tests/Unit/DocumentManagementContractTest.php`, `tests/Unit/PrivateStorageTest.php`, `tests/database/AdmissionDocumentsIntegrationTest.php` | Existing coverage evidence | Test evidence; not client approval |
| `docs/REQUIREMENTS.md`, `docs/ARCHITECTURE.md`, `docs/DATABASE.md`, `docs/RBAC.md` | Working requirement and architecture traceability | Project working records |
| `docs/DECISIONS.md` | Decision status | Explicit records only: `APPROVED` items are identified below; `NEEDS_DECISION` items remain unresolved |
| `docs/SRS.md` | Available SRS-derived working summary | Not a replacement for the missing original SRS |
| `docs/DOCUMENTS-READ-ONLY-AUDIT.md` | Historical audit context | Historical implementation snapshot; current code was rechecked and supersedes stale findings |

The repository records `SEC-05` (restricted medical access), `SEC-06` (document-type and authorization control), `SEC-07` (verification states and verifier), `SEC-08` (retention and logical retirement), `SEC-12` (scan before availability and quarantine failures), and `SEC-13` (consent/legal-basis evidence) as approved decisions. `DOC-01` through `DOC-08`, `OPS-03`, and `OPS-10` remain `NEEDS_DECISION` in `docs/DECISIONS.md`. No client approval is inferred from code or tests.

## 2. Implemented behavior

### Upload, metadata, and private storage

- `DocumentManagementService::TYPES` allowlists `aadhaar`, `birth_certificate`, `school_id`, `mca_card`, `medical_certificate`, `passport`, `visa`, `consent_form`, and `player_photo` (`app/Services/DocumentManagementService.php:15-25`).
- Uploads require an allowlisted type, a valid unmoved non-empty file, a maximum size of 10 MiB, and MIME `application/pdf`, `image/jpeg`, or `image/png` (`:538-551`). Profile photos are limited to JPEG/PNG (`:554-555`).
- PDF signature, image dimensions/MIME consistency, PDF JavaScript markers, and common executable/script markers are checked (`:558-590`). These checks are content heuristics, not a malware scanner.
- Storage keys are generated server-side as `documents/{branch}/{random}.ext`; uploaded names are metadata only. `LocalPrivateStorage` rejects traversal, absolute paths, NULs, drive paths, and roots inside the public web root, writes through a temporary file, and applies restrictive file permissions (`app/Libraries/Storage/LocalPrivateStorage.php:9-101`).
- The storage abstraction is rooted at `WRITEPATH/private` by the service (`DocumentManagementService.php:617-620`). No public URL or storage path is returned by the document controller.
- Upload, replacement, Admission linking, and Admission removal write `document_history` rows and audit events. Uploads become `active` immediately (`DocumentManagementService.php:204-256`).

### Categories and relationships

- The schema stores branch, nullable Admission/Player relationships, type, original name, storage key, MIME, size, optional expiry date, status, uploader, and timestamps (`app/Database/Migrations/20260904000700_CreateDocumentManagement.php`, `20260908000100_AddAdmissionDocumentRelationship.php`).
- Admission uploads and removal use `app/Modules/Admissions/Config/Routes.php` and the Admission Browser controller. Converted Admission documents are linked to the Player without copying the storage object through `linkAdmissionToPlayer()`.
- Player profile photos use `authorizedProfilePhotoId()` and select the newest active JPEG/PNG `player_photo` by `created_at`, then ID. The profile renders it only through the authorized preview route; initials remain the fallback.
- There is no general Documents-module upload screen. Upload entry points are Admissions and Player profile flows; the Documents index is a metadata list and the detail page is a metadata/history view.

### Download, preview, and authorization

- `app/Modules/Documents/Config/Routes.php` protects list/detail with `document.view`, download/preview with `document.download`, and POST upload with `document.upload`, all behind authentication.
- `DocumentManagementService::find()` loads the row, checks the persisted branch with `document.view`, and only returns active documents (`:291-320`). `read()` repeats download authorization, restricts MIME types, and reads only through private storage (`:517-535`).
- Missing, unauthorized, cross-branch, retired, expired-status, unsupported, and unavailable-storage cases are normalized by the service/controller to `Not found.`. The controller returns HTTP 404 without exposing storage keys, internal details, or personal data (`Browser.php:31-84`).
- Download filenames are now derived from the allowlisted document type and stored MIME extension (`Browser.php:87-102`), so uploaded names are not inserted into `Content-Disposition`.
- Preview responses use inline disposition for supported MIME types, `nosniff`, and `Cache-Control: private, no-store` (`Browser.php:51-84`).

### Branch scope and visibility

- List queries are restricted to `session('authorizedBranches')` and each visible branch is checked with `document.view` (`DocumentManagementService.php:92-105, 622-627`).
- Uploads authorize the submitted branch server-side and verify Admission/Player relationships in that branch (`:132-175`). The same authorization is repeated for replacement and removal.
- Admission document rows expose download/remove flags based on the row branch and current permissions (`:323-359`). The Player photo resolver requires both view and download permission for the Player branch (`:47-70`).
- Current routes are staff-facing. No parent portal, Player self-service document collection, guardian verification, or consent-based document route was found.

## 3. Confirmed defects and implementation gaps

These findings are supported by current source and conflict with an already approved requirement or security decision. They are not resolved by the recent test-expectation changes.

### 3.1 Files become active without the approved scan/quarantine gate — high risk

`upload()` stores the object and writes `status = active` after local MIME/content checks. No scanner adapter, quarantine state, scan result, failure quarantine, or scan audit event exists. This conflicts with approved `SEC-12` and `REQ-DOC-001`/`REQ-DOC-002`. The repository has heuristic script-marker checks, but those are not malware scanning evidence.

### 3.2 Verification workflow is absent — high risk

The schema has only practical `active`, `expired`, and `retired` status values. There is no verification state machine, verifier identity, verification timestamp, rejection reason, correction route, or verification UI. This conflicts with approved `SEC-07` and the verification portion of `REQ-DOC-002`.

### 3.3 Expiry is stored and displayed but not enforced — high risk

`expires_on` is persisted and shown in `app/Modules/Documents/Views/show.php`, but `find()` and `read()` check only `status = active`; they do not compare the date with the current date or transition an expired row. An active document with a past `expires_on` can therefore remain downloadable. The exact expiry boundary and timezone policy are not documented, so enforcement needs a decision-compatible implementation rather than an invented rule.

### 3.4 Document-type authorization is broader than approved medical/document policy — high risk

The base migration grants `document.view`, `document.upload`, and `document.download` to Super Admin, Branch Admin, Coach, and Fitness Coach; the Admission Team receives the same permissions through `20260908000200_AddAdmissionDocumentPermissions.php`. No service rule distinguishes `medical_certificate` from identity, consent, or ordinary documents. This conflicts with approved `SEC-05` and `SEC-06`; the exact role/type matrix is still `DOC-01` and `DOC-05` `NEEDS_DECISION`.

### 3.5 Expiry input has no explicit server-side date validation — medium risk

The upload service accepts `expires_on` as a raw optional value and writes it to metadata (`DocumentManagementService.php:204-215`). There is no explicit date-format or range validation before persistence. Database-level rejection may occur for some malformed values, but that is not a clear application validation contract.

### 3.6 Retention and replacement policy are incomplete — medium risk

Admission removal marks metadata `retired` but immediately deletes the private storage object. Replacement updates one row and removes the previous object; no version rows, restore action, retention job, legal hold, or physical-deletion schedule exists. Logical retirement is approved by `SEC-08`, but retention periods and replacement/version details remain `DOC-07`, `DOC-08`, and `OPS-10` `NEEDS_DECISION`. The current behavior must not be treated as a complete retention implementation.

### 3.7 Documents UI is a minimal foundation — medium usability gap

`app/Modules/Documents/Views/index.php` provides a compact query form and table with only a free-text search, while `show.php` provides metadata, download, and history. There is no complete browser upload flow, verification controls, expiry state handling, type-sensitive empty state, or consistent shared table/card/modal treatment. This is a presentation and workflow gap, not a reason to broaden access or add undocumented actions.

## 4. Security risks and operational evidence gaps

- Local private filesystem storage has no repository evidence of encryption at rest, key management, key rotation, or encrypted backups. These are `DOC-06`, `OPS-03`, `OPS-02`, and `OPS-11/12` dependencies, not assumptions that local permissions provide encryption.
- No malware scanning provider, quarantine boundary, scan result retention, or deployment evidence is present.
- No document-specific backup/restore or disaster-recovery evidence was found.
- Read/download audit policy is not defined as a separate event; current history/audit coverage is for writes and lineage operations. Whether reads require audit is unresolved under the document policy records.
- Staff permissions are branch-scoped in the service, but type-sensitive medical/guardian policy is not implemented. No parent/player visibility should be inferred from existing contact or portal records.
- The controller intentionally normalizes failures to 404 and logs no protected details in the response. This meets the current non-disclosure fix, but deployment log and monitoring evidence remain outside the repository.

## 5. Existing test coverage and missing tests

### Existing evidence

- `tests/Unit/DocumentManagementContractTest.php` covers route filters, private-storage boundaries, safe metadata-derived download names, MIME extension selection, and source-level security contracts.
- `tests/Unit/PrivateStorageTest.php` covers private object handling, traversal rejection, public-root rejection, and file cleanup.
- `tests/database/AdmissionDocumentsIntegrationTest.php` covers Admission upload, PDF/JPEG/profile-photo validation, private keys, replacement, Admission retirement, conversion linkage, cross-branch/permission denial, normalized not-found responses, and secure preview/download behavior.

The requested focused Documents command was run before this audit and passed: 21 tests and 164 assertions. That result is local test evidence only; it is not client approval or deployment evidence.

### Missing or insufficient coverage

- Expired active documents must be tested once the expiry boundary is approved and implemented.
- Verification state transitions, verifier metadata, rejection/correction reasons, and branch/type authorization need database and route tests.
- Medical-document access must have positive and negative role/type tests.
- Parent/player visibility, guardian relationship verification, consent expiry/withdrawal, and revocation need tests only after `DOC-03`–`DOC-05`, `SEC-09`, `SEC-10`, and `SEC-13` policy is reconciled.
- Scan/quarantine success/failure, scan audit records, encrypted storage/provider behavior, retention/legal hold, version restore, and deployment backup evidence are absent.
- Browser-level list/detail/upload responses, empty states, status filters, and CSRF behavior are not covered as a complete Documents route suite.
- Existing integration cleanup is fixture-local and should not be read as production-data safety evidence.

## 6. Remaining `NEEDS_DECISION` items

The following remain unresolved in `docs/DECISIONS.md`; this audit does not answer them:

- `DOC-01`: upload authority by document type.
- `DOC-02`: verification authority, states, transitions, rejection reasons, and correction policy.
- `DOC-03`: visibility of unverified and expired documents.
- `DOC-04`: parent/player document categories, consent, relationship authorization, and revocation.
- `DOC-05`: medical-document role visibility and safeguarding rules.
- `DOC-06`: encryption at rest, key ownership, rotation, and recovery.
- `DOC-07` and `OPS-10`: retention periods, legal holds, archival, and physical deletion.
- `DOC-08`: replacement/version/retirement history and restore policy.
- `OPS-03`: approved private-storage provider and deployment ownership.
- `SEC-10` and `SEC-13` implementation details: photo/media consent and guardian legal-basis register fields, lifecycle, and audit treatment.
- Whether document reads/downloads require an audit event, and the exact expiry date/time boundary, are also not defined in the current records.

These are policy questions. Existing code, migration grants, or historical audit tables do not constitute answers.

## 7. Smallest safe next coding task

There is no safe broad Documents workflow to implement before the unresolved policy boundaries are recorded. The smallest coding slice that can proceed without inventing policy is a focused **expiry and upload-validation foundation** after the approver records the date boundary and scan boundary:

1. Add explicit server-side validation for the optional `expires_on` format and the approved date rule.
2. Add a centralized read-time expiry guard that maps expired records to the approved visibility behavior.
3. Add the approved scan/quarantine adapter boundary without choosing a provider in application code.
4. Add focused tests for same-day expiry, expired download/preview, malformed expiry input, scan success/failure, and audit outcomes.

Until those decisions and the deployment owner’s storage/scanning evidence are available, the correct next action is a short approver decision packet covering `DOC-01`–`DOC-08`, `OPS-03`, `OPS-10`, and the expiry boundary. Do not add medical roles, parent portals, verification states, retention jobs, or scanner behavior by assumption.

## 8. Summary by requested category

| Category | Current conclusion |
|---|---|
| Implemented behavior | Private local storage, allowlisted upload checks, branch/permission checks, safe download/preview routes, Admission linkage, replacement, Admission retirement, history/audit writes, and authorized Player photo preview |
| Confirmed defects | No malware scan/quarantine, no verification workflow, no expiry enforcement, no type-sensitive medical authorization, raw expiry validation, incomplete retention/version policy, minimal Documents browser UI |
| Security risks | Sensitive document permissions are broad; encryption, key management, scanning, retention, backup, and deployment evidence are absent |
| Missing tests | Expiry, verification, medical/type access, parent/player/consent, scan/quarantine, retention/version restore, complete browser routes, and deployment controls |
| Approval status | `SEC-05`–`SEC-08`, `SEC-12`, and `SEC-13` are recorded approved; `DOC-01`–`DOC-08`, `OPS-03`, and `OPS-10` remain `NEEDS_DECISION`; code is not client approval |
| Next action | Record the missing policy and deployment decisions, then implement the smallest approved expiry/scanning foundation with focused tests |
