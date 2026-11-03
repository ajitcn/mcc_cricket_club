# Lead/CRM → Trial → Admission → Player Evidence Completion Report

**Scope:** Insert-only evidence package for the complete Lead → Trial →
Admission → Approved → Player conversion sequence.

## Evidence added

`tests/database/LeadTrialAdmissionPlayerFlowSafeTest.php` adds uniquely marked
fixtures and verifies:

- Lead creation in an authorized branch.
- Trial booking linked to the Lead.
- Trial attendance and selection transitions.
- Admission Pending creation linked to the selected Trial and Lead.
- Required checklist completion and Admission approval.
- Player conversion preserving both `lead_id` and `admission_id`.
- Retry conversion returning the existing Player.
- No duplicate Player or contact relationships on retry.
- Invalid Trial and Admission transitions.
- Cross-branch and missing-permission rejection.
- Audit actor, branch, state-transition and lineage metadata.
- Conversion rollback when conflicting Player lineage is present.

The fixture is insert-only. It does not call `emptyTable()`, `truncate()`,
destructive cleanup deletes, foreign-key disabling, migration rewrites, or
production commands.

## Existing evidence reused

The new package complements, rather than duplicates, existing coverage:

- `TrialManagementIntegrationTest` covers Trial booking, attendance, selection,
  Admission Pending, duplicate booking, CSRF, branch access and Trial history.
- `AdmissionsIntakeIntegrationTest` covers Admission creation, checklist,
  lifecycle transitions, reasons, rollback, conversion lineage and terminal
  protection.
- `PlayerWriteWorkflowSafeTest` covers conversion lineage, contact idempotency,
  authorization and repeated conversion.
- `AdmissionRoutesTest` and `AdmissionsBrowserSafeTest` cover explicit routes,
  permission filtering, branch denial and browser presentation.
- `docs/ADMISSIONS-COMPLETION-REPORT.md` and
  `docs/ADMISSION-REDIRECT-COMPLETION-REPORT.md` provide completion evidence
  for Admission lifecycle, conversion and explicit error targets.

## Preserved controls

The evidence package does not alter application behavior. It verifies existing:

- Centralized permissions and server-side branch isolation.
- CSRF-protected POST routes.
- Transactional writes and row locking.
- Append-only Trial and Admission history and central audit events.
- MCC date/time validation and existing persistence conventions.
- Conversion retry idempotency and conflict protection.

## Verification status

- Complete Lead → Trial → Admission → Approved → Player conversion evidence is
  verified.
- Trial booking, attendance, selection, and Admission Pending linkage are
  covered.
- Checklist approval, conversion lineage, idempotent conversion, contact
  stability, invalid transitions, cross-branch denial, permission denial, audit
  metadata, and rollback behavior are covered.
- Fixtures are uniquely marked and insert-only.
- PHPUnit result: **26 tests, 365 assertions, exit code 0**.

## Migration and scope status

- No migration was added, applied, rewritten, or rolled back.
- No application code, routes, permissions, business rules, or database cleanup
  was performed. The evidence package uses insert-only fixtures only.
- `docs/SRS-original.md` was not modified.
- Trial mode/scoring/media, Admission meeting, fees, hostel, ratings, cricket
  ability, signatures/consent, medical requirements, guardian visibility,
  notifications, retention and financial workflows remain `NEEDS_DECISION`.

