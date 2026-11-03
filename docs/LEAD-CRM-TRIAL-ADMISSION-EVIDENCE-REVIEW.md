# Lead/CRM → Trial → Admission Evidence Review

**Review date:** 2026-10-11  
**Mode:** Documentation-only route, source, test, and completion-report review

No application code, migrations, permissions, database records, routes, business
rules, or `docs/SRS-original.md` were modified.

## Evidence boundary

This review traces the existing Lead/CRM, Trial, Admission, and Player
conversion implementation. Existing user-provided direction is treated as scope
evidence only and is not client approval. A flow is **Verified** only where the
bounded behavior has source evidence and focused test or completion-report
evidence. **Partially verified** means the behavior exists and has component
coverage, but a single cross-module evidence package is missing.

## End-to-end state trace

| Step | Route/controller entry point | Service/model relationship | Authorization, branch, CSRF, audit and transaction evidence | Transition and error behavior | Time and test evidence | Classification |
| --- | --- | --- | --- | --- | --- | --- |
| Lead → Trial Booked | `GET/POST /crm/trials/from-lead/{lead}` → `App\Modules\Trials\Controllers\Browser::bookPage()` / `book()` | `TrialService::bookingPage()` and `book()` load `leads`, `branches`, active Coach assignments and Grounds; `trials.lead_id` and `trials.branch_id` preserve the relationship | `trial.book` is checked against the Lead branch; Coach and Ground are revalidated for that branch; POST is CSRF-protected; booking, Trial history, Lead status history and audit are written in a transaction; future duplicate booking is rejected | A Lead must exist, be eligible, and not have a future booking. Invalid date, time, Coach, Ground or remarks return safe input-preserving errors. Lead status becomes `trial booked` | `TrialManagementIntegrationTest::testBookingAttendanceSelectionAndAdmissionPendingAreAudited`, duplicate-booking and browser CSRF tests; no consolidated completion report | **Verified** |
| Trial Booked → Trial Attended | `POST /crm/trials/{id}/attendance` → `Trials\Controllers\Browser::attendance()` | `TrialService::attendance()` locks the Trial, updates `trials.status`, writes `trial_history`, updates the related Lead status and audit | `lead.update` and the Trial branch are rechecked server-side; CSRF and transaction handling remain in the browser/service path | Only `booked` Trials accept `attended` or `no_show`; repeated or terminal updates are rejected safely. `attended` moves the Lead to `trial attended` | Integration coverage verifies booking/attendance/audit and no-show/cross-branch handling; no single flow report | **Verified** |
| Trial Attended → Selected | `POST /crm/trials/{id}/selection` → `Trials\Controllers\Browser::selection()` | `TrialService::selection()` updates `trials.selection_outcome`, appends Trial history, updates the Lead status when selected, and records audit | `lead.update`, Trial branch, CSRF and transaction checks are enforced; Trial is locked before transition | Selection accepts only `selected` or `not_selected`, only after `attended`, and only while the outcome is `pending`; otherwise a safe error is raised | `TrialManagementIntegrationTest::testSelectionRequiresAttendance` and the audited workflow test cover the transition; no end-to-end Lead-to-Admission report | **Verified** |
| Selected → Admission Pending | `POST /crm/trials/{id}/admission-pending` → `Trials\Controllers\Browser::admissionPending()` | `TrialService::admissionPending()` calls `AdmissionService::createFromTrialPending()`; the new Admission stores `lead_id`, `trial_id`, `branch_id` and starts at `INTAKE`; Lead status becomes `admission pending` | Route filter requires `lead.update`; the nested Admission service additionally requires `admission.create`, and both services recheck branch ownership. CSRF, row locks, transaction rollback, Admission history and audit are present. Repeated creation returns the existing same-branch Admission | Only an attended Trial with `selection_outcome=selected` can transition. Invalid source, branch mismatch, inactive permission or duplicate lineage is rejected | `TrialManagementIntegrationTest::testAdmissionPendingDoesNotExposeTheSameTransitionAgain`; `AdmissionsIntakeIntegrationTest::testAuthorizedIntakePreservesLeadAndStartsAtIntake`, duplicate and cross-branch tests | **Verified** |
| Admission Pending → Admission | `POST /admissions/from-lead/{lead}` and Trial Admission Pending call `AdmissionService::createFromLead()` / `createFromTrialPending()`; `GET /admissions/{id}/page` enters `Admissions\Controllers\Browser::showPage()` | `AdmissionService` links `admissions.lead_id`, optional `trial_id`, `branch_id`, checklist fields, status history and audit; detail projection joins Lead, Trial, Coach, Branch and Batch | `admission.create`/`admission.view` are enforced against the Admission branch; Lead, Trial, Batch and branch equality are revalidated; state-changing routes use POST/CSRF; creation is transactional and duplicate intake is rejected | Lead-linked creation accepts only eligible Lead states and a valid same-branch Trial when supplied; source and cross-branch failures return safe errors; initial status is `INTAKE` | Admission intake integration tests cover creation, prefilled values, invalid/ineligible Lead rollback and duplicate prevention; Admission Trial Summary report covers authorized projection and empty state | **Verified** |
| Admission → Approved | `POST /admissions/{id}/status` or `/admissions/{id}/complete` → `Admissions\Controllers\Browser::status()` / `complete()` | `AdmissionService::transition()` and `complete()` update `admissions`, append `admission_status_history`, set approval actor/time and record audit | `admission.update` and branch authorization are rechecked; completion also requires `player.convert`; POST/CSRF, transactions and checklist prerequisites are preserved | The documented Admission lifecycle matrix rejects invalid transitions, terminal edits, missing reasons for rejection/withdrawal and approval without a complete checklist. Approval records `approved_by`, `approved_at`, and checklist completion | `AdmissionsIntakeIntegrationTest` covers allowed/forbidden transitions, checklist prerequisites, reason requirements, approval and rollback; `ADMISSIONS-COMPLETION-REPORT.md` records 21 tests/274 assertions for the earlier bounded lifecycle suite | **Verified** |
| Approved Admission → Player conversion | `POST /admissions/{id}/convert` or `/complete` → `Admissions\Controllers\Browser::convert()` / `complete()` → `AdmissionService::convert()` / `convertApprovedWithinTransaction()` | Conversion creates or reuses `persons`, `players`, `player_branch_history`, optional batch assignment and admission contact links; `players.admission_id` and optional `lead_id` preserve lineage; Admission becomes `CONVERTED` | `player.convert` plus Admission branch checks are enforced; row locking, transaction rollback, audit/history and normalized contact reuse preserve retry safety; browser exception targets explicitly return to the Admission detail | Only approved Admissions convert. Existing conversion returns the same Player; conflicting lineage and incomplete identity fail without partial writes. Browser failures return to `/admissions/{id}/page` | `AdmissionsIntakeIntegrationTest::testConversionCreatesOnePlayerWithLineageAndIsIdempotent`, rollback and conflict tests; `ADMISSION-REDIRECT-COMPLETION-REPORT.md` records 22 tests/311 assertions and explicit error targets; `ADMISSIONS-COMPLETION-REPORT.md` records lifecycle/conversion evidence | **Verified** |

## Route and controller inventory

### Lead/CRM

- `app/Modules/CRM/Config/Routes.php` defines Lead list, create, detail,
  update, assignment, status and follow-up routes under the authenticated
  `crm` group.
- `App\Modules\CRM\Controllers\Browser` delegates Lead reads and writes to
  `CrmService`; Lead detail projects related Trials and Admissions.
- `CrmService` uses `LeadModel`, Lead status history, follow-up models and
  branch-aware queries. Lead status changes are transactional and audited.

### Trial

- Trial routes are registered in `CRM/Config/Routes.php` under `/crm/trials`.
- `App\Modules\Trials\Controllers\Browser` delegates booking, rescheduling,
  cancellation, attendance, selection and Admission Pending to `TrialService`.
- `TrialService` uses `TrialModel` and `TrialHistoryModel`; each transition
  locks the Trial, validates the current state, updates Lead status where
  appropriate, appends history and records audit metadata.

### Admission and conversion

- Admission routes are defined in `app/Modules/Admissions/Config/Routes.php`.
- `Admissions\Controllers\Browser` delegates intake, checklist, status,
  completion and conversion to `AdmissionService`.
- `AdmissionModel`, `AdmissionStatusHistoryModel` and `PlayerConversionModel`
  support persisted status history and conversion lineage. The service also
  coordinates Player, contact, batch, branch-history and private-document
  relationships within transactions.

## Security and persistence checks

- **Branch isolation:** Lead, Trial and Admission records carry `branch_id`.
  Services recheck the persisted branch against authorized branch context;
  Trial Coach/Ground and Admission Batch/Trial relationships are same-branch
  validated.
- **Permissions:** Lead reads/writes use `lead.view`, `lead.create` and
  `lead.update`; booking uses `trial.book`; Admission operations use
  `admission.view`, `admission.create`, `admission.update` and
  `player.convert`. Menu visibility is not relied upon as authorization.
- **CSRF:** State-changing browser routes are POST routes covered by the
  application CSRF configuration; the Trial and Admission tests include CSRF
  assertions.
- **Audit/history:** Lead status history, Trial history, Admission status
  history and central audit records include actor, branch/entity context,
  before/after status values and reasons where required.
- **Transactions/idempotency:** Trial booking and transitions, Admission
  creation/status changes, and Player conversion use transactions and row
  locks. Duplicate future Trial booking, duplicate Admission intake, repeated
  conversion and conflicting Player lineage are handled safely.
- **Error/not-found behavior:** Detail services return safe `Not found.` or
  `Forbidden` outcomes after branch/permission checks. Browser controllers map
  those outcomes to safe HTTP responses or explicit originating-page redirects;
  the Admission redirect completion report confirms the three Admission
  exception targets.
- **Date/time:** Trial inputs use separate MCC-local date and time values,
  validate them with the configured application timezone, and persist normalized
  date/time fields. The reviewed Admission dates follow the existing MCC date
  conventions. No timezone suffixes or raw metadata are required in the UI.

## Confirmed defects and inconsistencies

No confirmed defect was found in the Trial or Admission state transitions,
branch checks, conversion lineage, or retry behavior.

The following evidence gap remains:

- `/crm/trials/{id}/admission-pending` is filtered at the route by `lead.update`,
  while `AdmissionService::createFromTrialPending()` additionally requires
  `admission.create`. This is a layered authorization requirement and should be
  covered explicitly in a future cross-flow test; it was not changed because
  relaxing either check could weaken authorization.
- CRM Lead create/update exception handlers still use `redirect()->back()` in
  legacy paths. This is outside the Admission redirect fix and may return to an
  unrelated page; it should be treated as a separate bounded defect only after
  focused tests confirm the impact.

## Missing references and unresolved policy

- The complete sequence is now covered by the insert-only evidence package and its
  26-test, 365-assertion verification result.
- Trial mode vocabulary, scoring authority, retrial policy, media linkage,
  Admission meeting policy, fees, hostel, ratings, cricket ability, signatures,
  consent, medical requirements, guardian visibility, notifications, retention
  and financial workflows remain `NEEDS_DECISION`.
- Existing completion reports are module-scoped rather than a single
  end-to-end report. Their evidence is listed above and was not treated as
  approval for deferred policy.

## Classification summary

| Classification | Items |
| --- | --- |
| Verified | Lead → Trial Booked → Trial Attended → Selected → Admission Pending → Admission → Approved → Player conversion; branch, retry, lineage, audit, rollback and explicit redirect behavior |
| Partially verified | None for the bounded sequence |
| Missing evidence | None for the bounded Lead → Trial → Admission → Player sequence; broader client-reference policy remains outside this package |
| Confirmed defect | Legacy CRM Lead create/update exception paths use `redirect()->back()`; separate from the verified Admission paths |
| NEEDS_DECISION | Trial mode/scoring/retrial/media; Admission meeting/fees/hostel/ratings/ability/consent/medical; guardian visibility, notifications, retention and financial workflows |
| Deployment blocker | Production migration approval, deployed PHP/vendor readability, private document/scanner/storage configuration and provider configuration evidence |

## Recommended next safe action

Preserve the verified insert-only evidence package. Any future extension of this
flow must first address the documented `NEEDS_DECISION` policy items and
deployment evidence requirements. Do not change routes, permissions, business
rules, migrations or production data.
