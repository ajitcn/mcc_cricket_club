# Client Reference Remaining Work Audit

**Audit date:** 2026-10-11  
**Match/Tournament decision review date:** 2026-10-11  
**Mode:** Documentation and bounded Admission verification audit  

`docs/SRS-original.md` was not modified. The bounded Admission verification
applied approved test-group migrations and focused application changes; no
production data, permissions, or unrelated migration history was changed.

## Admission complete-form verification reconciliation

The complete September 2026 PDF field set is rendered in both Admission create
and edit through the shared form partial. The test database is
`mcc_cricket_club_test`. Migration status before apply showed 72 history rows;
the apply command added exactly one row:
`20261011000300_AddCompleteAdmissionFormFields`, batch 57. The unrelated
same-version Match migration remained intact. The Admission migration is
forward-only, additive, idempotent, branch-neutral at schema level, and
non-destructive; existing Admission rows remained readable.

Final focused result: **65 tests, 732 assertions, 0 skipped, 0 failures, 0
errors, exit code 0**. Coverage includes Unit Admission contracts, Feature
routes, browser/database-safe tests, private document authorization, complete
form rendering, submitted-value and validation-error preservation, CSRF,
central permissions, server-side Branch/Batch/Trial/Coach authorization,
checklist/approval, conversion lineage, and idempotency. No separately named
visual-parity test file exists; the complete-form contract and route-rendering
tests are the available repository evidence.

PHP lint passed for the changed PHP files. JavaScript syntax check passed with
`node --check public/assets/js/app.js`; no JavaScript file was changed.

**Admission capture status: VERIFIED.**

### Admission plan-master removal audit

**Status: REMOVED FROM DEVELOPMENT/TEST.**

The former Coaching, Fee, Hostel, and Academic Plan masters were removed from
the development/test application. Existing Branch, Program, Batch, Trial,
Coach, and Lead/source data remains unchanged. Admission and renewal history
retains immutable display labels, with no replacement plan fields.

Formal approver, approval date, and effective date remain blank. The bounded
Admission verification result is **77 tests, 857 assertions, 0 failures, 0
errors**. Authorization verification separately passed with **12 tests, 191
assertions**. `docs/SRS-original.md` was not modified.

### New Admission UI presentation verification

**Status: VERIFIED** — the New Admission UI presentation slice is complete.

The form now has MCC card styling and a clear hierarchy for applicant,
guardian, address, academic/cricket, coaching, fees/installments, hostel,
documents/declarations, signatures/confirmation, and medical-note sections. It
uses a responsive two-column desktop layout and one-column mobile layout with
standardized labels, field heights, spacing, validation placement,
`mcc-form-actions`, aligned `btSelect` menus, and shared datepicker layering.
Dropdown overflow and z-index behavior prevent cropping by cards or outer
containers.

Field names, submitted values, errors, CSRF, routes, permissions, and workflow
behavior were preserved. PHP lint passed. JavaScript syntax passed with no
JavaScript changes. The existing Admission Unit/Feature UI contract suite
completed with **11 tests, 231 assertions, 0 skipped, 0 failures, and 0
errors**. `docs/SRS-original.md` is absent and was not modified.

Payment, hostel allocation, notifications, document approval, signature
verification, medical policy, ratings semantics, and portal visibility remain
capture-only/deferred. No production migration was applied.

## Match Player Performance completion reconciliation

The existing Match Player Performance evidence is separately counted and
passed:

- Unit: 13 tests, 179 assertions.
- Feature: 6 tests, 59 assertions.
- Database: 4 tests, 38 assertions.
- Total: **23 tests, 276 assertions, exit code 0**.
- No tests were skipped.
- PHP lint passed for the relevant service, controller, view, and test files.
- Database: `mcc_cricket_club_test`.
- Idempotent migration `20261011000400_AddMatchPlayerPerformanceVersion` was
  applied as batch 56.
- Completion report: `docs/MATCH-PLAYER-PERFORMANCE-CORRECTION-COMPLETION-REPORT.md`.

Batting, bowling, fielding, membership, branch validation, permissions, CSRF,
completed-Match restrictions, correction reasons, idempotent writes, audit
metadata, rollback, and bounded sensitive-data handling are verified. The
shared per-record optimistic-concurrency version is now also verified for the
bounded correction slice; stale writes are rejected safely with reload
messaging and successful corrections increment atomically.

## Match Player Performance correction decision reconciliation

No formal client approval has been supplied for the correction or version
policy. The selected defaults are recorded as user direction only; existing
implementation and test evidence do not constitute client approval. The
following items remain `NEEDS_DECISION`:

| Decision area | Status |
| --- | --- |
| Correction authority | `NEEDS_DECISION` |
| Required correction reason | `NEEDS_DECISION` |
| Optimistic concurrency requirement | `NEEDS_DECISION` |
| Shared or separate batting, bowling, and fielding versions | `NEEDS_DECISION` |
| Stale-form behavior | `NEEDS_DECISION` |
| Idempotency behavior | `NEEDS_DECISION` |
| Permissions and branch rules | `NEEDS_DECISION` |
| CSRF and transaction requirements | `NEEDS_DECISION` |
| Audit and rollback requirements | `NEEDS_DECISION` |

The selected decision, resolution, approver, approval date, effective date,
and approval comments remain blank. Every secure default and alternative in
the decision documents remains:

`USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL`

The formal policy record remains approval-gated, but the implemented bounded
correction/version slice is verified by the completion report and focused
evidence above.

No application behavior, routes, permissions, migrations, schema, tests,
database records, or SRS changes are authorized by this reconciliation.

## Selected Match Player Performance defaults

User instruction selected secure defaults for planning, but did not provide
formal client approval. The direction remains:

`USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL`

| Area | Recorded direction | Status |
| --- | --- | --- |
| Correction authority | Existing Match Player Performance update permission within the authorized branch. | `NEEDS_DECISION` |
| Correction reason | Required for every existing batting, bowling, and fielding record update. | `NEEDS_DECISION` |
| Optimistic concurrency | Submitted version must match the current record version. | `NEEDS_DECISION` |
| Version granularity | One shared version per Match Player Performance record. | `NEEDS_DECISION` |
| Stale form behavior | Reject safely, preserve the current record, and require reload. | `NEEDS_DECISION` |
| Idempotency | Repeated first-time submissions must not duplicate performance records. | `NEEDS_DECISION` |
| Permissions, branch, audit, and rollback | Preserve existing permissions and strict branch validation; require CSRF, transactions, before/after audit metadata, and rollback on failure. | `NEEDS_DECISION` |

Approver, approval date, and effective date remain blank. No implementation,
migration, route, permission, schema, test, or database change is authorized.

## Match/Tournament bounded implementation reconciliation

The recorded Match ↔ Tournament direction remains explicitly
`USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL`. A bounded implementation has
now been delivered and verified without treating that direction as client
approval.

| Item | Current classification | Evidence and boundary |
| --- | --- | --- |
| Standalone Match and optional Fixture link | Verified for bounded linkage slice | Standalone Matches remain supported; nullable one-to-one Fixture linkage is enforced by service validation and a unique index. Formal client approval is still absent. |
| Fixture scheduling and status | Verified for bounded linkage slice | Fixture owns scheduling/status; postponement, rescheduling, and cancellation reasons are audited. Reopening terminal records is not implemented. |
| Match result and completion | Verified for bounded linkage slice | Completed verified Match results are required for Fixture completion; ordinary completed-record reopening is rejected. Scorecards and innings remain excluded. |
| Tournament standings source | Verified for bounded linkage slice | Only completed Fixtures linked to completed Matches with verified results contribute; scheduled, postponed, cancelled, and incomplete records do not. Broader formulas remain unapproved. |
| Corrections and concurrency | Verified for bounded slice | Existing authorization, reason, expected version, atomic update, transaction, and before/after audit metadata are covered by focused tests. |
| Branch isolation and existing security | Verified for bounded slice | Same-branch Match/Fixture/Tournament/Ground/Opponent/Player checks, existing permissions, CSRF, transactions, and audit behavior remain covered. |
| Migration/deployment | Deployment blocker | `20261011000300_AddMatchTournamentLinkageVersion` is forward-only/idempotent and applied in the test database; production migration approval and deployment-owner evidence remain required. |

Bounded verification result: 55 tests, 523 assertions, exit code 0. The legacy
destructive Tournament integration suite remains excluded because its fixture
cleanup is incompatible with persistent append-only data protections.

## Evidence boundary

Findings use the current client-reference matrix, requirements, decisions,
architecture, database, and RBAC documents together with the completion reports
for the bounded slices. User-provided direction is not client approval unless
an approver, approval date, and effective date are recorded.

## Classification summary

| Classification | Current items |
| --- | --- |
| Completed and verified | Admission/Trial Summary; Admission form supported fields; Admission form visual parity; Admission redirect handling; CRM redirect handling; Lead/CRM → Trial → Admission evidence package; Scheduling and Individual Session Skill taxonomy; Director Dashboard bounded formulas; Documents metadata UI alignment; Documents browser presentation verification; Group Coaching UI alignment and verification |
| Completed and verified | Attendance → Coach feedback implementation, migration, insert-only integration fixtures, and 101-test/906-assertion verification |
| Completed and verified | Match Player Performance correction/version slice; broader competition policy remains separate |
| Confirmed defect | Participant replacement does not preserve participant-row history; no new Match/Tournament defect was confirmed in the bounded slice |
| Missing evidence | No safe consolidated end-to-end Match/Tournament/Performance result, no approved scorecard/innings evidence, and no safe execution evidence for the legacy destructive Tournament integration suite |
| NEEDS_DECISION | Trial scoring/mode/media; Admission meeting, fees, hostel, consent, medical, signature, document approval, notification, and portal policies; unresolved dashboard formulas; broader group/individual policy; reports/exports beyond implemented bounded slices; retention/archival; guardian visibility; financial and media workflows |
| Deployment blocker | PowerShell Composer/bootstrap readability evidence; private storage/scanner/encryption/backup/retention deployment-owner configuration; Match/Tournament production migration approval; provider configuration approvals |

## Completed and verified

| Capability | Evidence | Boundary that remains deferred |
| --- | --- | --- |
| Admission complete-form capture | `docs/ADMISSION-FORM-COMPLETION-REPORT.md`; 65 tests, 732 assertions, 0 skipped, 0 failures, 0 errors, exit code 0; test migration batch 57; PHP lint passed | Formal privacy/visibility, ratings, consent, medical, payment, hostel allocation, document approval, notification, and portal policy remain `NEEDS_DECISION` |
| Admission form visual parity | `docs/ADMISSION-FORM-VISUAL-PARITY-REPORT.md` plus complete-form contract/route evidence; all requested fields are rendered in create and edit through the shared partial; latest presentation verification is 11 tests and 231 assertions | Fixed-PDF versus responsive-web rendering and production browser/printer/device parity remain deployment-owner limitations |
| Admission/Trial Summary | `docs/ADMISSION-TRIAL-SUMMARY-COMPLETION-REPORT.md`; authorized projection, empty state, branch/permission checks, escaped output and no raw JSON | Payment, hostel, media, notifications, guardian visibility, scoring and meeting policy |
| Admission redirect handling | `docs/ADMISSION-REDIRECT-COMPLETION-REPORT.md`; 22 tests, 311 assertions, exit code 0; explicit create, checklist and conversion exception targets | No remaining redirect defect in the bounded Admission paths |
| CRM redirect handling | `docs/CRM-REDIRECT-COMPLETION-REPORT.md`; 2 tests, 14 assertions, exit code 0; explicit Lead create/edit/follow-up targets and existing Trial targets | No remaining redirect defect in the bounded CRM paths |
| Scheduling and Individual Session Skill taxonomy | `docs/INDIVIDUAL-SESSION-SKILL-COMPLETION-REPORT.md`; 44 tests, 551 assertions, 2 skipped, exit code 0 | Recurrence, capacity, media, payments, notifications, portal visibility, reporting and retention decisions |
| Director Dashboard bounded formulas | Completion evidence for FORM-01, FORM-02, FORM-03, FORM-04, FORM-06, FORM-07 and FORM-11; 4 tests, 80 assertions | FORM-05 Elite taxonomy, FORM-08 age groups, FORM-09 workload, FORM-10 availability/leave, FORM-12 attendance denominator, FORM-13 KPI |
| Group Coaching alignment and verification | `docs/GROUP-COACHING-UI-COMPLETION-REPORT.md`; create/edit alignment, shared `mcc-form-actions`, authorized Player selection, group-size summary, roster/attendance behavior; 43 tests, 614 assertions, exit code 0 | No remaining gap in this bounded UI slice; policy-sensitive group workflows remain deferred |
| Attendance → Coach feedback | Feedback version fields, optimistic concurrency, migration idempotency, insert-only integration fixtures, and final verification: 101 tests, 906 assertions, exit code 0 | Flow verified; append-only roster triggers, attendance behavior, branch isolation, permissions, CSRF, eligibility, transactions, and audit logging preserved |
| Documents metadata UI alignment and browser presentation | `docs/DOCUMENTS-METADATA-UI-COMPLETION-REPORT.md`; 12 tests, 97 assertions, exit code 0; list, detail, upload/versioning, preview, download, retired, expired, pending, verified, clean, failed and quarantined states reviewed | Policy extensions remain blocked |

## Implemented but missing completion evidence

| Area | Existing evidence | Exact gap | Safe evidence work |
| --- | --- | --- | --- |
| Cross-module reference flow | Lead/CRM → Trial → Admission → Approved → Player conversion evidence is recorded; Attendance → Coach feedback, bounded Match/Tournament linkage, and bounded Match Player Performance correction/version evidence are recorded | Broader competition policy remains bounded or unresolved | Documentation/decision audit only; no workflow expansion |

## Reconciled Match Performance and Tournament linkage

### Ownership decision status

The Match-Tournament decision request and implementation matrix were reviewed
on 2026-10-11. User-provided planning direction has now been recorded, but no
formal client approval, approver, approval date, or effective date has been
supplied. Secure defaults and user direction are not approval. The following
remain `NEEDS_DECISION` as formal client approval status; the bounded
implementation status is recorded separately below:

- Whether Matches may exist independently of Tournaments.
- Whether a Fixture owns the Match or a Match owns the Fixture.
- Whether a Fixture may link to only one Match.
- Whether a Match may link to more than one Tournament.
- Whether Match or Fixture status is authoritative for completion.
- Whether Match result/outcome or Fixture points is authoritative for result,
  winner, tie, no-result, and standings.
- How postponed or cancelled Fixtures affect linked Matches.
- Correction, reopening, optimistic concurrency, and standings recalculation.
- Same-branch ownership versus an explicitly approved cross-branch Tournament.

Required approval fields remain blank: decision/choice, approver name,
approval date, effective date, and approval comments. Because these fields are
missing, all seven decisions remain `NEEDS_DECISION`. No Match ↔ Tournament
ownership or source-of-truth change is approved by this audit.

### Recorded user-provided planning direction

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** The planning flow is that a
Tournament schedules Fixtures, Fixtures may lead to Matches, and verified
completed Match results feed Tournament standings. Standalone Matches are
allowed; a Fixture may link to one Match; and a Match may link to only one
Tournament Fixture.

The Fixture owns Tournament scheduling, round, date/time, Ground, and status.
The linked Match owns cricket result and completion details. Verified completed
Match results are planned as authoritative for winner, tie, no-result, and Match
completion. Points are planned to come only from completed, verified linked
Matches. Postponed Fixtures may be rescheduled with audit history; cancelled
Fixtures are retained and contribute no result or points. Corrections require
authorized permission, reason, optimistic version checking, before/after audit
metadata, and a transaction. All listed references remain within the authorized
branch with no implicit cross-branch links.

The direction remains `USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL`. The
bounded implementation is verified, but formal approval is still required
before treating these rules as approved client policy.

| Impact area | Current status |
| --- | --- |
| Migration and data repair | Verified for bounded slice; `20261011000300_AddMatchTournamentLinkageVersion` is forward-only/idempotent. Production migration approval remains a deployment blocker. |
| Permissions and CSRF | Verified for bounded slice; existing server-side permissions and CSRF are preserved, with no new permission. |
| Branch isolation | Verified for bounded slice; same-branch validation is enforced for bounded references. Cross-branch competition remains out of scope. |
| Audit and optimistic concurrency | Verified for bounded slice; correction reason, version checks, atomic transactions, and before/after audit metadata are covered. |
| Tests | Verified: 55 tests, 523 assertions, exit code 0; PHP lint passed. The legacy destructive Tournament integration suite remains excluded. |

| Linkage | Classification | Evidence and boundary |
| --- | --- | --- |
| Match -> Branch | Verified | Branch-scoped Match queries, authorization, transactions, audit, and the reported Match result of 21 tests and 243 assertions. |
| Match -> Ground/Opponent/Coach | Verified | Branch-owned references, active/reference validation, conflict checks, CSRF, and Match service evidence. |
| Match -> Player squad/Playing XI | Partially verified | Squad and Playing XI persistence, branch validation, and XI subset/cap rules are evidenced; registration, approval, locking, and correction policy are not approved. |
| Match -> Match Player Performance | Verified for bounded correction/version slice | Batting, bowling, fielding, membership, branch validation, permissions, CSRF, correction reason, shared per-record versioning, atomic increments, stale rejection, transactions, idempotency, audit, rollback, and sensitive-data handling are covered by 23 tests and 276 assertions. |
| Match -> Tournament Fixture | Verified for bounded linkage slice | One optional Fixture/Match link in each direction is enforced and tested; formal client approval remains absent. |
| Tournament -> Fixture | Verified | Fixture routes, statuses, branch checks, permissions, CSRF, transactions, audit history, and safe Tournament fixture evidence exist. |
| Fixture -> Ground/Player | Partially verified | Branch and active-state validation are evidenced; participant history and complete scheduling-boundary policy are not. |
| Fixture -> Standings | Verified for bounded linkage slice | Only completed Fixtures linked to completed, verified Matches feed standings; broader formulas, tiebreakers, exceptional outcomes, and formal source-of-truth approval remain open. |
| Fixture local time -> UTC/MCC timezone | Verified for bounded slice | Fixture conversion now consumes the configured MCC application timezone while preserving UTC persistence. |
| Match/Tournament schema deployment | Deployment blocker | The forward-only/idempotent test migration is applied and verified; production migration approval and deployment evidence remain required. |
| Match scorecard/innings -> Performance/standings | Missing evidence | No scorecard or innings workflow is evidenced; no automatic qualification or unapproved standings formula may be inferred. |

Preserved boundaries: no scorecards or innings, no automatic Tournament
qualification, no unapproved standings formulas, no registration or selection
policy changes, and no exports, notifications, or new permissions.

## Exactly one recommended next safe backlog item

**Complete a deployment-owner production-readiness review for the Admission
migration and private-data controls, without applying the migration to
production.**

The review should confirm target database identity, migration approval,
rollback/backup ownership, private document storage/scanner configuration, and
retention/visibility ownership. It must not apply production schema changes,
alter Admission policy, enable deferred workflows, add permissions, or change
the `NEEDS_DECISION` records.

## NEEDS_DECISION

Every item below remains `NEEDS_DECISION`; recommended defaults or existing
code are not approval.

| Item | Existing implementation evidence | Exact remaining gap and dependencies | Permission/branch impact | Migration impact | Required tests | Client direction required |
| --- | --- | --- | --- | --- | --- | --- |
| Trial mode, scoring and outcome | Trial booking/status/history and Admission summary exist | On-ground/video vocabulary, scoring authority, outcome gates and retrial rules are undefined; depends on Trial policy | Reuse Lead/Trial permissions and branch scope; no new grant | Unknown until policy is approved | Unit transition/scoring; Feature authorization; safe branch/audit tests | Yes |
| Trial media | Private Documents storage and scanner states exist | Trial linkage, reviewer, visibility, retention and quarantine policy are undefined; depends on storage deployment | Existing document permissions with explicit branch/relationship rules | Possible linkage migration | Upload/private-download/quarantine/audit tests | Yes |
| Admission meeting | Admission detail/checklist exists | Meeting fields, authority, visibility, correction and history are undefined | Admission permissions and branch scope require decision | New table/fields likely | Unit validation; Feature permission; insert-only audit tests | Yes |
| School/college | Applicant identity and supported Admission fields exist | Field type, visibility and Player conversion mapping are undefined | Admission/Player branch scope and PII visibility require decision | Field or mapping migration possible | Validation, branch, privacy and conversion tests | Yes |
| Permanent/correspondence addresses | Existing contact/address support is incomplete for this reference | Field model, privacy, Player mapping and retention are undefined | Branch and sensitive-data permissions require decision | New fields/table possible | Sensitive-field authorization and persistence tests | Yes |
| Previous coaching centre | Coach/Player reference data exists | Text versus catalog ownership and visibility are undefined | Admission/Player branch scope requires decision | Possible catalog/field migration | Validation, branch and history tests | Yes |
| Hobbies and achievements | No approved normalized source is evidenced | Text versus records, visibility and retention are undefined | PII visibility and branch scope require decision | New fields/tables possible | Input limits, escaping and authorization tests | Yes |
| Fees and payment installments | No approved payment workflow is evidenced | Amounts, schedules, receipts, refunds, reminders and provider are undefined | Finance roles, branch scope and provider authorization require decision | New financial schema likely | Transaction, reconciliation, authorization and audit tests | Yes |
| Hostel | No approved hostel workflow is evidenced | Requirement, allocation, consent, charges and visibility are undefined | Sensitive/finance permissions and branch scope require decision | New schema likely | Privacy, consent, finance and rollback tests | Yes |
| Ratings | Performance metrics exist but no Admission rating policy | Scale, authority, visibility and retention are undefined | Performance/admission permissions and branch scope require decision | Possible rating fields/table | Formula, authorization and audit tests | Yes |
| Cricket ability | Performance and skill data exist in other bounded modules, but no Admission ability policy is approved | Batting, bowling, wicketkeeping, fitness fields and rating authority are undefined | Performance/admission permissions and branch scope require decision | Possible fields/table | Formula, authorization and audit tests | Yes |
| Signatures and consent | CSRF and audit exist; no signature/consent record is approved | Capture method, authority, storage, visibility and retention are undefined | Sensitive-document and branch permissions require decision | New document/consent schema likely | Integrity, authorization and retention tests | Yes |
| Medical and certificate requirements | Document policy has medical permission gates | Required types, authority, visibility, privacy and retention are undefined | Explicit medical permission and branch scope required | Possible document policy migration | Private access, audit and generic-not-found tests | Yes |
| Dashboard FORM-05/08/09/10/12/13 | Bounded formulas FORM-01/02/03/04/06/07/11 are implemented | Elite taxonomy, age groups, workload, leave, attendance denominator and KPI sources are incomplete | Dashboard permission and authorized branch scope must remain | None until formula sources are approved | Formula unit, branch/date feature and safe DB tests | Yes |
| Group coaching policy | Existing group UI, roster snapshots and attendance are verified | Group entity, capacity, eligibility, roster lock, focus/drill/plan and automation are undefined | Scheduling/attendance permissions and branch scope require policy | Possible group/capacity migration | Eligibility, snapshot, concurrency and audit tests | Yes |
| Individual session policy extensions | Skill catalog and session integration are verified | Media, portal visibility, reporting, notifications and retention beyond taxonomy are undefined | Existing session/coach permissions; new visibility rules require decision | Possible media/linkage migration | Authorization, privacy and retention tests | Yes |
| Parent/Student visibility | Portal authentication and limited Achievement visibility exist | Relationship, consent, branch and sensitive-field rules for these reference flows are undefined | Portal relationship checks and branch isolation require decision | Possible visibility/consent schema | Relationship, branch and privacy tests | Yes |
| Reports and exports | Existing Reports and bounded Achievement export infrastructure exist | Approved sources, fields, filters, freshness, retention and authorization for remaining reports are undefined | Existing report permissions plus branch filtering; no broad export grant | None or report schema depending on scope | Filter parity, privacy, authorization and audit tests | Yes |
| Notifications | Notification foundation/routes exist | Channels, consent, templates, retry, recipients and retention are undefined | Notification permissions, branch and consent rules require decision | Provider/config/schema impact possible | Delivery, retry, consent and audit tests | Yes |
| Retention and archival | Soft-retire/history patterns exist in several modules | Periods, legal holds, restoration and deletion/retirement rules are undefined | Administrative permission and branch scope require decision | Archival/index migration possible | Retention, legal hold, restore and audit tests | Yes |
| Guardian and financial workflows | Parent portal relationship foundation and Admission data exist | Guardian visibility and financial record policy are undefined | Portal/finance permissions and branch scope require decision | Likely new schema | Privacy, authorization, transaction and audit tests | Yes |

## Deployment blockers

| Blocker | Existing evidence | Exact gap and dependencies | Permission/branch impact | Migration impact | Required tests | Client/deployment direction required |
| --- | --- | --- | --- | --- | --- | --- |
| PowerShell Composer/bootstrap readability | Managed runs have intermittently reported unreadable vendor files; normal PowerShell is authoritative | Runtime ACL/path/vendor consistency must be verified before migrations or PHPUnit | None; do not weaken bootstrap or authorization | None | `is_readable()` checks, autoload check, PHPUnit smoke test | Deployment owner: Yes |
| Private storage/scanner/encryption/backup/retention | Private storage abstraction and scanner states exist | Production paths, scanner service, key management, backups and retention settings lack deployment evidence | Existing document permissions and branch scope must remain | No app migration unless policy requires one | Private download, quarantine, backup/restore and audit tests | Deployment owner: Yes |
| Payment/notification providers | No approved provider configuration | Credentials, endpoints, retry and ownership are absent | Provider permissions/branch and consent rules unresolved | Possible provider/config/schema changes | Provider integration and failure tests | Client and deployment owner: Yes |
| Production migration/data operations | Forward-only migrations exist in repository | No production approval for applying migrations or data repair | Production RBAC and branch data must be protected | Production migrations blocked pending approval | Migration dry-run/status and rollback-safety review | Deployment owner: Yes |

## Security and data boundaries

All recommended work must preserve centralized RBAC, server-side branch
isolation, CSRF, service-layer transactions, append-only audit metadata,
private document storage, configured MCC timezone/date conventions, field-level
validation, submitted-value preservation, and no raw JSON or sensitive data in
views/logs. Medical, guardian, document, private-contact and portal data must
remain excluded unless a separate decision explicitly authorizes it.

## Confirmed defects

No unresolved Admission or CRM redirect defect remains in the reviewed paths.
Admission create failures use `/admissions/new`; checklist and conversion
failures use `/admissions/{id}/page`. CRM Lead create failures use
`/crm/leads/new`; Lead edit failures use `/crm/leads/{id}/edit`; Lead and
follow-up failures use `/crm/leads/{id}`. Trial failure paths remain explicit.
Submitted input, flash errors and API behavior are preserved. The Admission
completion report records 22 tests and 311 assertions; the CRM completion report
records 2 tests, 14 assertions and exit code 0. No CRM `redirect()->back()` paths
remain.

## Items that must not be implemented without approval

Do not implement payment, hostel, trial scoring/media, consent, medical or
certificate requirements, guardian or Parent/Student visibility changes,
notifications, retention/archival, unresolved dashboard formulas, group
eligibility/capacity, new financial workflows, or new Admission fields without
explicit recorded decisions.

## Admission plan-master removal verification

The former Coaching, Fee, Hostel, and Academic Plan masters are removed from
the development/test application. Existing Admission and renewal labels remain
readable, and existing Branch, Program, Batch, Trial, Coach, and Lead/source
data remains unchanged.

The new removal migration runs only in `mcc_cricket_club_test`, is
forward-only and idempotent, and leaves existing Admission data readable. No
production or unapproved environment was changed, and

## Final development verification reconciliation

Final verification used mcc_cricket_club_test, where migration history is
**74 applied migrations** and migration 20261011000500_CreateAdmissionMasterData
is at **batch 58**. Authorization verification passed with **12 tests and 191
assertions**. The complete Admission suite passed with **77 tests and 857
assertions**, with zero failures and zero errors. PHP lint passed.

Historical labels remain readable after deactivation, and branch isolation was
verified. No production environment was accessed, and no data was deleted or
truncated. Deployment remains **NOT APPROVED — TEST VERIFIED ONLY**.
`docs/SRS-original.md` was not modified.

## 2026-10-21 application-wide read-only audit addendum

The application-wide audit was performed without changing source code,
migrations, permissions, routes, tests, documentation, or database records.
PHP lint passed for 648 PHP files and JavaScript syntax passed for the one
application JavaScript file. The read-only migration status command reported no
pending migration for the configured test runner, but the migration inventory
contains duplicate prefix `20261011000300` files and the runner reported 100
available versus 103 applied history rows. This is a deployment blocker and
requires deployment-owner reconciliation before production migration work.

The full Unit suite currently reports 303 tests and 5,943 assertions with one
error and seven failures. The failures include stale or broken contract
assertions in Opponent Management, Admission complete/PDF/plan/connectivity,
Admission Test fee-section order, Player Development, and Session Management.
The focused Admission Test/Admission/Documents/Session contract subset reports
51 tests and 1,277 assertions with three failures. Feature and database suites
were not run because their fixture cleanup is outside the permitted
non-destructive audit scope.

New audit findings are recorded in `docs/APPLICATION-AUDIT-REPORT.md`:

- RBAC mutation and audit writes are not transactionally coupled and lack
  explicit target/branch validation.
- Several views interpolate dynamic values without consistent HTML or
  JavaScript-context escaping.
- Admission Test fee synchronization can retain stale schedule rows when a full
  payment plan is submitted with a non-empty removal list.
- Document uploads remain quarantined because the scanner is not configured;
  this is secure behavior but blocks verified document workflows.
- Route-level permission filters are inconsistent where services provide the
  second authorization boundary; no direct bypass was confirmed in the
  inspected service paths.

Existing client-approval boundaries remain unchanged. Unresolved policy areas
including document scanning/retention, payment and hostel policy, portal
visibility, ratings/scoring, notifications, exports, retention, Tournament
rules, and idempotency policy remain `NEEDS_DECISION` or deployment-owner work.
No production migration, data cleanup, permission change, or business-rule
change was performed.

## Portal policy approval update — 2026-11-10

The seven Student/Parent Portal policy areas are approved for development and
production under `docs/PORTAL-POLICY-APPROVAL.md`:

- Fees and payments
- Document visibility
- Medical-data visibility
- Messaging permissions
- Consent and signatures
- Profile/contact editing
- Hostel and transport information

Approver: **Ajit**, Manager. Approval and effective date: **2026-11-10**.
Implementation remains subject to server-side authorization, branch isolation,
private storage, audit, CSRF, validation, transaction, and test controls.
