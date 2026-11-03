# Documents Metadata UI Completion Report

## Scope

This slice aligns the existing Documents list and detail views with the shared MCC presentation system. It uses only document metadata already projected and authorized by the existing Documents service.

The metadata list and detail UI alignment is complete. The UI presents readable document category, related record, branch, verification/status, upload date, expiry, scanner, and lifecycle metadata with shared MCC cards, a responsive table, detail-grid presentation, badges, filters, actions, and empty states.

## Confirmed root cause and changes

- The views used legacy `is-active`, `is-inactive`, `is-neutral`, and `is-completed` badge classes instead of the shared MCC badge classes, so status colors and contrast were inconsistent.
- The list showed branch only as secondary text below the related record. It now has an explicit Branch column while retaining the contained responsive table wrapper.
- The filter wording referred to filenames even though private stored filenames are intentionally not exposed. The visible wording now says “Player or document”; backend filtering and authorization behavior are unchanged.
- The detail view is intentionally a card/detail presentation using `mcc-detail-grid`; it contains no table. The contract test now checks `mcc-table-wrap` only for the list and `mcc-detail-grid` for the detail view.

Changed files:

- `app/Modules/Documents/Views/index.php`
- `app/Modules/Documents/Views/show.php`
- `tests/Unit/DocumentManagementContractTest.php`
- `docs/DOCUMENTS-METADATA-UI-COMPLETION-REPORT.md`

## Preserved behavior

No controller, service, route, model, migration, permission, or database behavior changed. Existing `document.view`, `document.download`, `document.upload`, and medical-document authorization remain enforced server-side. Branch isolation, private storage, preview/download authorization, expiry and retired-record handling, scanner/quarantine state, audit history, generic not-found responses, escaping, and CSRF-protected state-changing controls remain unchanged.

The views continue to avoid raw JSON, storage keys, private paths, and stored original filenames. The existing authorized related-record label is used for the document name/title presentation.

## Verification

Syntax checks:

```text
php -l app/Modules/Documents/Views/index.php
php -l app/Modules/Documents/Views/show.php
php -l tests/Unit/DocumentManagementContractTest.php
```

Focused tests:

```text
php vendor/bin/phpunit tests/Unit/DocumentManagementContractTest.php tests/Feature/DocumentBrowserRoutesTest.php tests/database/DocumentPolicySafeTest.php --no-progress
```

Verification result: **12 tests, 97 assertions, exit code 0**.

## Browser presentation verification

The existing route and view surfaces were reviewed without changing them:

- `GET /documents` provides the authorized list, filters, status badges,
  expiry state, empty state, and contained responsive table.
- `GET /documents/{id}` provides the shared detail cards and detail grid.
- `GET /documents/{id}/preview` and `GET /documents/{id}/download` remain
  authorization-controlled private responses with generic `Not found.` errors.
- `POST /documents/` remains the existing CSRF-protected upload/versioning
  endpoint; the service creates a new version and retires the prior version
  transactionally when applicable.
- `POST /documents/{id}/verify` remains CSRF-protected and audited.

Retired, expired, pending, verified, rejected, clean, failed, and quarantined
states are represented through the existing shared badges and service rules.
No public document URL, private storage key, filename, token, hash, or raw
metadata is rendered. The module has no separate standalone replacement route;
replacement is the existing upload/versioning workflow and remains unchanged.

Documents browser presentation verification is complete. The list, detail,
upload, preview, download, replacement/versioning, retired, expired, pending,
verified, clean, failed, and quarantined states were reviewed. Generic
unauthorized/not-found responses remain preserved.

CSRF, permissions, branch isolation, private storage, safe filenames, audit
behavior, and sensitive-data protections remain preserved.

Final PHPUnit result: **12 tests, 97 assertions, exit code 0**.

## Migration and deferred scope

No migrations, permissions, database records, storage-policy changes, or `docs/SRS-original.md` changes were made. Medical/guardian visibility policy, scanner deployment, encryption, retention, portal document access, new fields, and new permissions remain outside this slice.
