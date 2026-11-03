# Admission Form Decision Request

**Status: VERIFIED** — complete Admission PDF capture slice.

## Recorded direction

USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL

The complete September 2026 PDF field set is requested for create/edit capture.
All new values are nullable unless an existing Admission rule already requires
the field. Existing branch, RBAC, CSRF, checklist, approval, audit, and Player
conversion rules remain authoritative.

### Captured now

- Admission number/serial, admission date, branch, academic year, Batch/program.
- Candidate identity, DOB, age, gender, student email and phone.
- Parent/guardian contacts, current/permanent/correspondence addresses.
- School/college, previous coaching centre, playing experience, hobbies,
  achievements, cricket ability/profile, assigned Coach, and Trial reference.
- Coaching plan, admission/training fees, installment plan and due dates.
- Hostel requirement, charges and payment plan.
- Documents submitted, declaration, confirmation, and signature text fields.

### Deferred policy decisions

The form captures values only. Payment collection/status, hostel allocation,
ratings semantics, signature verification, consent evidence, medical policy,
certificate/document approval, notifications, and portal visibility remain
NEEDS_DECISION and are not implemented by this slice.

Approver: ____________________  Approval date: ____________________  Effective date: ____________________

The bounded capture implementation is complete under this user-provided
direction. Existing source code and tests are implementation evidence, not
client approval. Formal policy decisions for privacy, visibility, retention,
ratings, consent, medical data, payment status, hostel allocation, document
approval, notifications, and portal exposure remain `NEEDS_DECISION`.
`docs/SRS-original.md` is not modified.

## Admission plan-master history

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL**

The former Coaching, Fee, Hostel, and Academic Plan masters have been removed
from the development/test application.
Super Admin may view, create, update, deactivate, and retire explicitly
authorized records. Branch Admin may view, create, update, and deactivate only
within assigned branches and may not retire shared/global records. Other roles
are view-only where required by Admission workflow.

Existing Admission and renewal history retains immutable display labels. No
replacement master fields were added. Existing Branch, Program, Batch, Trial,
Coach, and Lead/source data remains unchanged.

The former plan-master capture and management workflow is no longer available.

Formal approver: ____________________  Approval date: ____________________
Effective date: ____________________

The bounded implementation uses existing centralized Admission permissions and
was verified only in `mcc_cricket_club_test`. Migration
`20261011000500_CreateAdmissionMasterData` changed history 73 → 74 at batch 58.

## Master-data optimization decision gate — superseded by implementation record

**Status: TEST-VERIFIED ONLY — deployment approval remains pending.**

The former plan-master decision gate is obsolete after the development/test
removal. Branch, Program, Batch, Trial, Coach, and Lead/source remain reused.

The former plan-master fields, permissions, and replacement-dropdown decisions
are no longer part of the development/test application:

- Whether direct Admissions need a source/campaign reference beyond existing
  Lead and `lead_sources`; no duplicate Application Reference Master is assumed.
- Proposed new management permissions and their role assignments.
- Required nullable Admission references and historical display snapshots.

Until approved, existing Admission free-text fields and controlled options
remain unchanged and all related policies remain `NEEDS_DECISION`.

## Supported now

The current Admission schema now also contains the requested nullable PDF
capture fields. Create and edit use the shared partial, with server-side
validation, escaped output, preserved submitted values/errors, CSRF, centralized
permissions, branch isolation, and authorized Branch/Batch/Trial/Coach
references. The test migration was applied only to `mcc_cricket_club_test`.

Focused evidence: **65 tests, 732 assertions, 0 skipped, 0 failures, 0 errors,
exit code 0**. PHP lint passed for the changed PHP files. JavaScript syntax
check passed with `node --check public/assets/js/app.js`; no JavaScript file was
changed.

## Remaining decisions for reference fields not in the schema

The master-data defaults above are recorded user direction and have a bounded
test-environment implementation. The following separate capture-policy
questions remain unresolved and do not authorize additional workflows.

### ADM-FORM-01 — School or college

- **Current evidence:** Nullable Admission column, service field, shared
  create/edit rendering, and focused test coverage now exist.
- **Recommended secure default:** Keep capture bounded and do not expose the
  value to portals/reports until visibility and retention are approved.
- **Choices:** Do not collect; add a nullable text field; model an institution
  reference catalog.
- **Resolution:** `IMPLEMENTED FOR CAPTURE; POLICY NEEDS_DECISION`
- **Comments:** Implemented under user-provided direction; no client policy
  approval supplied.
- **Approver:**
- **Approval date:**
- **Effective date:**
- **Migration impact:** New forward-only column or catalog and index.
- **Permission/branch impact:** Branch-scoped Admission authorization; define
  sensitive-data visibility before implementation.
- **Acceptance criteria:** Authorized create/edit persistence, validation,
  branch isolation, escaping, and safe detail rendering.

### ADM-FORM-02 — Permanent and correspondence addresses

- **Current evidence:** Nullable current/permanent/correspondence Admission
  fields are rendered and server-validated.
- **Recommended secure default:** Keep addresses private and do not infer Player
  or portal visibility without approved policy.
- **Choices:** Omit; add separate address fields; add a normalized address table.
- **Resolution:** `IMPLEMENTED FOR CAPTURE; PRIVACY POLICY NEEDS_DECISION`
- **Comments:** Address sensitivity, history, and conversion mapping are undefined.
- **Approver:**
- **Approval date:**
- **Effective date:**
- **Migration impact:** New fields/table, indexes, and conversion mapping.
- **Permission/branch impact:** Admission and Player privacy rules required.
- **Acceptance criteria:** Server validation, branch authorization, audit/correction,
  and no unauthorized detail exposure.

### ADM-FORM-03 — Previous coaching centre and Coach relationship

- **Current evidence:** Assigned Coach is a nullable branch-authorized Admission
  reference; previous coaching centre is bounded nullable text; Trial remains
  branch-authorized.
- **Recommended secure default:** Keep the reference same-branch and do not
  infer cross-branch Coach ownership.
- **Choices:** Omit prior centre; add bounded text; add a branch-scoped catalog.
- **Resolution:** `IMPLEMENTED FOR CAPTURE; OWNERSHIP POLICY NEEDS_DECISION`
- **Comments:** No approved source, ownership, or validation rule exists.
- **Approver:**
- **Approval date:**
- **Effective date:**
- **Migration impact:** New nullable field or reference table if approved.
- **Permission/branch impact:** Coach and Admission branch scope must be defined.
- **Acceptance criteria:** Authorized projection, branch isolation, audit, and
  preserved historical values.

### ADM-FORM-04 — Hobbies and achievements

- **Current evidence:** Nullable Admission text fields are rendered with
  server-side limits and escaped output.
- **Recommended secure default:** Keep free text bounded and private until
  visibility and retention are approved.
- **Choices:** Omit; bounded text fields; normalized activities/achievements.
- **Resolution:** `IMPLEMENTED FOR CAPTURE; PRIVACY POLICY NEEDS_DECISION`
- **Comments:** Potential Player/portal/reporting impact is unresolved.
- **Approver:**
- **Approval date:**
- **Effective date:**
- **Migration impact:** New fields/table and audit/retention rules.
- **Permission/branch impact:** Define staff and portal visibility before coding.
- **Acceptance criteria:** Validation, authorization, branch isolation, audit,
  escaping, and safe export behavior if exports are approved.

## Explicitly out of scope

Payment collection/status, refunds, hostel allocation, ratings semantics,
performance scoring, signature verification, consent evidence, medical policy,
certificate/document approval, notifications, portal visibility, and new
lifecycle or conversion rules are not implemented or inferred by this request.
The corresponding form values are capture-only where present.

## Verification record

- Migration command: `php bin/migrate.php latest --group=tests`.
- Database: `mcc_cricket_club_test`; history changed from 72 to 73.
- Applied migration: `20261011000300_AddCompleteAdmissionFormFields`, batch 57.
- Unrelated migrations rerun: 0.
- Final focused tests: 65 passed; 732 assertions; 0 skipped; 0 failures; 0
  errors; exit code 0.
- Initial focused run: 41 errors and 1 failure; corrected null-safe optional
  PDF option validation and explicit field contract marker, then reran green.

## Admission plan-master removal reconciliation

**Status: REMOVED FROM DEVELOPMENT/TEST.**

The former Coaching, Fee, Hostel, and Academic Plan masters were removed from
the development/test application. Existing Admission and renewal history
retains immutable display labels, and no replacement plan fields were added.

The new removal migration ran only in `mcc_cricket_club_test`; it is
forward-only and idempotent. Existing Admission data remains readable, and
`docs/SRS-original.md` was not modified.
