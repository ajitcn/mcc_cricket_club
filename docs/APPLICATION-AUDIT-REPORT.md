# MCC Cricket Club Application Audit

**Audit date:** 2026-10-21  
**Mode:** Read-only static audit with bounded, non-destructive verification  
**Scope:** Authentication, authorization, branch isolation, browser security, data integrity, storage, workflows, migrations, UI contracts, tests, and existing completion evidence.

## Executive summary

The application has a substantial security foundation: global CSRF protection is
enabled, authentication sessions are regenerated and checked against the active
user record, most module routes use centralized permission filters, services
perform branch checks, and private documents are stored outside the public web
root with controlled download authorization.

The audit did not establish production readiness. Four issues require priority
engineering attention: the RBAC mutation service is not transactionally coupled
to its audit write and lacks explicit target/branch validation; dynamic values
are emitted without consistent escaping in several views; the Admission Test
fee synchronizer can leave stale schedule rows when switching to a full-payment
plan while removals are also posted; and the migration inventory contains a
duplicate timestamp plus an applied/available count mismatch. Document uploads
are intentionally quarantined because the scanner is not configured, which is a
deployment blocker for document verification/download workflows.

The authoritative `docs/SRS-original.md` file was not present in the workspace,
so requirements traceability against that source could not be independently
verified. Existing matrices and completion reports were treated as evidence,
not as client approval.

## Verification performed

| Check | Result |
| --- | --- |
| PHP lint | Passed: 648 PHP files under `app` and `tests`, 0 failures |
| JavaScript syntax | Passed: 1 JavaScript file checked (`public/assets/js/app.js`) |
| Migration status | Read-only status completed against `mcc_cricket_club_test`; no pending migration was reported by the runner |
| Unit suite | 303 tests, 5,943 assertions; 1 error, 7 failures |
| Focused contract subset | 51 tests, 1,277 assertions; 3 failures |
| Feature/database suites | Not run: repository suites include destructive fixture cleanup, which was excluded by the audit instructions |
| Production database/schema | Not inspected or changed |
| Browser/device audit | Static view/CSS/JS review only; no authenticated production-like browser session was used |

The Unit failures are separately classified below. They are not treated as a
single application failure without reconciling whether the assertion or the
implementation is authoritative.

## Verified areas

### Authentication and sessions — Verified / Partially verified

Evidence: `app/Services/AuthService.php`, `app/Config/Session.php`,
`app/Filters/Auth.php`, and `app/Config/Filters.php`.

Password authentication checks the database user state, failed-login lockout,
session regeneration, role/permission context, branch assignments, and a
session-version value. Password recovery uses hashed expiring tokens and
invalidates older tokens. Session configuration uses server-side file sessions,
regeneration-on-destroy, and a bounded regeneration interval.

The audit did not exercise external session fixation, cookie attributes under
the deployed web server, reverse-proxy HTTPS behavior, or concurrent session
revocation. Those remain missing deployment evidence.

### CSRF — Verified for browser POST forms

Evidence: `app/Config/Filters.php`, `app/Config/Security.php`, and a static
scan of 90 module view forms.

CSRF is registered globally before application processing. The POST-form scan
found no browser POST form missing `csrf_field()`. The forms without an explicit
token were GET-only filter/search forms. Token rotation is disabled outside
production, so production deployment configuration must still be verified.

### Centralized RBAC and branch isolation — Partially verified

Evidence: `app/Services/AuthorizationService.php`,
`app/Services/BranchContextService.php`, `app/Filters/Permission.php`, and
module services.

The normal pattern is centralized permission checking plus service-level branch
ownership checks. Admission Test, Admission, Documents, Payments, Match
Performance, and several other inspected workflows use authorized branch sets
and expected-version checks.

Some routes expose only `auth` while relying on service authorization, including
selected Trial media/coach/ratings routes, Coach leave cancellation, and Match
Performance POST routes. The inspected service methods do enforce permissions,
so this is not evidence of a confirmed bypass; it is a defense-in-depth gap
and requires route-level regression tests.

### Transactions and optimistic concurrency — Partially verified

Inspected Admission Test fees, personal details, assessment remarks, paper-set
selection, Admission renewal, documents, payments, scheduling, attendance,
and Match Performance services use transactions and/or version predicates.
Admission Test fee updates perform fee and schedule changes in one transaction,
validate branch/test ownership, and reject stale versions.

Coverage is not uniform across every module and could not be proven end-to-end
without running database suites that clean or mutate fixtures. A deployment
review must verify transaction error handling against the actual production
driver and isolation level.

### Private documents and media — Verified security baseline / Deployment blocker

Evidence: `app/Libraries/Storage/LocalPrivateStorage.php`,
`app/Services/DocumentManagementService.php`,
`app/Services/TrialMediaService.php`, and Documents browser routes.

Private storage rejects traversal, absolute paths, drive-qualified paths, and
public-web-root locations. Files use private directories, restrictive modes,
temporary writes, atomic rename, MIME/extension/size validation, and generated
storage keys. Download and preview routes are permission-gated and services
re-check branch ownership and document state.

Uploads are always assigned `scanner_status = QUARANTINED` with reason
`Scanner is not configured.` A document cannot be verified as clean while the
scanner is unavailable. This is security-safe but blocks the intended verified
document workflow until an approved scanner, operational configuration, and
retention/backup policy exist.

### Admission, Admission Test, Trial, Fees, and Follow-ups — Partially verified

The inspected Admission Test service preserves selected paper-set locking,
submitted answers, version checks, branch authorization, fee-row IDs, and
transactional fee/audit updates. Admission services contain request-hash based
idempotency for the inspected create/renewal path. Existing Admission and
Meeting Follow-up services use server-side validation and transactions.

The Admission Test fee UI statically uses shared datepicker hooks and no native
`type="date"` was found in the Admission Test views. The broader application
still contains a native date input in `app/Views/dashboard.php:90`, so the
shared datepicker standard is not universal.

Player, Attendance, Coach Feedback, Scheduling, Documents, Fees, Payments,
Matches, Tournaments, and Portal modules all have source, routes, and tests,
but an end-to-end production-like audit was not possible without mutating
fixtures. Existing completion reports cover bounded slices only.

## Confirmed defects

### AUD-001 — RBAC mutations are not transactionally coupled to audit writes

- **Module/path:** Authorization administration; `app/Services/RbacService.php`.
- **Workflow:** Role and branch assignment/revocation.
- **Evidence:** `assignRole`, `revokeRole`, `assignBranch`, and `revokeBranch`
  mutate the database and then call `auditPersistence->record()` without a
  transaction, target existence validation, or explicit target-branch scope.
  The service is also compressed into one-line methods, making review harder.
- **Severity/classification:** High / Confirmed defect.
- **Security/data impact:** A mutation can succeed while its audit write fails;
  duplicate assignment is silently ignored; invalid target IDs are not clearly
  rejected.
- **Branch/permission impact:** The caller needs `rbac.manage`, but the service
  does not independently validate the target user, role, or branch relationship.
- **Migration/schema impact:** None directly.
- **Remediation:** Move each mutation into a readable service transaction;
  validate target existence and authorized branch scope; make the audit write
  part of the same unit of work; return explicit conflict/not-found outcomes.
- **Required tests:** Transaction rollback on audit failure, invalid target,
  cross-branch target, duplicate assignment, revoke idempotency, audit metadata,
  and permission denial.
- **Client approval:** No for the security hardening; permission-matrix changes
  require approval if target scope is broadened.

### AUD-002 — Dynamic output is not consistently escaped

- **Modules/paths:** Representative evidence includes
  `app/Modules/Performance/Views/form.php:26`,
  `app/Modules/Matches/Views/form.php:94,109`,
  `app/Modules/Matches/Views/performance.php` option/textarea values,
  `app/Modules/Tournaments/Views/form.php:84,97`,
  `app/Modules/Tournaments/Views/fixture_form.php:133`,
  `app/Modules/CRM/Views/show.php:357,387,421`,
  `app/Modules/CRM/Views/follow_up.php:18`, and
  `app/Modules/Admin/Views/branch.php:28`.
- **Workflow:** Form option values/labels, rating values, filter state, and a
  dynamic JavaScript confirmation string.
- **Evidence:** These locations interpolate values without `esc()` or an
  equivalent JavaScript-context encoder. Several values are currently enums or
  numeric IDs, but the source does not enforce that assumption at the output
  boundary.
- **Severity/classification:** Medium / Confirmed defect.
- **Security/data impact:** Stored or reflected HTML/attribute/JavaScript
  injection risk if an option, label, or transition value becomes user- or
  administrator-controlled.
- **Branch/permission impact:** A successful injection would execute in the
  current authorized user context.
- **Migration/schema impact:** None.
- **Remediation:** Escape HTML text/attributes with `esc()`, use a dedicated
  JavaScript encoder for script contexts, and constrain enum output server-side.
- **Required tests:** Malicious option/label/transition fixtures, attribute and
  script-context assertions, and regression checks for all affected views.
- **Client approval:** No; this is output-safety remediation.

### AUD-003 — Admission Test full-payment switching can leave stale schedule rows

- **Module/path:** Admission Test fees; `app/Modules/AdmissionTests/Services/AdmissionTestService.php:788-870`.
- **Workflow:** Fee-plan update from installment to full payment.
- **Evidence:** `syncFeeRows()` clears existing rows only when
  `$clearWhenNotRequired` is true **and** `$removedIds === []`. If a non-empty
  removal list is posted while the plan is no longer installment-based, only the
  explicitly listed IDs are deleted; other existing rows remain.
- **Severity/classification:** Medium / Confirmed defect.
- **Security/data impact:** Stale fee schedules can be displayed or projected
  after the payment plan says they are not applicable.
- **Branch/permission impact:** The existing branch/test checks remain in place;
  this is a data-integrity issue within an authorized update.
- **Migration/schema impact:** None.
- **Remediation:** Treat a non-installment plan as authoritative and clear all
  current rows within the same transaction after validating the submitted plan;
  retain explicit removal validation and audit before/after data.
- **Required tests:** Switch with no removals, switch with partial removals,
  retry, rollback, audit metadata, and reload projection.
- **Client approval:** No if this restores the existing stated fee-plan rule;
  approval is required if business policy intentionally permits retained hidden
  schedules.

### AUD-004 — Migration inventory has duplicate version identity

- **Module/path:** `app/Database/Migrations/20261011000300_AddCompleteAdmissionFormFields.php` and
  `app/Database/Migrations/20261011000300_AddMatchTournamentLinkageVersion.php`.
- **Workflow:** Migration discovery and deployment.
- **Evidence:** The migration directory contains two files with the same
  `20261011000300` prefix. Read-only test status reported 100 available versus
  103 applied history rows and also showed duplicate historical version/class
  entries for earlier migrations.
- **Severity/classification:** High / Deployment blocker.
- **Security/data impact:** A fresh or differently configured environment may
  apply migrations in an ambiguous order or fail to identify the intended
  version, causing schema drift.
- **Branch/permission impact:** Indirect; schema drift can affect authorization
  and branch-scoped constraints.
- **Migration/schema impact:** Direct deployment and repeatability risk.
- **Remediation:** Reconcile migration identity/history with the deployment
  owner; assign unique forward-only versions, verify applied checksums/classes,
  and test a clean schema plus an upgrade schema. Do not rename or delete
  migration history without an approved deployment procedure.
- **Required tests:** Fresh install, upgrade from current production state,
  repeated status/apply dry runs, duplicate-column checks, and schema/index/FK
  verification.
- **Client approval:** Yes for production migration-history repair and any
  migration renaming strategy.

## Security and data-integrity findings requiring evidence or hardening

### Route-level defense in depth — Partially verified / Medium

Some authenticated routes omit an explicit permission filter and rely on the
service layer: selected CRM Trial media/ratings/coach routes, Coach leave
cancellation, and Match Performance POST routes. Inspected services do perform
authorization, so no direct bypass was confirmed. Add route-level permission
filters where practical and retain service checks; add direct-route denial tests.

### Idempotency coverage — Missing evidence / Medium

Admission create/renewal has inspected request-hash handling, and several update
services use optimistic versions or unique keys. The audit did not prove an
idempotency key across every create, conversion, payment, renewal, upload, and
retry workflow. In particular, document version upload and some scheduling or
match creation paths require a bounded retry/concurrency review. Do not add a
global key without deciding its scope and retention policy.

### Audit integrity — Partially verified / Medium

Many critical services record actor, branch, entity, and before/after data, but
the RBAC defect above demonstrates that audit persistence is not uniformly part
of the same transaction. A cross-module audit completeness and failure-injection
test is still required.

### Sensitive data and PDFs — Missing evidence / Medium

Private document services and permission-gated reads were statically verified.
The audit did not execute every PDF/export path or inspect all generated PDF
templates for sensitive-field minimization, authorization, temporary-file
cleanup, and response headers. This is especially relevant to Admission,
Admission Test, Player, and report exports.

## Migration and deployment blockers

1. Duplicate migration timestamp identity and status/history count mismatch
   described in AUD-004.
2. Document scanner is not configured; new uploads remain quarantined.
3. Private storage deployment evidence is incomplete for encryption, backup,
   retention, restore testing, and web-server deny rules.
4. `docs/SRS-original.md` is absent, so authoritative requirement traceability
   cannot be completed.
5. The existing reports record multiple client approval gates as blank or
   `NEEDS_DECISION`; those boundaries must not be treated as implementation
   approval.

## Test results and failure separation

### Passing checks

- PHP lint: 648 files, 0 failures.
- JavaScript syntax: 1 file, 0 failures.
- Migration status command: completed read-only; no pending migration reported by
  the configured test migration runner.

### Unit suite failures

`vendor/bin/phpunit --configuration phpunit.dist.xml tests/Unit` reported
303 tests and 5,943 assertions, with 1 error and 7 failures:

- Error: `Tests\Unit\OpponentManagementContractTest::testSavePreservesExplicitCreateAndEditTargets` — undefined `$id` in the test contract.
- Failure: `AdmissionCompleteFormContractTest::testCompletePdfFieldGroupsUseSharedControlsAndExistingWorkflow` — expected 2, actual 4.
- Failure: `AdmissionPdfContractTest::testAdmissionDownloadRouteIsPermissionGatedAndUsesCanonicalNumber` — contract expects a different canonical filename.
- Failure: `AdmissionPlanRemovalContractTest::testHistoricalLabelFieldsRemainInAdmissionModelsAndViews`.
- Failure: `AdmissionPlayerConnectivityContractTest::testServicesProjectCanonicalLineageAndGuardConversionPlacement`.
- Failure: `AdmissionTestContractTest::testRestoredAdmissionTestFeeSectionOrderHasOneWrapper`.
- Failure: `PlayerDevelopmentContractTest::testDevelopmentSelectorsAreBranchScopedAndCoachAuthorized`.
- Failure: `SessionManagementContractTest::testSessionFormsUseAuthorizedRelationshipSelectors`.

The first failure is a broken test variable. The remaining failures require
triage against current intended implementation; they are not silently counted
as product defects.

### Focused subset

The focused subset of Admission Test, Admission form, Documents, and Session
Management contracts reported 51 tests and 1,277 assertions with 3 failures:
the Admission Test fee-section-order assertion, the Admission complete-form
contract, and the Session Management selector contract. This confirms that the
current focused contract baseline is not clean.

Feature and database suites were intentionally not executed because the audit
was required to avoid destructive cleanup, truncation, rollback, or production
migrations; the repository’s database tests contain fixture-reset behavior.

## Existing completion reports and known blockers

Existing reports document bounded completion for Admission forms, Admission/Test
flows, Documents metadata, Attendance/Coach Feedback, Scheduling, Match Player
Performance, and selected Match/Tournament linkage. They do not constitute a
consolidated end-to-end security sign-off. Existing matrices explicitly retain
many policies as `NEEDS_DECISION`, including ratings/scoring modes, payment and
hostel policy, document approval/scanning, notifications, portal visibility,
retention, exports, and broader Tournament rules.

The prior `docs/CLIENT-REFERENCE-REMAINING-WORK-AUDIT.md` is retained and
updated with this audit’s new evidence; no existing client-approval boundary is
changed.

## Deferred NEEDS_DECISION items

- Authoritative requirements source and reconciliation because
  `docs/SRS-original.md` is absent.
- Final RBAC target-scope and approval matrix for administrative role/branch
  mutation.
- Document scanning provider, verification authority, encryption, backup,
  retention, and portal/document visibility policy.
- Idempotency key scope, retry retention, and duplicate policy across uploads,
  scheduling, matches, conversions, payments, and renewals.
- Admission/Trial ratings, payment/hostel/fee policy, portal visibility,
  notifications, exports, and retention/archival rules where existing matrices
  mark them unresolved.
- Tournament lifecycle, ownership, standings source, corrections, and
  cross-branch policy.

## Exactly one recommended next safe action

Create a deployment-owner reconciliation ticket for the migration identity/history
blocker (AUD-004), including a clean-install and upgrade dry run, before any
production migration or further schema-dependent release.
