# Admission Form Implementation Matrix

**Status: VERIFIED** — complete Admission PDF capture slice.

> USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL

The September 2026 admission PDF is the requested presentation and capture
reference. The implementation captures its complete field set through the
current Admission aggregate and new nullable intake columns. Payment
collection, hostel allocation, document approval, signature verification,
notifications, and other downstream workflows remain capture-only or deferred.

| PDF section | Implemented fields | Persistence | Validation/security | Deferred behavior |
| --- | --- | --- | --- | --- |
| Office use and academic plan | Admission number, admission date, branch, academic year, Batch/program, year 1-3 dates, age, assigned Coach | `admissions` and existing branch/Batch/Coach relations | Authorized branch, active Coach, MCC date parsing, CSRF | No automatic fee or timetable workflow |
| Applicant and family | Existing identity/contact fields plus school/college, parent contacts, addresses | `admissions` | Escaped output, server-side limits, branch authorization | No new portal or medical visibility |
| Cricket profile | Playing experience, hobbies, achievements, ability/rating text, batting order and bowling options | `admissions` | Nullable text capture, no invented scoring | No Performance/KPI calculation |
| Coaching plan | Plan, fees, training fee, installment plan/due dates | `admissions` | Nullable non-negative fee values | No payment gateway, collection, reminders, or reconciliation |
| Hostel | Requirement, charges, payment plan | `admissions` | Nullable capture, branch authorization | No allocation or billing workflow |
| Documents and declaration | Documents submitted, declaration flag, signature/confirmation text | `admissions` plus existing private Documents workflow | Existing upload authorization and CSRF | No signature verification or document approval |

Migration `20261011000300_AddCompleteAdmissionFormFields` was applied only to
`mcc_cricket_club_test` as batch 57; migration history changed from 72 to 73
rows. It is forward-only, additive,
non-destructive, and idempotent: it adds only missing nullable/intake columns
and the Coach foreign key. Existing Admission data remained readable; the
existing unrelated same-version Match migration history was preserved.

The captured coaching-plan option vocabulary follows the PDF: Personal Coaching
(1 Day, 2 Days, 3 Days, 4 Days, 5 Days; 1 hour, 1.5 hours, 2 hours), Group
Coaching (2 Days, 3 Days, 4 Days, 6 Days), Indoors + Outdoors, ground or gym
strengthening/fitness, Match Simulation, Practice Match, Tournaments (as per
availability and performance), Induction after the admission, 3 or 6 month
review/progress/feedback cycles, Meditation Sessions Weekly once, High
Performance Elite Camp, Student Diary, and the listed Welcome Kit quantities.

## Scope and evidence

The reference is `C:\Users\ajitc\Downloads\NEW Asmission Form Sep 2026.pdf`.
This matrix compares the reference form with the current Admission module. The
current schema and `AdmissionService` remain authoritative for implementation.

| Reference area | Existing supported field or flow | Current UI | Action in this slice | Status |
| --- | --- | --- | --- | --- |
| Admission date | `admissions.joining_date` | Create and edit datepicker | Preserve and standardize datepicker presentation | Implemented |
| Branch | `admissions.branch_id` | Create branch selector; edit read-only branch | Preserve authorized branch options and ownership | Implemented |
| Batch/program | `admissions.batch_id` | Create/edit selector | Use shared searchable select and preserve values | Implemented |
| Trial | `admissions.trial_id`, existing `trials` table | Create selector; edit selector added | Use branch-authorized Trial options and preserve values | Implemented |
| Student identity | Applicant name, email, phone, DOB, gender, age group | Create/edit fields | Add missing direct-intake email and phone inputs; retain edit values | Implemented |
| Student contact | `applicant_email`, `applicant_phone` | Edit available; create previously omitted | Add create fields with existing names and validation | Implemented |
| Parent/guardian contact | Parent names, mobile, email, emergency contact | Create/edit fields | Preserve existing section | Implemented |
| Cricket profile | Playing role, batting/bowling style, strong hand, jersey size | Create/edit fields | Preserve existing options and validation | Implemented |
| School/college | Nullable Admission capture field | Create/edit shared partial | Server-side length validation, escaping, branch authorization | Policy/visibility approval remains `NEEDS_DECISION` |
| Permanent/correspondence address | Nullable Admission text fields | Create/edit shared partial | Server-side limits, escaping, controlled detail access | Privacy/retention/conversion policy remains `NEEDS_DECISION` |
| Coach / previous coaching centre | Branch-authorized Coach reference plus nullable prior-centre capture | Create/edit shared partial | Active same-branch Coach validation; escaped prior-centre text | Coach ownership/visibility policy remains `NEEDS_DECISION` |
| Hobbies and achievements | Nullable Admission text fields | Create/edit shared partial | Server-side limits and escaping | Privacy/retention/reporting policy remains `NEEDS_DECISION` |
| Fees, installments, hostel, signatures, medical, certificates | Nullable capture fields where requested | Create/edit shared partial | Existing server-side validation and authorization | Payment, allocation, verification, medical, and approval workflows deferred |

## Preserved controls

- Existing Admission routes, permissions, CSRF protection, branch isolation,
  checklist rules, audit history, and idempotent Player conversion remain in use.
- Existing server-side `AdmissionService` validation and field names are reused.
- Submitted values and field-level errors continue to use `old()` and the
  existing `field_errors` flow.
- No production data was changed. The test migration was applied only to
  `mcc_cricket_club_test`; existing test Admission records remained readable.

## Verification targets

Verification result: **65 tests, 732 assertions, 0 skipped, 0 failures, 0
errors, exit code 0**. The focused suite covered Unit contracts, Feature
routes, browser/database-safe behavior, documents, complete-form rendering,
validation and submitted-value preservation, branch-scoped references, CSRF,
permissions, checklist/approval, conversion lineage, and idempotency. No
separate visual-parity test file exists; complete-form contracts and route
rendering are the repository’s available visual-parity evidence.

PHP lint passed for the changed PHP files. JavaScript syntax check passed with
`node --check public/assets/js/app.js`; no JavaScript file was changed.
The null-safe option-validation defect was fixed before the final verification.

## New Admission UI presentation verification

**Status: VERIFIED** — the New Admission UI presentation slice is complete.

| Presentation area | Verified result |
| --- | --- |
| Section organization | Applicant, guardian, address, academic/cricket, coaching, fees/installments, hostel, documents/declarations, signatures/confirmation, and medical notes are clearly grouped. |
| MCC presentation | Shared MCC cards, section titles, labels, field heights, spacing, validation placement, and `mcc-form-actions` are used consistently. |
| Responsive layout | Two columns are used at desktop widths and one column at mobile widths. |
| Select/date controls | `btSelect` alignment, width, overflow, z-index, and shared datepicker layering are corrected. |
| Preservation | Field names, submitted values, errors, CSRF, routes, permissions, and workflow behavior remain unchanged. |
| Verification | **11 tests, 231 assertions, 0 skipped, 0 failures, 0 errors**; PHP lint passed; JavaScript syntax passed with no JavaScript changes. |

The repository does not contain `docs/SRS-original.md`; it was not modified.

## Admission plan-master removal

**Status: REMOVED FROM DEVELOPMENT/TEST.**

The former Coaching, Fee, Hostel, and Academic Plan masters were removed from
the development/test application. Existing Branch, Program, Batch, Trial,
Coach, and Lead/source data remains reused.

The former plan-master design and its management tests are obsolete. Historical
Admission and renewal labels remain preserved, and no replacement plan fields
were added.

## Admission plan-master removal reconciliation

**Status: REMOVED FROM DEVELOPMENT/TEST.**

The former plan-master slice is no longer part of the development/test
application. Existing Admission display-label snapshots remain readable.

Branch isolation and role authorization remain server-side for the existing
Admission workflow.

The new removal migration runs only in `mcc_cricket_club_test`, is forward-only
and idempotent, and leaves existing Admission data readable. `docs/SRS-original.md`
was not modified.
