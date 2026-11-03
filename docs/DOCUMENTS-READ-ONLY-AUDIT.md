# Documents Read-Only Audit

**Review date:** 2026-09-27  
**Scope:** Document routes, controllers, service, storage abstraction, migrations, Admission/Player integration, permissions, views, tests, and current requirements/decision records.  
**Change status:** Read-only review. No application code, tests, database records, or existing decision documents were changed.  
**Authority note:** `docs/SRS-original.md` is unavailable. `docs/REQUIREMENTS.md`, `docs/DECISIONS.md`, and `docs/SRS.md` are working records and are not treated as a replacement client approval baseline.

## Executive summary

The Documents module has a functioning private local-storage foundation with branch-scoped metadata, upload validation, controlled download/preview routes, replacement support, Admission-to-Player lineage linking, and append-oriented history/audit writes. It is not a complete compliance workflow.

The current implementation does not provide verification states/verifiers, malware scanning or quarantine, document-type-specific permissions, medical-document restrictions, consent/legal-basis tracking, expiry enforcement, configurable retention, encryption/key-management evidence, parent/player document visibility, or a general retirement workflow. These gaps are controlled by `SEC-05`–`SEC-08`, `SEC-10`, `DOC-01`–`DOC-08`, `OPS-03`, and `OPS-10`; they must remain `NEEDS_DECISION` or blocked where the repository has no approved detail.

## Source and navigation map

| Area | Evidence | Current state |
|---|---|---|
| Routes | `app/Modules/Documents/Config/Routes.php` | Authenticated list/detail, download, preview, and upload routes with `document.view`, `document.download`, and `document.upload` filters. |
| Controller | `app/Modules/Documents/Controllers/Browser.php` | Thin list/detail/upload/download/preview methods; download and preview return private response bodies and safe error responses. |
| Service | `app/Services/DocumentManagementService.php` | Type allowlist, branch authorization, upload/replacement, Admission linkage, list/detail, read, preview support, and removal from Admissions. |
| Storage | `app/Libraries/Storage/PrivateStorageInterface.php`, `LocalPrivateStorage.php` | Local filesystem abstraction rooted at `WRITEPATH/private`, with random keys, traversal checks, private directory/file modes, and public-root rejection. |
| Schema | `20260904000700_CreateDocumentManagement.php`, `20260908000100_AddAdmissionDocumentRelationship.php` | Documents, history, nullable Admission/Player lineage, branch and actor foreign keys, status, expiry date, MIME, size, and storage key. |
| Permissions | `20260904000700_CreateDocumentManagement.php`, `20260908000200_AddAdmissionDocumentPermissions.php`, `AdminService.php` | `document.view`, `document.upload`, and `document.download`; Admission Team added for Admission documents. |
| Views | `app/Modules/Documents/Views/index.php`, `show.php`, Admission views, Player photo integration | Basic document list/detail; richer Admission document cards/previews; Player profile photo uses an authorized private preview route. |
| Tests | `tests/Unit/DocumentManagementContractTest.php`, `tests/Unit/PrivateStorageTest.php`, `tests/database/AdmissionDocumentsIntegrationTest.php` | Storage boundary, upload validation, replacement, retirement from Admission, branch/permission denial, preview/download, and conversion linkage are covered. No complete verification/expiry/privacy/retention suite exists. |

## Existing document categories and workflows

`DocumentManagementService::TYPES` currently allows:

- `aadhaar`
- `birth_certificate`
- `school_id`
- `mca_card`
- `medical_certificate`
- `passport`
- `visa`
- `consent_form`
- `player_photo`

Implemented workflows:

1. Upload a document for an Admission or Player.
2. List documents for authorized branches.
3. Open metadata/detail and history.
4. Download an active document through an authorized route.
5. Preview active PDF/JPEG/PNG through an authorized route.
6. Replace an existing document of the same type while preserving its row and relationship.
7. Retire an Admission document through the existing Admission removal route and remove its storage object.
8. Link Admission documents to the converted Player without duplicating files.
9. Resolve the newest active JPEG/PNG `player_photo` for authorized Player profile display.

There is no general upload screen in the Documents module itself; current upload entry points are Admissions and Player profile flows. The Documents index is a metadata list and the detail page exposes download/history.

## Upload validation and private-storage behavior

Validation in `DocumentManagementService::validateUpload()` requires:

- an allowlisted document type;
- a valid, unmoved upload with non-empty size up to 10 MiB;
- MIME type limited to PDF, JPEG, or PNG;
- JPEG/PNG image dimensions and MIME consistency via `getimagesizefromstring()`;
- a PDF signature beginning with `%PDF-`; and
- rejection of PHP/script markers and PDF JavaScript markers.

Original names are reduced to a basename, control characters are removed, and a fallback name is generated if empty. Stored keys use `documents/{branch}/{random-bytes}.ext`; client filenames are not used as storage paths.

`LocalPrivateStorage`:

- rejects traversal, absolute paths, NUL bytes, Windows drive paths, and keys escaping the storage root;
- refuses a root inside the public web root;
- creates directories with restrictive permissions;
- writes through a temporary file and renames into place; and
- applies `0600` file permissions where supported.

The controller never exposes `storage_key` or creates a public URL. Download and preview read through `DocumentManagementService::read()`.

### Confirmed security/operational limitations

- No malware scanner or quarantine gate exists. Files become active immediately after MIME/content checks, despite approved `SEC-12` requiring scanning before availability, quarantine on failure, and audited scan outcomes.
- No encryption-at-rest or key-management evidence exists for local private files (`DOC-06`, `OPS-03`).
- Download uses the stored original name inside a quoted `Content-Disposition` value without escaping embedded quotes. `safeOriginalName()` removes control characters but permits quotes; this is a concrete header-safety defect to fix in a focused storage-response task.

## Download and preview authorization

Routes apply authentication and permission filters. The service repeats authorization using the document's persisted branch:

- `find()` requires `document.view` for the document branch.
- `read()` requires `document.download`, requires `status = active`, and rejects unsupported MIME types.
- `preview()` calls `read()` and allows only PDF, JPEG, and PNG, with `Cache-Control: private, no-store` and inline disposition.
- `download()` returns an attachment with `nosniff` and content length.
- `forAdmission()` requires both Admission view and document view, then supplies per-row download/remove flags.

The Player photo resolver requires both `document.view` and `document.download`, filters by Player/branch/type/status/MIME, and selects newest by `created_at` then ID.

### Authorization concern

`find()` first loads a document by ID and then raises `Forbidden` after checking the branch. A missing ID raises `Not found`. That difference can reveal whether an unauthorized document ID exists. The response body is not the document, but the protected-record existence distinction should be reconciled with the project requirement that unauthorized users cannot infer protected record existence.

Document visibility is permission- and branch-scoped, but not document-type scoped. A user with the general document permissions can access `medical_certificate` through the same paths as ordinary documents.

## Verification and expiry handling

The schema has `status` with the practical values `active`, `expired`, and `retired`, plus nullable `expires_on`. The detail view displays expiry and status. The list can filter those status strings.

No verification route, verifier identity, verification timestamp, pending/verified/rejected state, or verification history exists. Uploaded rows become `active` immediately. This conflicts with approved `SEC-07`, which requires explicit verification states and a verifier.

`read()` checks `status = active` but does not compare `expires_on` with the current date. There is no expiry job or read-time transition from active to expired. An active row with a past `expires_on` can therefore remain downloadable/previewable until some separate process changes its status. Expiry enforcement is a confirmed implementation gap against `REQ-DOC-002` and `SEC-07`, while the exact expiry timing/timezone policy remains `NEEDS_DECISION`.

## Replacement and retirement behavior

Replacement updates the same document row, generates a new random storage key, records a `replaced` history event and audit event, and deletes the old storage object after the metadata transaction completes. If the transaction fails, the new object is removed. Old metadata is not retained as a version row.

Admission removal marks the row `retired`, records history and audit, and deletes the private object. If metadata persistence fails, the service attempts to restore the storage object and logs a critical error if restoration fails. Retired rows remain in metadata and are unavailable through `read()`.

There is no general document retirement/replacement version screen, restore operation, retention job, or physical-deletion policy. `SEC-08` approves logical retirement and retention rules, but `DOC-07` and `DOC-08` leave retention periods and replacement/version policy unresolved.

## Sensitive data, medical documents, and consent

The type list includes Aadhaar, birth certificate, passport, visa, medical certificate, and consent form. The storage layer keeps objects private and routes are server-authorized, but the current permissions are broad:

- `document.view`, `document.upload`, and `document.download` are granted to Super Admin, Branch Admin, Coach, and Fitness Coach by the base migration.
- Admission Team receives the same permissions through the Admission document migration.
- No dedicated medical-document permission or document-type policy matrix exists.
- No controller/service rule distinguishes `medical_certificate` from ordinary identity or admission documents.
- No consent/legal-basis register fields, guardian authority, withdrawal, or media-consent workflow exists in the Documents schema.

This is inconsistent with approved `SEC-05` (restricted medical role access), `SEC-06` (access controlled by type and authorization), and `SEC-10`/`SEC-13` (media/consent evidence). The exact permitted roles, consent workflow, and document-type matrix remain `NEEDS_DECISION` under `DOC-01`, `DOC-03`, `DOC-05`, and related security records.

## Parent and Player visibility

The reviewed Documents routes are staff-facing. No parent portal document route, verified guardian relationship check, consent check, player self-service document view, or revocation rule was found. Player profile integration currently exposes only an authorized profile photo through the private preview route; it does not expose the full document collection.

Parent/player visibility, age/consent eligibility, medical visibility, relationship verification, and revocation remain `NEEDS_DECISION` (`DOC-03`, `DOC-04`, `DOC-05`, `REL-03`–`REL-05`, `SEC-09`, `SEC-13`). No portal access should be inferred from the staff document permission or from the existence of parent-player tables.

## Retention, scanning, encryption, and key-management evidence

| Control | Repository evidence | Status |
|---|---|---|
| Retention | Logical `retired` status and Admission removal path; no expiry/retention scheduler or period. | Partial; period and deletion policy `NEEDS_DECISION` (`SEC-08`, `DOC-07`, `OPS-10`). |
| Malware scanning | MIME/signature/script-marker checks only; no scanner, quarantine, or scan result. | Confirmed gap against approved `SEC-12`. |
| Encryption at rest | No encryption layer or key-management integration for `WRITEPATH/private`. | `NEEDS_DECISION`/deployment dependency (`DOC-06`, `OPS-03`). |
| Storage provider | `LocalPrivateStorage` only. | Provider/deployment ownership `NEEDS_DECISION` (`OPS-03`). |
| Audit | Upload, replace, Admission link, and remove write history/audit events. | Partial; verification, scan, read/download, expiry, and policy events are not defined or implemented. |
| Recovery/backup | No document-specific backup/restore evidence. | Operational evidence unavailable (`OPS-02`, `OPS-11`, `OPS-12`). |

## Tests and evidence

### Existing coverage

- `tests/Unit/DocumentManagementContractTest.php` checks private routes, Admission relationship routes, storage abstraction, audit boundary, script rejection, and no public-assets storage.
- `tests/Unit/PrivateStorageTest.php` checks private object handling and traversal rejection.
- `tests/database/AdmissionDocumentsIntegrationTest.php` covers Admission upload, profile photo validation, JPEG/PDF handling, replacement, Admission retirement, cross-branch/permission denial, secure download/preview route, PDF JavaScript rejection, PHP upload rejection, and Admission-to-Player document linkage.

### Gaps

- No complete Documents browser integration suite for list/detail/upload route responses.
- No tests for expired active documents, verifier/state transitions, medical-type access, parent/player visibility, consent, stale branch assignments, or ID existence leakage.
- No scanner/quarantine, encrypted storage, provider, key-management, retention, restore, or deployment-owner evidence.
- No tests for quote-safe download filenames or response header hardening.
- The Admission document test performs destructive cleanup of its own fixture; it is evidence of behavior, not proof of persistent-database safety for production data.

## Clear defects versus NEEDS_DECISION

### Confirmed defects or implementation gaps

1. Uploaded documents become active without the approved malware-scan/quarantine gate.
2. `expires_on` is displayed and stored, but active documents are not blocked or transitioned when expired.
3. Medical and other sensitive document types use the same broad document permissions; no per-type authorization is enforced.
4. Download `Content-Disposition` does not escape quotes in a client filename.
5. Unauthorized document IDs can produce a different error (`Forbidden`) from nonexistent IDs (`Not found`), allowing record-existence inference.
6. Verification states, verifier metadata, and verification UI/workflow are absent despite approved `SEC-07`.
7. The Documents index/detail UI is minimal and does not consistently use shared table/card/button/empty-state patterns.

### NEEDS_DECISION

- `DOC-01`: upload authority by document type and branch/role policy.
- `DOC-02`: verification authority, states, transitions, rejection reasons, and correction policy.
- `DOC-03`: visibility of unverified/expired documents.
- `DOC-04`: parent/player document categories, consent, and relationship authorization.
- `DOC-05`: medical-document roles, privacy, and safeguarding rules.
- `DOC-06`: encryption at rest, key ownership, rotation, and recovery.
- `DOC-07` / `OPS-10`: retention periods, legal holds, archival, and physical deletion rules.
- `DOC-08`: replacement/version/retirement history and restore policy.
- `OPS-03`: approved private storage provider and deployment ownership.
- `SEC-10` / `SEC-13`: photo/media consent and guardian legal-basis register.

## Smallest safe next task

The smallest clearly authorized implementation slice is a security-hardening change that does not choose unresolved document policy:

1. Escape/encode download filenames safely in `Content-Disposition`.
2. Normalize unauthorized/not-found document responses so protected IDs do not disclose existence.
3. Add focused tests for filename header safety and cross-branch/not-found response equivalence.

Verification, expiry enforcement, medical visibility, scanning, encryption, retention, consent, parent/player access, and replacement-version work must wait for the corresponding decisions and deployment evidence. The next larger slice should be an approver-approved document-type/verification contract, not a broad Documents rewrite.

