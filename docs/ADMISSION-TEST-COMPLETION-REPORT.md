# Admission Test Completion Report

## Status

`IMPLEMENTED_AND_AUDITED_WITH_DOCUMENTED_NEEDS_DECISION_ITEMS`

The Admission Test module is implemented using `docs/reference/Back Side.pdf` and `docs/reference/Front Side Selection Paper.pdf`. Exact extracted labels, fields, options, layout notes, and unresolved items are recorded in the implementation matrix. No question text, marks, pass threshold, or approval rule was invented where the paper was incomplete or ambiguous.

## Completed analysis

- Inspected existing Trial routes, controller, service, models, views, and tests.
- Inspected existing Admission model fields, routes, services, follow-up workflow, and Trial-to-Pending-Admission conversion path.
- Inspected CRM navigation and identified `NavigationService` as the menu source.
- Created `ADMISSION-TEST-IMPLEMENTATION-MATRIX.md`.
- Created `ADMISSION-TEST-DECISION-REQUEST.md`.
- Added the `Admission Test` module immediately after `Trials` in the CRM navigation.
- Added the Trial-list `Start Admission Test` CSRF-protected action with the Trial primary key.
- Added Trial-linked Admission Test, structured response, and Test Follow-up persistence.
- Added Personal Details, a required server-locked Paper Set A/B/C selection, Test Started/Test Submitted flow, version checks, audit events, and transactional rollback paths.
- Audited all 30 configured A/B/C questions against the extracted Selection Paper contract. Legacy assessment columns remain nullable for stored-record compatibility, but the Admission Test workflow no longer captures or renders paper-set ratings.
- Updated Basic Test rendering and persistence to submit only the selected 10-question paper set; the other paper definitions remain in the catalog but are not rendered or accepted for the test.
- Removed the Admission Test paper-set rating section from the wizard, review, correction UI, and new submission path. Existing rating columns and historical records remain readable at the storage level for backward compatibility; Trial Coach ratings and Admission ratings were not changed.
- Added correction history snapshots for submitted responses, plus complete submitted-response/revision rendering on the review page. Historical assessment JSON remains readable by the service but new snapshots no longer write rating values.
- Reused the existing Trial-to-Pending-Admission conversion service and linked the resulting Admission back to the Test with retry idempotency.
- Added branch-scoped permission checks, explicit routes, controlled lineage links, and responsive shared MCC form/card presentation.
- Added Lead-first Personal Details prefill with exact Back Side.pdf mappings, same-branch Admission fallback, draft/value precedence, editable fields, and non-mutating source projection.
- Added a separate post-submission Fees section with Coaching Fee, Hostel Fee, existing payment-plan options, total calculation, full-payment `Not applicable` states, and editable coaching/hostel installment schedules using the shared datepicker.
- Added versioned fee updates with server-side validation, CSRF and permission/branch enforcement, transactions with rollback, required update reasons, before/after audit metadata, and conversion copying into the existing Admission fee structures. Admission Test answers/results and Follow-up history remain independent.

## Migration and tests

- Migration: `20261019000200_CreateAdmissionTestWorkflow` applied to the `tests` database. It adds the missing Back Side fields, `admission_test_id`, `admission_tests`, `admission_test_responses`, and `admission_test_followups`.
- Follow-up migration: `20261019000300_AddAdmissionTestAssessmentHistory` adds nullable assessment marks and `admission_test_submission_history`.
- Fees migration: `20261021000100_AddAdmissionTestFees` adds capture-only Admission Test fee fields and branch-scoped coaching/hostel installment tables.
- PHP lint: passed for 18 changed PHP files.
- JavaScript syntax: passed for 1 repository JavaScript file.
- Admission Test focused tests: `6 tests, 51 assertions` passed, including schema, idempotent creation, structured response persistence, route, and PDF contract coverage.
- Trial regression subset: `31 tests, 616 assertions` passed.
- Trial, Admission, Follow-up regression selection: the previously completed selection passed `59 tests, 1161 assertions`.
- A broader legacy Admission filter still exposes one pre-existing PDF filename expectation mismatch in `AdmissionRoutesTest`; no Admission Test code causes that failure.

## Next implementation gate

Resolve the remaining `NEEDS_DECISION` items for incomplete Selection Paper prompts, printed wording, score calculation, result values, status transitions, approval/rejection, follow-up policy, and conversion eligibility before enabling derived scoring or approval automation.
