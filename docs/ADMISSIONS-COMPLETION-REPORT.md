# Admissions Core Lifecycle Verification Report

**Verification date:** 2026-10-07  
**Database target:** `mcc_cricket_club_test`  
**Scope:** AD-01, AD-02, AD-03, and PL-01 through PL-06  
**Direction classification:** Existing approved requirements plus recorded user-provided direction where explicitly identified

## Result

The requested command was run exactly:

```text
php vendor/bin/phpunit tests/Unit/AdmissionModuleContractTest.php tests/Feature/AdmissionRoutesTest.php tests/database/AdmissionsBrowserSafeTest.php tests/database/PlayerWriteWorkflowSafeTest.php --no-progress
```

Result:

```text
OK (21 tests, 274 assertions)
```

The suite is passing after correcting contact idempotency. `PlayerService::linkContactWithinTransaction()` now locks and reuses normalized existing contacts, preserves complete values, and fills only missing metadata when valid conversion values are available.

No migration, permission, production data, or `docs/SRS-original.md` was modified. The focused application fix was limited to contact idempotency, with corresponding Unit and insert-only database regression coverage.

## Current implementation evidence

- `AdmissionService` contains the seven-status lifecycle and transition matrix.
- Checklist completion and approval markers are persisted before conversion.
- Lead-linked and Direct Admission intake paths exist.
- Conversion uses a transaction, preserves `admission_id` and optional `lead_id`, records `admission_converted`, and returns the existing Player on repeated conversion.
- Admissions browser routes and views cover list, detail, edit, checklist, status, completion, and conversion actions.
- `AdmissionsBrowserSafeTest` covers browser rendering, permission scope, branch scope, direct admissions, and route behavior.
- `PlayerWriteWorkflowSafeTest` covers lineage, conversion authorization, lifecycle history, audit behavior, complete and incomplete contact reuse, repeated conversion, and contact-transfer behavior.

## Corrected defect

The original retry defect occurred when the first conversion created a guardian with incomplete metadata. A repeated conversion compared all fields exactly and inserted a duplicate when new parent metadata was available. The corrected path uses the existing Player/contact relationship and normalized identity, preserves existing values, and fills only missing metadata. Contact rows are locked during the transaction to prevent duplicate retry/concurrency behavior within the existing conversion transaction and admission row-lock design.

The following historical failure description is superseded by the corrected implementation and passing regression test:

On the first conversion, the first guardian receives the parent email/mobile and the second guardian is intentionally linked without those details. On a repeated conversion, the method restarts its “first guardian” selection and compares the second guardian with the now-populated parent email/mobile. It treats the existing second guardian as different and creates a duplicate contact.

## Migration status

Migration status was checked with:

```text
php bin/migrate.php status --group=tests
```

Evidence:

- Database: `mcc_cricket_club_test`
- Available migrations: 66
- Applied history rows: 67
- Existing Admission/Player deployment migrations are present.
- The Achievement migration history remains present, including the pre-existing duplicate `20261007000100` row.
- No migration was run or changed for this verification.

The Admissions scope requires no new migration or schema change. Existing Admission, Player, contact, audit, and assignment migrations must remain applied in dependency order.

## Security review

The passing portions of the suite verify or contractually cover:

- Centralized Admission and Player permissions.
- Server-side branch authorization and cross-branch denial.
- POST/CSRF protection for state-changing routes.
- Checklist, status, and conversion authorization.
- Actor and branch attribution in lifecycle and conversion audits.
- Admission and optional Lead lineage in conversion audit metadata.
- Safe browser responses for unauthorized or cross-branch access.
- No passwords, tokens, or unrelated sensitive fields in the reviewed audit paths.

The failed idempotence path must be corrected without weakening any of these controls.

## Acceptance criteria

Admissions is ready for completion only when all of the following pass:

- Valid AD-01 transitions succeed and invalid transitions fail.
- AD-02 checklist prerequisites and approval authority are enforced.
- Rejected reopening requires a non-empty reason and preserves history.
- Lead-linked and Direct Admissions convert only from the approved state.
- Repeated conversion creates exactly one Player and exactly one set of source-derived contacts.
- Conversion failures roll back all related writes.
- Admission and optional Lead lineage are preserved.
- Cross-branch and missing-permission requests are denied.
- List, detail, edit, lifecycle, and conversion routes remain CSRF-protected where state changes occur.
- Audit metadata contains actor, branch, transition, reason, and lineage without secrets.
- Unauthorized responses and audits do not leak sensitive applicant/contact data.
- Tests use only unique insert-only fixtures and no destructive cleanup.

## Deployment evidence

Repository tests cannot prove deployed PHP SAPI, HTTPS termination, session behavior, filesystem permissions, database concurrency, or production logging. Before release, deployment owners must provide:

- Applied migration evidence in the target environment.
- Role and permission verification for Admission and Player conversion actions.
- HTTPS/CSRF behavior through the deployed web entry point.
- Database transaction and concurrency evidence for repeated conversion.
- Review of application and audit logs for sensitive applicant/contact leakage.

## Deferred relationship and privacy items

The following remain outside this verification and must not be implemented here:

- Guardian visibility, relationship editing, revocation, and portal eligibility (`REL-03` through `REL-05`).
- Medical-information visibility and policy (`DAT-02`).
- New document policy, verification, retention, or medical-document exposure.
- Parent/Student portal behavior.
- New permissions, migrations, relationship rules, or role grants.

`docs/SRS-original.md` was not present in the workspace, so its content hash could not be independently verified. No operation in this task created, edited, or deleted that path.
