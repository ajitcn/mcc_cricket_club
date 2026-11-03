# Admission PDF Capture Deployment Readiness

**Review status:** TEST-VERIFIED; PRODUCTION DEPLOYMENT NOT APPROVED  
**Review scope:** `20261011000300_AddCompleteAdmissionFormFields` and the
bounded Admission PDF capture slice  
**Review date:** 2026-10-11

This document is an operational readiness review. It does not authorize a
production migration, application deployment, permission change, or policy
decision. `docs/SRS-original.md` is absent from this checkout and was not
modified.

## Migration and scope

Migration: `20261011000300_AddCompleteAdmissionFormFields`

Target table: `admissions`.

The migration adds only missing columns. The affected columns are:

- Text/varchar capture: `admission_number`, `academic_year`, `school_college`,
  `previous_coaching_centre`, `ability_overall`, `ability_batting`,
  `ability_bowling`, `ability_wicketkeeping`, `ability_fitness`,
  `batting_order`, `student_signature`, `parent_signature`,
  `authorized_signature`, `year1_age_group`, `year1_plan`, `year2_age_group`,
  `year2_plan`, `year3_age_group`, `year3_plan`, `personal_coaching_days`,
  `personal_coaching_duration`, `group_coaching_days`, `coaching_delivery`,
  `fitness_plan`, `match_program`, `induction_plan`, `review_frequency`,
  `progress_report_frequency`, `feedback_call_frequency`,
  `meditation_sessions`, `elite_camp`, `student_diary`, and `welcome_kit`.
- Numeric capture: `age`, `hostel_required`, `declaration_accepted`.
- Date capture: `admission_date`, `year1_start_date`, `year1_end_date`,
  `year2_start_date`, `year2_end_date`, `year3_start_date`, and
  `year3_end_date`.
- Fee capture: `admission_fee`, `training_fee`, and `hostel_charges`.
- Text areas: `current_address`, `permanent_address`,
  `correspondence_address`, `playing_experience`, `hobbies`, `achievements`,
  `bowling_options`, `coaching_plan`, `installment_plan`,
  `installment_due_dates`, `hostel_payment_plan`, `documents_submitted`, and
  `confirmation_notes`.
- Reference: nullable `coach_user_id`, with the
  `admissions_coach_user_fk` foreign key to `users(id)`, using `ON DELETE SET
  NULL` and `ON UPDATE CASCADE`.

The migration does not create payment processing, hostel allocation,
notification, document approval, signature verification, medical, portal, or
new lifecycle workflows. Those remain capture-only or `NEEDS_DECISION`.

## Test evidence and database target

The verified test target was:

- Database: `mcc_cricket_club_test`
- Group: `tests`
- Migration history before: 72 rows
- Migration history after: 73 rows
- Applied migration: `20261011000300_AddCompleteAdmissionFormFields`
- Applied batch: 57
- Unrelated migration history: preserved; no unrelated migration was rerun
- Focused verification: **65 tests, 732 assertions, 0 skipped, 0 failures,
  0 errors**
- PHP lint: passed for the changed PHP files and Admission migration
- JavaScript syntax: passed with `node --check public/assets/js/app.js`; no
  JavaScript file was changed
- Application defect corrected before final verification: null-safe option
  validation for optional PDF fields

The test evidence is not production evidence. It proves the bounded test-group
schema and Admission behavior only.

## Safety properties

The migration is:

- **Forward-only:** `down()` intentionally performs no rollback because
  Admission intake data must not be removed.
- **Additive:** only missing columns and the missing named foreign key are
  added.
- **Idempotent:** existing columns are checked before `addColumn`; the named
  foreign key is checked before creation.
- **Non-destructive:** it does not delete, truncate, rewrite, or transform
  existing Admission rows.
- **Data-preserving:** existing Admission records remained readable after the
  test migration.

The existing same-version Match migration is already present in test history.
The migration runner identifies migrations by version and class; the
deployment owner must preserve both class files and verify the production
history before applying anything. Do not rename migrations, rewrite history,
or use a broad latest operation when unrelated production migrations are
pending.

## Required backup and recovery posture

Before any approved production deployment:

1. Confirm a current, restorable database backup or snapshot covering the
   `admissions` table and migration-history table.
2. Record backup time, retention, restore owner, restore location, and a
   successful restore-validation result.
3. Confirm application and database maintenance contacts are available.
4. Do not use the empty `down()` method as a destructive rollback mechanism.

If the migration fails after some DDL has completed, stop the deployment,
retain the database state for diagnosis, and involve the database owner. Do
not drop columns, delete Admission rows, truncate tables, disable foreign
keys, or manually rewrite migration history. Because the migration is
idempotent, a separately approved retry may reconcile missing schema after the
failure is understood.

## Ordering and execution controls

Only an explicitly approved production command may be used. Before execution:

- Confirm the environment is production-approved and is not an unapproved
  staging, development, or unrelated database.
- Confirm the database name and connection group independently of application
  input.
- Run a read-only migration status check and save the output.
- Confirm the Admission migration class is pending in the target and that no
  unrelated migration will be applied.
- Confirm the same-version Match migration history and migration files are
  consistent; do not resolve the collision by rewriting history.
- Confirm the preceding Admission schema migrations are already applied and
  the database engine supports the required `ALTER TABLE` and foreign-key
  operations.
- Schedule the change during a low-traffic window with an agreed lock timeout
  and application error-monitoring owner.

Execution should apply this migration as a single, explicitly identified
change. Avoid an unrestricted `latest` operation if the target has any other
pending migration. Capture command output, start/end time, migration result,
history row, and operator identity.

After execution:

- Re-run migration status and confirm the one expected history entry.
- Confirm no unrelated history row changed.
- Confirm every expected column exists with nullable/default/length properties
  matching the migration.
- Confirm `admissions_coach_user_fk` exists with the expected delete/update
  actions.
- Read a representative existing Admission record and verify its values are
  unchanged and readable.
- Run the approved Admission smoke/verification suite against the deployed
  environment without destructive fixture cleanup.

## Runtime and locking considerations

The migration uses `ALTER TABLE admissions`, which can acquire a metadata lock
and may block or be blocked by concurrent Admission reads/writes depending on
the MySQL/MariaDB version and table size. The deployment owner must:

- inspect active sessions and long-running Admission transactions;
- avoid deploying during bulk Admission intake or conversion activity;
- monitor lock waits, migration duration, database errors, and application
  latency;
- stop safely before a forced kill or destructive intervention if lock impact
  exceeds the agreed threshold; and
- retain the migration output and database logs for the change record.

The migration is not an application transaction boundary. DDL behavior and
implicit commits are database-engine dependent, so backup and post-failure
inspection are mandatory.

## Private-data and authorization readiness

The new fields include sensitive address, guardian-adjacent, declaration,
signature, and document-description data. The deployment owner must verify:

- Admission reads and updates pass through `admission.view` and
  `admission.update`, with authenticated sessions and server-side branch
  authorization.
- Create operations require `admission.create`; state-changing Admission
  routes remain POST routes protected by the existing CSRF configuration.
- Batch and Trial references are checked against the Admission branch.
- `coach_user_id` is accepted only for an enabled Coach assigned to the same
  branch; the database foreign key alone is not the authorization boundary.
- Address, guardian, declaration, and signature values are escaped in the
  Admission views and are not written to logs, URLs, public assets, or
  unauthorized API responses.
- `documents_submitted` is capture text only; it does not approve a document
  or bypass document policy.
- Uploaded Admission documents use `DocumentManagementService` and
  `LocalPrivateStorage` under `WRITEPATH/private`, never `public/assets`.
- Document listing, preview, download, upload, removal, and verification apply
  branch and permission checks. Downloads require `document.download`; medical
  documents additionally require `document.medical.view` where applicable.
- Private previews send `Cache-Control: private, no-store`, and storage keys
  cannot escape the private storage root.
- Production secrets, credentials, tokens, sensitive values, and document
  contents are not logged during the deployment or verification.

These controls are existing application paths reviewed for readiness; this
document does not approve new visibility, retention, medical, signature,
document-approval, payment, hostel, notification, or portal policy.

## Monitoring and post-deployment verification

Monitor for at least the agreed observation period:

- migration completion and migration-history consistency;
- database errors, lock waits, deadlocks, and `ALTER TABLE` duration;
- Admission create/edit response errors and validation-error rates;
- authorization denials, cross-branch access attempts, and CSRF failures;
- document upload, private preview/download, scanner, and storage errors;
- audit events for administrative and Admission changes; and
- application latency and error rates on Admission and document routes.

Verification owner should confirm create and edit rendering for every PDF
field, preserved submitted values and validation errors, existing Admission
readability, branch-safe references, private document access, and unchanged
checklist/approval/conversion/idempotency behavior. Payment, hostel allocation,
notifications, document approval, signature, and other unresolved workflows
must remain inactive.

## Deployment-owner approval record

Approval has not been recorded. The values below remain pending because no
deployment-owner identity, decision, schedule, environment, backup evidence,
or operational owner assignments were supplied.

| Field | Value |
| --- | --- |
| Name | PENDING — deployment owner not provided |
| Status | NOT APPROVED — TEST VERIFIED ONLY |
| Approval date | PENDING — not provided |
| Planned deployment date | PENDING — not provided |
| Environment | UNSPECIFIED — no environment approved |
| Backup confirmation | NOT CONFIRMED — backup must be taken and restore-validated first |
| Monitoring owner | PENDING — not provided |
| Verification owner | PENDING — not provided |
| Comments | Do not apply the migration to production or any unapproved environment. Before any approval, confirm the target environment, take and validate a backup, check migration status before and after, verify Admission columns and existing data, rerun the 65-test/732-assertion suite, and check private address, guardian, declaration, signature, and document protections. No destructive rollback or migration-history rewrite is permitted. |

## Change boundary

This review changes documentation only. It does not apply the migration to
production or any unapproved environment, and it does not modify application
code, migrations, database records, permissions, tests, or
`docs/SRS-original.md`.
