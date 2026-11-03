# MCC Cricket Club Management System — Implementation Requirements

Status: CRM lead, approved Admissions lifecycle/conversion, and approved Player identity/profile slices implemented; remaining Phase 1 capabilities remain gated by their referenced decisions.
Source: `SRS-original.md`, reconciled with `DECISIONS.md`, `REQUIREMENTS-REVIEW.md`, and repository evidence.

Navigation scope note: Assignments are provided by the existing Player and Coach assignment workflows, and Fixtures are provided by the existing Tournament/Match workflows. Neither is a separate navigation module. Other deferred entries remain Coming Soon because their requirements, authority, formulas, or workflow policies are not sufficiently defined for safe implementation.

`APPROVED` means explicitly present in the repository decision register or current project instruction. `IMPLEMENTED` is used only where code and tests verify the requirement. Where evidence is absent, the status is `NOT_IMPLEMENTED`, `PARTIALLY_IMPLEMENTED`, or `NEEDS_DECISION` as applicable.

## Authoritative Admissions and CRM workflow

The Lead and Admission capabilities form one pipeline; Admission is the final conversion stage, not an unrelated standalone workflow. Admissions also support a first-class Direct Admission path without a Lead; Lead linkage is optional and existing Lead-linked lineage is preserved:

`Lead → Trial Booked → Trial Attended → Selected → Admission Pending → Admission Completed → Active Player`

Lead requirements include Lead Source, Campaign, Counselor, follow-up history, next follow-up date, remarks, admission probability, status, trial information, and conversion information. The Follow-Up contract is approved under `LD-13`. Current repository implementation is limited to the verified Lead subset recorded under `REQ-CRM-001`; Campaign, Counselor, remarks, probability, trial workflow, and conversion presentation remain planned or separately gated where implementation evidence is absent. Campaigns has no implemented module or navigation item.

Lead Sources and Lead Statuses are admin-managed configuration records. They are not separate CRM modules. The authoritative source vocabulary is Google, Meta Ads, Instagram, Website, Walk-In, Referral, School, WhatsApp, and Other; active/inactive configuration and current implementation evidence remain authoritative for what can be selected in the application.

## Trial Management decision gate

Trial Management is the next stage of the Lead/Admission pipeline:

`Lead → Trial Booked → Trial Attended → Selected → Admission Pending`

Trial Management is approved for implementation under `TRIAL-01`. It uses existing Lead/CRM authorization and introduces no new permission. Implementation must preserve independent Trial history, branch scope, manual attendance, approved transitions, audit events, and admin-managed Lead Status interaction.

User-provided implementation direction dated 2026-09-21 adds a centralized `trial.book` permission for booking routes and form visibility, with branch authorization retained in the service. Existing roles with `lead.update` receive the booking permission to preserve current booking access. This conflicts with TRIAL-01's approved no-new-permission clause and is recorded as scoped user direction, not client approval; client reconciliation remains `NEEDS_DECISION` (see `TRIAL-BOOK-01` and `docs/RBAC.md`).

User-provided implementation direction dated 2026-09-22 removes the Lead-status restriction from first-Trial booking: an eligible Lead in any current status may be booked when no future booked Trial exists. This changes eligibility only; `trial.book` permission checks, branch authorization, validation, future-booking protection, and booking behavior remain unchanged. This is not client approval. Because TRIAL-01 does not explicitly define first-Trial eligibility for every Lead status, client reconciliation of this status interaction remains `NEEDS_DECISION` (see `TRIAL-ELIG-01`).

User-provided implementation direction dated 2026-09-22 makes Trial location optional and renders it as a dropdown sourced from active Grounds records in the authorized Lead branch. No location options are invented; an empty option is allowed. This conflicts with TRIAL-01's approved contract, which lists location as required, and the optionality and Grounds relationship remain `NEEDS_DECISION` for client reconciliation (see `TRIAL-LOC-01`).

The decision contract must approve the Trial fields and required/optional rules for Lead, Branch, trial date/time, location/ground, coach/evaluator, booking status, attendance status, selection outcome, remarks, cancellation reason, and reschedule history. It must also approve Trial cardinality and re-trial rules, the Trial/session relationship, branch scope, authoritative attendance source, transition matrix, audit events, and the interaction between Trial state and Lead Status.

Trial implementation must not repurpose Sessions or link Trial attendance to the Attendance module in this phase. Existing Lead, Session, Admission, Attendance, branch, and test data must remain unchanged.

## Authoritative navigation architecture

The sidebar follows this product structure. Only entries with implemented routes and sufficient authorization are currently rendered; planned and future entries are documentation architecture, not permission or route commitments.

```text
Dashboard

Admissions & CRM
  ├─ Leads [implemented]
  ├─ Trials [planned]
  ├─ Admissions [partially implemented]
  ├─ Follow-ups [approved under LD-13]
  ├─ Campaigns [planned]
  └─ Marketing Dashboard [implemented with limitations]

Players
  ├─ Players [partially implemented]
  ├─ Player 360° [planned]
  ├─ Development [future scope]
  ├─ Performance [future scope]
  ├─ Fitness [future scope]
  ├─ Documents [foundation only / blocked]
  └─ Achievements [future scope]

Coaching
  ├─ Coaches [planned]
  ├─ Assignments [partially implemented dependency]
  ├─ Sessions [implemented]
  └─ Coach KPIs [future scope]

Attendance
  ├─ Attendance [partially implemented]
  ├─ Reports [planned / blocked]
  └─ Alerts [future scope]

Matches & Tournaments [future scope]
Grounds & Equipment
  ├─ Grounds [implemented under existing scope]
  └─ Equipment inventory [limited user-authorized scope; create/list/edit/soft delete implemented; not client approval]
High Performance [future scope]
Reports [blocked]
Communication [future scope]

Administration
  ├─ Branches [implemented]
  ├─ Users [implemented]
  ├─ Roles [implemented]
  ├─ Permissions [implemented]
  ├─ Lead Sources [implemented configuration]
  ├─ Lead Statuses [implemented configuration]
├─ Audit Logs [implemented boundary / OPS-01 approved; evidence dependent]
  └─ Settings [planned]
```

The Status column is the implementation/readiness status. Requirement owner, approver, and approval date are not independently recorded in the repository; where known, approval authority is traceable through the dependency decisions in `DECISIONS.md`. Evidence not found is not treated as approval.

Approval update (2026-08-28): `GOV-01`, `GOV-02`, `SEC-12`, `SEC-13`, and `SEC-14` are now `APPROVED` in `DECISIONS.md` using the approved baselines recorded there. This removes those decisions as unresolved dependencies but does not change any requirement's implementation status; remaining unresolved decisions and missing implementation evidence continue to govern readiness.

Approval update (2026-08-28): `DAT-01`, `REL-01`, and `REL-02` are now `APPROVED` in `DECISIONS.md` using the Phase 1 recommended baselines. This removes those decisions as unresolved blockers for CRM and player/guardian planning; implementation status remains governed by repository evidence and acceptance tests.

Approval update (2026-08-29): `AT-01`–`AT-06` are approved using the documented Attendance baselines. The Attendance source-event slice is implemented; AT-01 denominator behavior and AT-06 thresholds, formulas, reporting, alerts, and recipients remain explicitly deferred.

Attendance implementation evidence (2026-08-29): `AttendanceService`, persistence models, migration, protected MVC routes, and database integration tests verify the approved source-event scope. Recording uses only the persisted session roster, approved status vocabulary, server-side branch authorization, UTC storage with `Asia/Kolkata` presentation, cancellation protection, correction reasons, append-only history, optimistic versions, and duplicate prevention. Denominator calculations and all AT-06 derived behavior remain deferred.

Attendance completion audit (2026-09-01): the canonical session/roster page now supports server-side date, branch, batch, session, player, session-state, and approved attendance-state filters, preserves filter values, shows roster recording counts, and exposes a staff-facing attendance detail/history page. No denominator, percentage, KPI, ranking, report, alert, or notification behavior was added.

Attendance safe verification update (2026-09-01): tests/database/AttendanceSafeWorkflowTest.php passes 5 tests / 51 assertions using only persistent test-database fixtures and no cleanup. Coverage includes authorized access, permissions, branch and roster boundaries, approved statuses, cancellation protection, correction reason/version/history, audit metadata, list/detail behavior, CSRF, and the absence of a physical deletion route.

## Foundation and security

### Programs and Batches (limited user-provided scope; not client approval)

The user-provided direction dated 2026-09-22 authorizes only create/list screens. Programs contain `name` only. Batches contain `name`, an existing Program relationship, and a Branch relationship. Super Admin selects an authorized Branch; Branch Admin uses the server-validated active Branch and submitted Branch values are ignored. `program.view`, `program.create`, `batch.view`, and `batch.create` are centralized permissions granted only to the existing Super Admin and Branch Admin roles.

The migration preserves existing Batch rows by adding nullable `program_id`; new Batch creation requires a Program. Existing-row backfill, Program branch ownership/sharing, lifecycle/status, capacity, edit/delete, dependent-record behavior, coach assignment, scheduling, recurrence, attendance, fees, and all other fields/workflows remain `NEEDS_DECISION`. This is user direction, not client approval, and does not modify `docs/SRS-original.md`.

### Equipment inventory (limited user-provided scope; not client approval)

`REQ-EQUIPMENT-001` records the user-provided implementation direction dated 2026-09-21. Equipment inventory records have Name, Quantity, and Branch. The implemented workflow creates, lists, edits, and soft-deletes records. Edit may change Name and Quantity only; the record's Branch remains fixed. Super Admin means the existing `Super Admin` role and selects an active assigned branch. Whether a Super Admin may create or manage records for an inactive but assigned branch remains `NEEDS_DECISION`. “Branch Manager” means the existing `Branch Admin` role: one authorized active branch is selected automatically; for multiple authorized active branches, the user selects the active branch through shared navigation and the Equipment form presents it as fixed; zero authorized active branches prevents create, update, or delete. Active branch selections are revalidated against current database branch assignments on every Equipment operation. Branch Admin operations derive Branch server-side and ignore submitted `branch_id`.

`equipment.view`, `equipment.create`, `equipment.update`, and `equipment.delete` are introduced for the two mapped roles, with update and delete enforced separately. Delete sets `deleted_at`, retains the record and audit event, and removes it from the active list; hard delete and a user-facing restore workflow remain `NEEDS_DECISION`. A user assigned both roles is denied Equipment access pending a precedence decision. This is scoped user authorization, not client approval of the complete RBAC matrix or replacement requirements baseline. Categories, assignments, maintenance, availability, reporting, and Grounds or scheduling relationships remain `NEEDS_DECISION` and are not implemented. Quantity positivity, zero/fractional values, and other quantity bounds also remain `NEEDS_DECISION`; validation limits are provisional implementation constraints. `docs/SRS-original.md` remains unavailable and unmodified; `docs/SRS.md` is not treated as approved.

| ID | Requirement | Source/status | Implementation evidence |
|---|---|---|---|
| REQ-EQUIPMENT-001 | Create, list, edit, and soft-delete branch-owned inventory records with Name, Quantity, and Branch; edits cannot reassign Branch; enforce the scoped Super Admin/Branch Admin branch behavior above. Whether Quantity must be positive, may be zero or fractional, and its other bounds remain `NEEDS_DECISION`. Restore workflow remains `NEEDS_DECISION`. | User-provided direction, 2026-09-21; implemented for this limited scope; not client approval. Quantity validation and schema limits are provisional implementation constraints only. | `app/Modules/Equipment`, `app/Services/EquipmentService.php`, `app/Services/BranchContextService.php`, migrations `20260921000100_CreateEquipmentInventory.php` and `20260921000200_AddEquipmentUpdateDelete.php`. | 

| ID | Title | Priority | Status | Dependencies |
|---|---|---:|---|---|
| REQ-AUTH-001 | Database-backed identity, login, logout, recovery, disablement, secure adaptive password hashing, session rotation/invalidation | MUST | PARTIALLY_IMPLEMENTED | SEC-01, SEC-14, INT-01 |
| REQ-RBAC-001 | Central explicit RBAC; multi-role users; deny-by-default authorization on every protected endpoint | MUST | PARTIALLY_IMPLEMENTED | SEC-02, SEC-04 |
| REQ-BRANCH-001 | Server-side branch isolation with explicit authorized branch assignments and cross-branch denial tests | MUST | PARTIALLY_IMPLEMENTED | SEC-03, BR-01–BR-10 |
| REQ-AUDIT-001 | Append-oriented audit of auth, lifecycle, RBAC, documents, sensitive changes, exports, and administration | MUST | PARTIALLY_IMPLEMENTED | OPS-01 approved; implementation and deployment evidence remain required |
| REQ-DOC-001 | Private document/media abstraction with controlled request-time authorization, validation, malware scanning, metadata, verification, expiry, replacement history, and retirement | MUST | NOT_IMPLEMENTED | SEC-06–SEC-08, SEC-12, OPS-03 |
| REQ-OPS-001 | Encrypted backups, restore testing, monitoring, error logging, incident response, deployment checklist, and recovery objectives | MUST | NOT_IMPLEMENTED | OPS-02, OPS-11, OPS-12 |

Acceptance criteria for all foundation requirements: unauthorized users receive no protected data and cannot infer protected record existence; invalid, missing, inconsistent, and out-of-scope data is rejected; critical actions expose actor/action/entity/timestamp/branch; secrets and sensitive content are absent from logs; failures are safe and retries are idempotent; conflicting updates cannot silently overwrite data; core workflows are keyboard accessible, visibly focused, semantically labelled, sufficiently contrasted, and responsive.

## Password-recovery HTTP contract

This is the single authoritative HTTP contract for the approved recovery flow under `REQ-AUTH-001`, `SEC-14`, and `INT-01`. It defines the interface only; implementation and deployment evidence remain separate status questions.

### Request recovery

**Endpoint:** `POST /auth/recovery/request`

**Authentication:** Unauthenticated by design. The request accepts an email/account identifier without requiring an authenticated session.

**Content type:** `application/json` with a JSON object body. Other content types, including form encoding, are not part of this endpoint contract.

**Fields:**

- `email` — required string; trim surrounding whitespace and normalize case for lookup; maximum 254 bytes; must pass server-side email validation.
- No additional fields are accepted.

The request body is limited to the existing authentication JSON bound of 16,384 bytes. Empty, missing, non-string, invalid-format, oversized, non-object, or unexpected-field input is rejected. Malformed JSON is rejected and is never treated as an empty or successful request.

**Valid-request response:** HTTP `202 Accepted` with the same body for an existing account, nonexistent account, disabled account, and delivery failure:

```json
{
  "status": true,
  "data": {
    "message": "If the account is eligible, recovery instructions will be sent."
  }
}
```

The response must not contain an account identifier, email address, account state, delivery result, reset token, or SMTP detail. A syntactically valid request must remain enumeration-resistant even when delivery fails; provider failures are recorded operationally and do not change this public response.

**Validation-error response:** HTTP `422 Unprocessable Entity` with the existing safe error shape:

```json
{
  "status": false,
  "message": "The recovery request is invalid."
}
```

Validation responses must not echo submitted email values or other request data. Recovery rate limiting is defined by approved decision `SEC-14`: account scope permits at most 5 requests per rolling 15-minute window and IP scope permits at most 10 requests per rolling 15-minute window. Both limits preserve the generic recovery response; the endpoint does not expose which limit was reached.

### Submit password reset

**Endpoint:** `POST /auth/recovery/reset`

**Authentication:** Token-authorized, not session-authorized. The normal authenticated-user authorization requirement must not be added to this endpoint; the token is the recovery authority and all normal CSRF protections still apply.

**Content type:** `application/json` with a JSON object body.

**Fields:**

- `token` — required string containing the opaque 64-character lowercase hexadecimal representation of the 32-byte recovery secret. The token is accepted only for validation and is never persisted or returned.
- `password` — required string; minimum 12 bytes and maximum 1,024 bytes; stored only as the approved adaptive password hash after successful validation. Password policy errors must not echo the password.
- No additional fields are accepted.

Malformed JSON, non-object bodies, missing/non-string fields, empty values, invalid token format, oversized input, and unexpected fields return HTTP `422` with the safe validation-error shape above. Invalid, expired, already-used, or otherwise unrecognized tokens return HTTP `400` with:

```json
{
  "status": false,
  "message": "The recovery token is invalid or expired."
}
```

This response must not disclose whether a token was malformed, expired, consumed, associated with an account, or associated with an enabled account. A successful reset returns HTTP `200`:

```json
{
  "status": true,
  "data": {
    "reset": true
  }
}
```

No password, token, account profile, session identifier, or SMTP information is returned.

### Authorization and CSRF

- `/auth/recovery/request` is intentionally unauthenticated; account existence is protected by the generic response contract.
- `/auth/recovery/reset` is authorized by a valid, unexpired, single-use recovery token, not by an existing authenticated session.
- Neither endpoint may require the normal `auth` or business-permission filter.
- The current application applies the `csrf` filter globally before requests and uses the configured cookie token plus `X-CSRF-TOKEN` header. Both recovery POST endpoints remain inside that global CSRF protection. A missing or invalid CSRF token is rejected by the existing CSRF filter; recovery implementation must not bypass, disable, or weaken it.

### Delivery and state semantics

- A valid recovery request uses the existing secure database reset-token lifecycle: cryptographically secure generation, hash-only persistence, expiry, single use, and post-reset invalidation.
- The plaintext token is delivered only through the approved self-managed SMTP/relay operated by MCC infrastructure; it is never returned in HTTP responses.
- SMTP credentials remain deployment-managed environment/secret configuration. Hosts, ports, domains, TLS modes, and retry values are not part of this contract.
- Delivery failure must not reveal account existence. The authoritative token/recovery state must not be reported as successfully delivered when delivery did not complete; retries must be bounded and idempotent according to the approved operational policy without creating unsafe token reuse.
- Recovery request, delivery attempt, delivery failure, reset completion, and invalid/expired/reused-token events are auditable without passwords, password hashes, tokens, SMTP credentials, authorization headers, session IDs, or sensitive recovery data.
- Successful reset invalidates applicable existing sessions through the existing session-version mechanism.

### Security acceptance criteria

Implementation is acceptable only when tests demonstrate:

- Existing, nonexistent, and disabled accounts receive the identical valid-request response and cannot be enumerated.
- Malformed JSON, invalid email, missing/empty fields, oversized bodies/fields, and unexpected fields fail safely without echoing secrets or submitted sensitive values.
- Tokens are generated securely, have the documented format, are stored only as hashes, expire, are single-use, and cannot be reused.
- Successful reset changes the adaptive password hash and invalidates existing authenticated sessions.
- Both endpoints remain protected by the actual global CSRF/request-protection path, while neither requires normal authenticated-user authorization.
- SMTP success and failure are handled through the application mail boundary; valid-request responses remain generic and SMTP diagnostics contain no credentials, tokens, passwords, session secrets, or sensitive recovery data.
- Retry/failure handling is bounded and idempotent without duplicate unsafe recovery side effects.
- Required recovery and audit events persist with actor/action/timestamp/entity context and no secrets.

**Implementation evidence:** The request/reset routes, validation, database token lifecycle, session invalidation, audit events, CSRF path, injectable SMTP boundary, and approved database-backed recovery rate limiting are implemented. Controlled tests use a mail double; real SMTP delivery and production deployment evidence remain unverified.

**Deployment configuration:** The SMTP adapter reads `email.protocol`, `email.SMTPHost`, `email.SMTPAuthMethod`, `email.SMTPUser`, `email.SMTPPass`, `email.SMTPPort`, `email.SMTPTimeout`, `email.SMTPKeepAlive`, `email.SMTPCrypto`, `email.fromEmail`, and `email.fromName` from deployment environment/secret configuration. No values belong in source control or `.env.example`.

**Remaining dependencies:** `INT-01` and the exact recovery rate-limit policy under `SEC-14` are approved; real SMTP deployment evidence remains incomplete. The approved contract does not resolve unrelated `NOT-01`–`NOT-03` or portal visibility/relationship decisions for `REQ-PORTAL-001`.

Attendance deployment-readiness evidence (2026-08-29): migration `202608290006_CreateAttendanceTables.php` was applied only to `mcc_cricket_club_test`. Schema inspection confirmed required Attendance columns, foreign keys, indexes, and unique `(session_id, player_id)` enforcement. No production database was accessed. The local front controller returned HTTP 200; this is local evidence, not production evidence.

## Non-functional requirements

| ID | Title | Priority | Status | Test implication |
|---|---|---:|---|---|
| NFR-01 | Standard approved reads/writes ≤3 seconds server response at p95 under agreed production-like data and concurrent load | MUST | NOT_IMPLEMENTED | Load test records dataset, concurrency, environment, and p95 result |
| NFR-02 | HTTPS, adaptive password hashing, session rotation at login, invalidation on logout/reset/disablement | MUST | PARTIALLY_IMPLEMENTED | MySQL authentication lifecycle tests verify hashing, regeneration hook, and invalidation behavior; exact native session-ID evidence and deployment HTTPS remain outstanding |
| NFR-03 | Authorization on every protected endpoint and document download | MUST | NOT_IMPLEMENTED | Positive/negative endpoint and download tests |
| NFR-04 | Production logs exclude passwords, tokens, full medical notes, identity-document numbers, and signed URLs | MUST | PARTIALLY_IMPLEMENTED | Application test log scan produced zero sensitive-pattern matches and production DB debugging is disabled; deployment-level log review remains outstanding |
| NFR-05 | Encrypted backups and quarterly restore tests with approved retention and recovery objectives | MUST | NOT_IMPLEMENTED | Restore evidence and schedule review |
| NFR-06 | Production changes require migration guidance, automated test results, and owner-approved release checklist | MUST | NOT_IMPLEMENTED | Release checklist audit |
| NFR-07 | MCC timezone `Asia/Kolkata`; database/audit timestamps stored in UTC; scheduling/UI presentation converted to MCC timezone; explicit timezone required for datetime requests | MUST | IMPLEMENTED for Scheduling | Timezone boundary, UTC persistence, presentation, ambiguity rejection, historical stability, and UTC audit tests |
| NFR-08 | Every export is authorized, logged, timestamped, filtered, and field-restricted by role | MUST | NOT_IMPLEMENTED | Export authorization, audit, scope, and field tests |

## Phase 1 operational requirements

| ID | Title | Priority | Status | Dependencies |
|---|---|---:|---|---|
| REQ-CRM-001 | Lead capture, immutable identity/source/owner/branch/status, follow-up and trial history, lost reason, audited reassignment, duplicate warning without auto-merge | MUST | PARTIALLY_IMPLEMENTED | LD-01–LD-08, LD-13, DAT-01 |
| REQ-TRIAL-001 | Trial booking, attendance, selection, scheduling/history, and transition to Admission Pending under the approved Trial contract | MUST | IMPLEMENTED | TRIAL-01, REQ-CRM-001, REQ-ADMISSION-001, REQ-SESSION-001 |
| REQ-ADMISSION-001 | Approved seven-status admission lifecycle, Phase 1 checklist, controlled conversion, idempotent player creation, and Lead-linked or Direct Admission intake with preserved admission/optional lead lineage | MUST | PARTIALLY_IMPLEMENTED | PL-01–PL-06, AD-01–AD-03, LD-09–LD-11, DAT-01 |
| REQ-PLAYER-001 | Player identity/profile, branch history, batch/coach assignment, guardian/contact relationships, status history, and authorized timeline | MUST | PARTIALLY_IMPLEMENTED | BR-02, BR-04, BR-06, PA-01, PA-02, REL-01–REL-05, DAT-01 |
| REQ-SESSION-001 | One-time session scheduling, roster eligibility, resource conflict prevention, cancellation reason/audit, and session ownership | MUST | APPROVED_FOR_IMPLEMENTATION | SCH-01–SCH-05, BR-04 |
| REQ-ATT-001 | Player ↔ session attendance; cancelled session exclusion; eligible roster only; approved status/correction/timing rules; explicit denominator and timezone | MUST | NEEDS_DECISION | AT-01–AT-06, REQ-SESSION-001, OPS-09 |
| REQ-DOC-002 | Document verification state, expiry tracking, controlled download, private storage, and audit history | MUST | NEEDS_DECISION | REQ-DOC-001, SEC-06–SEC-13 |
| REQ-REPORT-001 | Authorized, timestamped, filtered, branch-scoped CSV/Excel/PDF reports with approved columns and field policy | MUST | NEEDS_DECISION | REP-01–REP-03, SEC-11, NFR-08 |
| REQ-DASH-001 | Branch-scoped operational dashboard using only approved metric definitions and authoritative source data | MUST | NEEDS_DECISION | DASH-01–DASH-03 |

Phase 1 acceptance: all create/update/download/export actions are server-authorized and validated; branch-crossing requests are denied unless explicitly authorized; conversions and retries are idempotent; cancellation cannot create player attendance; cancelled sessions are excluded from denominators; reports and dashboards expose formula/version/scope metadata or remain unavailable; uploads fail safely without storing unscanned or executable content.

### Admissions reopening amendment (AD-01)

**Status:** User-provided direction implemented for the scoped change; **not client approval**. Client reconciliation remains `NEEDS_DECISION`.

The existing AD-01 lifecycle is amended only for rejected admissions: `REJECTED` may reopen to `IN_REVIEW` and may not transition to any other status. A non-empty reopening reason is required. The existing checklist is preserved, and reopening requires `admission.update` plus authorized access to the admission's branch. History and audit records include the actor, timestamp, previous status, new status, and reopening reason. Once reopened, the admission follows the existing lifecycle and conversion rules. No other transition, permission, branch rule, or status definition is changed.

**Direction recorded on:** 2026-09-22

**Source:** `AD-01` user-provided amendment recorded in `docs/DECISIONS.md`; not a client-approved replacement baseline.

## Phase 2/3 requirements

| ID | Title | Priority | Status |
|---|---|---:|---|
| REQ-PERF-001 | Raw performance capture and approved derived cricket calculations | SHOULD | NEEDS_DECISION |
| REQ-FIT-001 | Fitness measurements, units, reports, and approved risk rules | SHOULD | NEEDS_DECISION |
| REQ-PORTAL-001 | Parent/player portals with verified relationships, consent, eligibility, category visibility, revocation, and audit | SHOULD | NEEDS_DECISION |
| REQ-ADV-001 | Tournaments, advanced scorecards, media galleries/video, HPP, PDF reports, bulk imports, advanced notifications, marketing ROI, QR, and AI analytics | LATER | NEEDS_DECISION |

## Calculation rule

No performance score, coach KPI, fitness risk, improvement score, marketing ROI, ranking, alert threshold, or dashboard KPI may be implemented as authoritative without formula, source fields, units, precision/rounding, timezone, missing-data behavior, denominator, examples, owner, approval date, and version.

## Attendance readiness package (approved source-event scope; calculations deferred)

AT-01–AT-06 are approved for implementation using the documented baselines. AT-01 denominator behavior and AT-06 calculations, reporting, alerts, and thresholds are explicitly deferred.

AT-01–AT-06 remain `NEEDS_DECISION`. The intended traceability is:

| Decision | Requirement | Module | Acceptance criteria | Test status |
|---|---|---|---|---|
| AT-01 | REQ-ATT-001 | Attendance event grain, eligibility, denominator | One-record-per-player/session uniqueness, roster/branch eligibility, cancelled exclusion, denominator rule | Stops pending approval |
| AT-02 | REQ-ATT-001 | Status vocabulary and leave/absence treatment | Approved statuses, invalid-status rejection, privacy boundary, audited status changes | Stops pending approval |
| AT-03 | REQ-ATT-001, OPS-09 | Recording authority, timing, timezone | Permission/branch checks, timing window/cutoff, UTC storage and MCC presentation | Stops pending approval |
| AT-04 | REQ-ATT-001, REQ-SESSION-001 | Cancellation and roster boundary | No cancelled-session attendance, reschedule independence, approved roster treatment | Stops pending approval |
| AT-05 | REQ-ATT-001, OPS-01 | Corrections and history | Correction authority/reason, append-only prior values, stale-write rejection | Source-event scope approved; derived behavior remains deferred |
| AT-06 | REQ-ATT-001, REP/DASH requirements | Thresholds, reporting, alerts | Approved formula/scope/threshold/recipient/version before implementation | Stops pending approval |

### Proposed Attendance architecture

After approval, use a CI4 `Attendance` module with an `AttendanceService` for eligibility, timing, authorization, corrections, and transactions; `AttendanceModel` and `AttendanceHistoryModel` for persistence; thin server-rendered controllers and explicit routes; views containing presentation only; and focused database/security integration tests. A future attendance table should reference `sessions` and `players`, enforce the approved uniqueness rule, carry branch scope derived from the session, and use versioning or row locking for corrections. No schema or code is authorized by this proposal.

### Proposed focused threat controls

| Threat | Proposed control, pending applicable approval |
|---|---|
| Unauthorized recording/correction | Dedicated server-side permissions and correction authority; deny by default. |
| Cross-branch access/leakage | Derive branch from session and persisted relationships; scope every query server-side. |
| Duplicate attendance | Database uniqueness on the approved event grain plus transactional duplicate rejection. |
| Stale updates | Optimistic version or row lock; require current version and reject conflicts. |
| Cancelled-session manipulation | Recheck session state under transaction; reject attendance for cancelled sessions. |
| Player eligibility bypass | Resolve only from persisted session roster/approved assignment rule; ignore forged ownership fields. |
| Audit tampering | Append-oriented history/audit path with actor, UTC timestamp, prior/new state, and reason. |
| Privilege escalation | Central authorization service; no controller role shortcuts or UI-only controls. |
| Mass assignment | Explicit model allowlists and service-level field validation. |
| Forged IDs | Validate existence and authoritative session/player linkage inside the transaction. |

## Traceability summary

| Requirement | Decision dependency | Module/capability | Acceptance criteria | Automated test/evidence |
|---|---|---|---|---|
| REQ-AUTH-001 | SEC-01, SEC-14, INT-01 | Authentication/accounts | Recovery request/reset endpoints, disablement, session rotation/invalidation, adaptive hashing, generic failure behavior, approved recovery rate limiting, and audit secrecy are verified. | `tests/database/AuthLifecycleIntegrationTest.php`, `tests/database/AuthRecoveryIntegrationTest.php`, and `tests/database/RecoveryRateLimiterIntegrationTest.php` pass against `mcc_cricket_club_test`; `tests/Unit/SmtpRecoveryDeliveryTest.php` verifies the controlled CodeIgniter Email boundary. Real SMTP delivery and deployment evidence remain outstanding. |
| REQ-RBAC-001 | SEC-02, SEC-04 | Authorization framework | Unauthenticated, disabled, and unpermitted contexts are denied. | `tests/Unit/AuthorizationServiceTest.php` (not executed). |
| REQ-BRANCH-001 | SEC-03, BR-01-BR-10 | Branch management | Create, list, details, update, and non-destructive active/inactive lifecycle transitions are restricted to authenticated `rbac.manage` users, accept only validated name/code/address, preserve related records, enforce unique name/code, and record successful audit metadata. Under user-provided BR-10, a Super Admin receives an assignment only to each branch they create; an existing branch may be assigned to that Super Admin only when its `branch_created` audit event verifies them as creator. No other unassigned branches become visible. Physical deletion is prohibited. | `AdminService` uses the existing `user_branches` and audit mechanisms; creator assignment is transactional with branch creation. The two existing creator assignments were applied to `mcc_cricket_club_test` after audit verification. No tests were run for this change. Destructive legacy branch tests remain excluded. |
| REQ-AUDIT-001 | OPS-01 | Audit infrastructure | Required metadata is present, sensitive fields are redacted, authorized audit reads are scoped, and application write paths are denied. | `tests/Unit/AuditLoggerTest.php` and `tests/Unit/Ops01AuditPolicyTest.php`; deployment/database-protection evidence remains separate. |
| REQ-DOC-001 | SEC-06-SEC-13, OPS-03 | Private storage/documents | Traversal is rejected; objects are private; request-time authorization and policy tests remain blocked. | `tests/Unit/PrivateStorageTest.php` (not executed). |
| REQ-DOC-002 | SEC-06-SEC-13, OPS-03 | Document verification/download | Verification, expiry, controlled download, replacement history, and policy tests remain blocked. | Business tests not created; blocked. |
| REQ-OPS-001 | OPS-02, OPS-11, OPS-12 | Operations | Backup restore, incident, monitoring, and deployment evidence exists. | Evidence not found. |
| REQ-CRM-001 | LD-01-LD-08, LD-13, DAT-01 | CRM/leads | Lead capture, approved source/status behavior, and the approved controlled Follow-Up workflow are verified. | Safe focused configuration/browser tests and `tests/database/CrmFollowUpSafeTest.php` pass; legacy `CrmLeadIntegrationTest.php` remains unexecuted because it contains destructive global cleanup. Campaign, counselor, remarks, probability, and broader conversion presentation remain outside verified scope. |
| REQ-ADMISSION-001 | PL-01-PL-06, AD-01-AD-03, LD-09-LD-11 | Admissions/conversion | Seven-status lifecycle, exact transition matrix, rejection/withdrawal reasons, five approved checklist controls, Lead-linked and Direct Admission intake, shared browser MVC list/detail/new/edit UI, safe filters, branch/authorization enforcement, idempotent transaction-safe conversion, lineage, history, and audit are verified. | Safe focused `tests/database/AdmissionsBrowserSafeTest.php`: 4 tests, 65 assertions. Destructive legacy Admission tests are not executed. Trial scheduling and other modules remain separate scope. |
| REQ-PLAYER-001 | BR-02, BR-04, BR-06, PA-01, PA-02, REL-01-REL-05, DAT-01/02 | Players/guardians | Approved identity/profile read and controlled update, admission conversion lineage, lifecycle history, branch assignment/history, batch/group and primary-coach assignment, approved contact creation, branch isolation, duplicate identity protection, and audit are verified. | Safe `tests/database/PlayerWriteWorkflowSafeTest.php` plus `tests/database/PlayerBrowserSecuritySafeTest.php`; guardian visibility/revocation, migration, and real-browser evidence remain unresolved. Destructive `PlayerManagementIntegrationTest.php` is excluded. |
| REQ-SESSION-001 | SCH-01-SCH-05, BR-04, OPS-09 | Sessions/grounds/batches | Approved one-time scheduling, branch ownership, persisted-assignment eligibility, conflict prevention, cancellation, linked rescheduling, coach availability, and UTC/MCC timezone controls are implemented and tested. | Safe `tests/database/SchedulingSafeWorkflowTest.php`: 6 tests, 44 assertions; canonical Browser routes/shared staff UI verified. Destructive legacy `SchedulingIntegrationTest.php` is excluded. Attendance remains separate. |
| REQ-EQUIPMENT-001 | EQUIP-01, REQ-RBAC-001, REQ-BRANCH-001, OPS-01 | Equipment inventory | Limited user-authorized Name/Quantity/Branch create/list/edit/soft delete; immutable Branch on edit; separate operation permissions; active assignment validation; transactional create/update/delete audit. Hard deletion and restore workflow remain `NEEDS_DECISION`. | `app/Modules/Equipment`, `app/Services/EquipmentService.php`, `app/Services/BranchContextService.php`, and `20260921000100_CreateEquipmentInventory.php` / `20260921000200_AddEquipmentUpdateDelete.php`; focused tests added but not run per request. |
| REQ-ATT-001 | AT-01-AT-06 | Attendance | Eligible player/session records, cancellation exclusion, approved statuses, correction/history, branch authorization, and server-rendered MVC workflow are verified; denominator remains deferred. | Attendance integration/feature tests pass; derived denominator and AT-06 behavior remain deferred. |
| REQ-REPORT-001 | REP-01-REP-03, SEC-11, NFR-08 | Reports/exports | Scope, fields, authorization, timestamp, logging, and failure behavior are verified. | Business tests not created; blocked. |
| REQ-DASH-001 | DASH-01-DASH-03 | Dashboard | Only approved source-backed KPIs appear with defined scope and freshness. | Business tests not created; blocked. |
| REQ-PERF-001 | PF-01-PF-06 | Performance | Approved formulas, source grain, locking, visibility, and correction behavior are tested. | Business tests not created; deferred/blocked. |
| REQ-FIT-001 | FIT-01-FIT-03 | Fitness | Units, thresholds, visibility, and missing-data behavior are approved and tested. | Business tests not created; deferred/blocked. |
| REQ-PORTAL-001 | SEC-09, SEC-14, REL-01-REL-05 | Parent/player portals | Relationship verification, consent, category visibility, revocation, and audit are tested. | Business tests not created; blocked. |
| REQ-ADV-001 | MT-01-MT-06, HP-01-HP-05, INT-01 | Advanced operations/integrations | Each future capability requires its own approved decisions and acceptance tests. | No implementation evidence. |

### Attendance MVC implementation update (2026-08-29)

The approved Attendance source-event scope now includes a server-rendered CI4 MVC session list and roster page. The page is protected by authentication and `attendance.view`; recording and correction forms retain CSRF protection and submit through `AttendanceService`, which remains authoritative for validation, branch scope, roster eligibility, duplicate prevention, cancellation rules, correction reasons, optimistic versions, history, and audit. AT-01 denominator behavior and deferred AT-06 calculations, reports, alerts, and notifications remain unavailable.

### Administration implementation update (2026-08-29)

The existing `rbac.manage` permission now protects a server-rendered CI4 MVC workflow for user management, role management, approved permission assignment, effective-permission viewing, account enablement, and authorized branch assignment. Mutations use service-side authorization, explicit model allowlists, CSRF-protected POST routes, session-version invalidation for disabled accounts, and audit events. No new business permissions or policy decisions were introduced.

An unresolved dependency produces the chain `Requirement → NEEDS_DECISION → BLOCKED`; it cannot be bypassed by a scaffold or a proposed recommendation.

Staff dashboard UI update (2026-08-29): the authenticated application landing page uses the shared staff layout and displays only permission- and branch-scoped source-backed summaries from already implemented modules. It provides navigation, quick actions, recent leads/admissions, upcoming sessions, and safe empty states without introducing new KPI, denominator, reporting, alert, or notification rules. The separately deferred Operational Dashboard module remains unchanged.

CRM configuration update (2026-08-30): Lead Sources and Lead Statuses now use database-backed, ordered active/inactive configuration records. Existing lead text values were preserved and migrated into configuration records; inactive historical values remain readable, while new lead creation accepts only active configured values.
## Cross-project UI/UX consistency requirement

This is an authoritative project-wide requirement for all current and future pages and modules. The established application design language and reference pages—especially Lead List, Lead Details, and User Details—must be reused consistently for layout structure, sidebar/topbar, page headers, cards, sections, typography, colors, spacing, alignment, forms, controls, actions, badges, tables, pagination, filters, search, shared date pickers, overlays, alerts, empty/loading/error states, responsive behavior, hover/focus/active states, accessibility, and shared CSS/components/utilities.

Mandatory implementation rules:

1. Reuse existing layouts, components, CSS classes, and utilities before creating new patterns.
2. Do not duplicate page-specific CSS or markup when an existing reusable pattern can be extended.
3. New pages must match the established Lead/User visual and structural patterns.
4. Labels, controls, buttons, tables, cards, and action areas must use the shared alignment system.
5. Create, Edit, View, Deactivate, Save, Cancel, Filter, and other actions must use established button styles and spacing.
6. Every page must be responsive on desktop, tablet, and mobile without unintended horizontal overflow.
7. Date fields must use the shared date-picker component; native `input type="date"` requires explicit approval.
8. Popovers, tooltips, and detail overlays must be trigger-relative and must not disturb table rows or page layout.
9. The approved Valex-based visual language and centralized design tokens remain the project standard.
10. UI work must not weaken RBAC, validation, CSRF, authorization, or business logic.

Player scope update (2026-08-31): canonical browser routing, branch-scoped list filtering, profile/detail presentation, approved lifecycle history, and REL-01/REL-02 contact creation are implemented through `PlayerService`. The Player requirement remains `PARTIALLY_IMPLEMENTED` because REL-03--REL-05, DAT-02 evidence, deployment evidence, and full non-destructive workflow coverage remain outstanding. Legacy `/players/{id}/page` links redirect to canonical `/players/{id}`.

Admission-to-Player contact transfer update (2026-09-24, user-provided direction; not client approval): approved Admission conversion links non-empty father and mother names as `guardian` contacts and a non-empty `emergency_contact` as an `emergency-contact`. The Admission parent email and mobile are attached only to the first newly created guardian. Conversion retries are idempotent, manually added contacts are preserved, and the source Admission is recorded in audit data. Existing branch and Player visibility authorization remains in force; REL-03, REL-04, and REL-05 privacy, portal, editing, and revocation decisions remain `NEEDS_DECISION`.

Focused transfer coverage was added to `tests/database/PlayerWriteWorkflowSafeTest.php`; it has not been run.

Player browser verification update (2026-08-31): read-only CI4 feature/render coverage passes in `tests/database/PlayerBrowserSecuritySafeTest.php` with 5 tests and 57 assertions. It verifies authentication/permission denial, branch-scoped list/detail rendering, search/status filters, canonical and legacy routing, lineage/status presentation, shared layout/navigation, and authorization contracts. It does not claim write-workflow, real-browser, or production evidence; destructive Player integration tests remain excluded.

Player safe write verification update (2026-09-01): `tests/database/PlayerWriteWorkflowSafeTest.php` verifies Lead-linked and Direct Admission conversion, idempotent retry, lineage, unauthorized/cross-branch conversion denial, lifecycle status history/actor/audit, profile validation and branch-field escalation rejection, approved contact types, and batch/coach assignment branch/permission boundaries. The test creates only unique persistent `TEST_MCC_PLAYER_WRITE_*` fixtures and performs no cleanup. CI4 feature/render assertions also cover canonical Player forms and the shared shell; no real-browser or production verification is claimed.

CRM Follow-Up verification update (2026-09-01): `tests/database/CrmFollowUpSafeTest.php` passes 9 tests / 130 assertions for approved `LD-13` types, historical scheduling, pending edits, completion outcomes, cancellation reasons, immutable terminal records, controlled correction, branch/RBAC enforcement, server-side filters, canonical routes, CSRF-protected browser forms, shared navigation, and no physical deletion path. The filter test creates its own unique fixture and is independent of test order. No new CRM business policy was introduced.

CRM Lead UI and safe workflow update (2026-09-01): the canonical `/crm/leads/{id}` detail route now owns the Lead workflow; the documented `/crm/leads/{id}/page` URL remains a permission-protected 301 compatibility redirect. The shared Lead list/detail UI now presents preserved GET filters, server-side branch scope, relationship counts, permission-aware actions, and branch-safe Trial/Admission/Follow-Up context. `tests/database/CrmLeadSafeWorkflowTest.php` passes 8 tests / 66 assertions using persistent `TEST_MCC_LEAD_<unique-id>` fixtures without cleanup. The approved configurable duplicate warning, lifecycle and Follow-Up semantics, and Trial/Admission service boundaries remain unchanged; broader Campaign, Counselor, remarks, probability, analytics, and conversion-reporting behavior remains outside the verified scope.

Cross-module shell and route consistency update (2026-09-01): the shared staff shell remains the single document/layout boundary, and its sidebar, mobile drawer, collapse state, active-group behavior, Coming Soon entries, and Font Awesome controls are centralized in the shared partials, `NavigationService`, CSS, and JavaScript. Implemented route links were audited across CRM, Trials, Admissions, Players, Scheduling, Attendance, and Branch Management; canonical links and approved compatibility URLs are retained without duplicate workflows. Safe cross-module verification passes 88 tests / 1,035 assertions. No browser verification or deployment evidence is claimed; JavaScript runtime syntax tooling was unavailable in the environment.

Branch-management verification update (2026-09-01): `tests/database/BranchManagementSafeTest.php` passes 5 tests / 35 assertions for approved branch creation, update, active/inactive lifecycle, validation, RBAC, authentication, CSRF form rendering, canonical routes, shared staff UI, and audit events. Tests use persistent `TEST_MCC_BRANCH_WRITE_*` fixtures and never perform destructive cleanup.

Sessions/Scheduling verification update (2026-09-01): `tests/database/SchedulingSafeWorkflowTest.php` passes 6 tests / 44 assertions for approved creation, UTC/MCC timezone handling, roster eligibility, coach availability/conflict protection, cancellation, linked rescheduling, audit, permission checks, branch isolation, canonical routes, and shared staff UI rendering. The test uses unique future windows and never performs destructive cleanup. No attendance policy was introduced.

Shared navigation update (2026-09-01): all authenticated staff pages now use the permission-aware `NavigationService::staffGroups()` definition through one reusable sidebar. Implemented items are grouped by CRM, Admissions, Players, Scheduling, and Administration; active parent groups open automatically, desktop collapse state and group state use non-sensitive local storage, and mobile drawer behavior remains shared. No business routes or permissions were added.
Player UI polish update (2026-09-01): the approved Players list now presents Admission/Lead/optional Trial lineage alongside branch, batch, coach, status, and contact information. The profile identifies admission state and explicit Direct Admission/no-linked-Trial cases. Safe Player browser/security and write-workflow coverage passes; real-browser and deployment verification remain unclaimed.
### Coach and Attendance user-direction amendment (2026-09-25; not client approval)

The supplied Coach and Attendance direction defines the scoped implementation slice. Coach profile fields remain optional: Experience, Qualification, Certifications, structured/existing Availability, Specialization, and Leave through the existing leave workflow. Coaches retain one or more authorized branch assignments and may be assigned only to sessions in those branches. Existing identity/account fields and branch history/audit remain authoritative.

Attendance uses exactly `PRESENT`, `ABSENT`, `LEAVE`, `MEDICAL`, `LATE`, `RAIN_CANCELLED`, and `COACH_CANCELLED`. Existing `EXCUSED` values are mapped to `LEAVE` by a forward-only migration while preserving attendance and history rows. Percentages use occurred, non-cancelled scheduled sessions; `LEAVE` and `MEDICAL` are excluded from the denominator; `PRESENT` and `LATE` count as attended; `ABSENT` counts as not attended; and zero denominators display `No data`. Daily uses the configured local calendar date, weekly Monday-Sunday, monthly calendar month, and yearly calendar year.

Authorized staff may mark attendance only in permitted branches. Coaches may mark assigned sessions only where the existing permission model allows it. Branch Admin and Super Admin may correct attendance in authorized branches. Corrections require a reason and append actor, timestamp, prior status, new status, and reason to history and audit. Attendance history is not deleted.

The Coach KPI formulas and in-app/report-ready alert definitions are recorded as user direction for the scoped implementation. Feedback and performance values remain unavailable where source records do not exist and must display `No data` or `Not available` as defined. This amendment does not claim client approval or resolve wider visibility, recipient, or integration policy.

Coach implementation evidence update (2026-09-25; user-direction scope, not client approval): the existing Coach module now provides validated create/edit/display coverage for optional Qualification, Certifications, Availability notes, Specialization, and Experience fields through `coach_profiles`. Coach detail reads resolve current authorized branch assignments from `user_branches`, assigned sessions from `sessions`, and branch-scoped leave records from `coach_unavailability`; these records remain branch-scoped and no duplicate Coach assignment or leave tables were added. Source-backed KPIs for sessions conducted, attendance compliance, and assigned players are calculated by `CoachManagementService::kpis()` from existing sessions, roster/attendance, and player-coach assignment data, and are shown only when `coach.kpi.view` is authorized. Player feedback rating and Performance improvement index remain `Not yet available` because no approved source records and formulas exist. The user-provided Coach Management expansion authorizes the corresponding branch, session, leave, and audit workflows; KPI formula/version/visibility decisions for future sources remain blocked by `CM-05`.

Coach Management user-direction expansion (2026-09-25; implementation authorized by user direction, not client approval): CM-02 authorizes multi-branch Coach assignment within the actor's current authorized branches with historical branch preservation and audited changes. CM-03 authorizes session Coach assignment/reassignment within the session branch, with pre-start reassignment and reasoned authorized correction after start. CM-04 authorizes branch-scoped leave/availability create, edit, approval, cancellation, and correction for authorized Branch Admin/Super Admin users, with required dates/reason, overlap rejection, retained approved history, and no historical attendance mutation. CM-06 requires append-only audit metadata and reasoned corrections. Feedback ratings use approved 1–5 records linked to completed Coach sessions and Players; the feedback KPI is the reporting-period average. The performance index uses earliest/latest approved comparable Player performance records and average percentage improvement. CM-05 requires documented KPI versions, source-backed recalculation, application-timezone periods, branch/permission scope, and `Not yet available` for absent data. This is user direction only; unresolved client approval and any unspecified workflow details remain `NEEDS_DECISION`.

Implementation limitation: the Performance Improvement Index now has a user-provided PII-v1 source and formula direction and a forward-only approval-marker migration. Existing performance records remain excluded until explicitly approved; broader client approval and unresolved PF/CM policy items remain `NEEDS_DECISION`.

Coach availability policy direction (2026-09-25; user-provided, not client approval): availability is stored per Coach and authorized branch as either a recurring weekday interval or a one-time date interval. Times use the configured application timezone, start must precede end, and overlapping active records are rejected. Branch Admin and Super Admin management remains limited to current authorized branches; Coaches retain view-only access unless an existing permission explicitly permits edits. Approved leave takes precedence, availability is checked by existing scheduling validation, and availability changes are retained and audited with reasoned corrections or cancellations. Existing sessions and historical attendance are not changed automatically. Performance Improvement Index and external alerts remain NEEDS_DECISION.

Scheduling validation direction (2026-09-30; user-provided, not client approval): Coach availability records are informational for Session create, edit, reschedule, and Coach reassignment workflows. Scheduling must not reject a session because its time falls outside a configured availability interval or because no availability interval exists. Scheduling continues to reject overlapping approved Coach leave, inactive or unauthorized Coaches, invalid session windows, scheduling conflicts, and ineligible Players. Availability records remain unchanged and available for Coach management and display.

Scheduling completion timing direction (2026-09-30; user-provided direction — not client approval): A scheduled session may be completed before its scheduled end time when the immutable attendance roster snapshot exists, contains members, and every snapshot player has recorded attendance. Existing permission, branch, cancellation, audit, transaction, and idempotency rules remain unchanged.

### CM-04B — Coach leave approval workflow (user-provided direction; not client approval)

New leave starts `PENDING`. Authorized Branch Admin and Super Admin users with the existing `coach.update` permission may approve or reject leave within permitted branches; rejection requires a reason. Coaches may cancel their own pending leave, while authorized managers may cancel pending or approved leave with a reason. Approved leave remains the scheduling blocker used by `SchedulingService`; attendance history is not changed. Leave rows are retained, and every status transition records actor, branch, timestamp, previous value, new value, and reason through append-only audit logging.


### PII-v1 — Performance Improvement Index (user-provided direction; not client approval)

Use approved Player Performance records only. For each Player, skill (`record_type`), and metric, select the earliest and latest approved records ordered by `recorded_on` and record ID. Require two comparable records; ignore missing, invalid, incomparable, and zero-baseline values. Calculate `((latest - baseline) / abs(baseline)) * 100` for each valid metric and average the valid results, preserving negative decline values. Display `Not yet available` when no valid comparison exists. The Coach KPI is read-time, branch-scoped, and visible only with `coach.kpi.view`; source corrections require a reason and append-only audit metadata and recalculate the KPI. This is user direction only; unresolved wider performance formulas, source grain, correction policy, and client approval remain `NEEDS_DECISION` under `PF-01`–`PF-06` and `CM-05`.

### Documents secure-default direction (2026-09-27; user-provided, not client approval)

The user supplied these secure defaults for Documents policy reconciliation. They do not change the `NEEDS_DECISION` status of `REQ-DOC-001`, `REQ-DOC-002`, `DOC-01`–`DOC-08`, `OPS-03`, or `OPS-10`:

- Sensitive documents remain in private storage. Uploads require server-side MIME, extension, size, and upload-error validation, with safe server-generated filenames.
- Missing and unauthorized document requests use one generic `Not found.` response.
- Verification states are `PENDING`, `VERIFIED`, and `REJECTED`; only authorized staff with access to the document branch may verify.
- Expired documents are excluded from normal use.
- Medical documents are restricted to explicitly authorized roles.
- Parent/Guardian and Player visibility is limited by approved relationship and branch rules.
- Replacement creates a new version and retires the previous version; retired documents remain auditable but are not downloadable.
- Malware scanning is required before verification or download, and failed scans are quarantined.
- Audit history is retained; file contents and credentials are never logged.
- Deployment-owner confirmation is required for scanner, private-storage, retention, and encryption settings.

This is user-provided direction only. No implementation, permission change, schema change, or client approval is claimed.

### Documents foundation implementation direction (2026-09-27; user-provided, not client approval)

The first foundation slice is now implemented in source as user-directed work: verification states and reasoned branch-scoped verification actions; expiry-date validation and normal-use exclusion; new-version replacement with prior-version retirement; medical-document denial unless an explicit medical permission is present; and fail-closed scanner quarantine when no scanner integration is configured. The forward-only schema migration and focused source/database coverage remain subject to local migration application and test verification. This evidence does not change client approval status.

## Parent and Student portal direction (2026-10-02)

User-provided direction — not client approval. A separate portal login accepts an explicit Student or Parent choice, uses secure password hashes linked through `portal_accounts`, and keeps portal sessions separate from staff sessions. Student access is limited to the active linked Player; Parent access is limited to active Players linked through guardian contact relationships. Portal dashboards expose only profile, approved schedule, batch/program, and own/linked attendance data. Account activation, password reset delivery, consent, and detailed document visibility remain NEEDS_DECISION until explicitly approved.

Portal mobile OTP direction (2026-10-02; user-provided direction — not client approval): `/student-login` now requests a cryptographically random, short-lived WhatsApp OTP using a normalized verified mobile number for Student or Parent access. OTPs are stored only as hashes, are single-use, attempt-limited, resend-rate-limited, and never rendered or logged. Student identity resolves to one active Player; Parent identity resolves only through active guardian `player_contacts` relationships. Unknown and ineligible mobiles receive the same generic response. Provider configuration, activation, consent, and detailed portal document visibility remain deployment/NEEDS_DECISION items.

Portal development OTP display direction (2026-10-02; user-provided direction — not client approval): for local testing only, the generated OTP may be shown on the same `/student-login` response when `CI_ENVIRONMENT` is `development` or `testing`, the connected database is exactly `mcc_cricket_club_test`, and `PORTAL_OTP_DEBUG=true`. The display is never enabled in production or staging, never stored as plaintext, logged, placed in URLs, attributes, cookies, audit metadata, staff pages, or API responses, and is cleared after a new request, expiry, successful verification, or logout.
### User-provided Tournament fixture and standings direction (2026-10-03)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** The bounded Tournament slice includes manual branch-scoped fixtures linked to one Tournament, a Round, same-Tournament Home/Away teams, an active branch Ground, separate MCC-local date/time fields, Scheduled/Completed/Cancelled/Postponed statuses, reasoned cancellation/postponement, audited changes, and completed-fixture standings with Win 2, Loss 0, Tie 1 each, and No result 1 each. Net run rate, scorecards/innings, registration, selection policy, notifications, exports, and other unresolved T-01–T-20 workflows remain disabled.

## Match Player Performance direction (2026-10-03)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** The bounded Match Player Performance slice adds source batting and bowling entries for every selected squad or Playing XI Player. It uses existing `match.update` for active Match entry and existing `performance.update` for reasoned corrections on completed Matches. Records are branch-scoped, linked to the Match and Player, grouped by an explicit innings number and team side, and audited append-only. Cricket calculations are read-time only; missing denominators return `Not available`, and dot balls remain unavailable because no event source exists. Scorecards, event-level innings, net run rate, selection policy, registration, tournament integration, exports, and notifications remain unresolved or deferred.

## Match Player Fielding Details direction (2026-10-03)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** The Match Player Performance slice now includes per-Player fielding status, catches, stumpings, completed run-outs, assists, direct-hit run-outs, dropped catches, position, and notes. Fielding totals are calculated at read time. Existing Match/Performance permissions, branch checks, completed-Match correction reasons, transactions, and audit behavior remain authoritative. No wider scorecard, event, tournament, or performance-governance policy is approved by this direction.

## Tournament Phase 1 reconciliation — T-01 through T-04

**Recorded:** 2026-10-04  
**Status:** USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.  

The user selected the recommended secure defaults for the first four Tournament decision areas. These records authorize only the bounded implementation described below and do not approve T-05 through T-20.

- **T-01:** Keep the existing Tournament type allowlist; standalone branch-owned Tournaments; explicitly authorized Match links only; no parent competition hierarchy.
- **T-02:** Allow `planned → active → completed`, `planned → cancelled`, and `active → cancelled`; completed and cancelled are terminal; no reopening; exceptional changes require a reason; record transitions in append-only audit history.
- **T-03:** One owning branch; no implicit cross-branch access; Grounds, Teams, Players, Coaches, Fixtures, Matches, and reports must match the owning branch; no branch transfer workflow.
- **T-04:** Separate MCC-local date/time inputs; service-layer conversion; UTC storage where timestamps are required; no visible timezone suffix; fixtures must fall within Tournament start/end boundaries; use existing Match/Scheduling conventions.

**Approver identity:** User-provided direction; client approver not recorded.  
**Approval date:** Not client-approved.  
**Effective date:** 2026-10-04 for the bounded implementation only.
### Attendance-first Session Player feedback direction (2026-10-05)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** Session Player feedback may be recorded once attendance exists for that Player in the immutable roster snapshot. Completion is not a prerequisite for scheduled, active, or completed sessions. Existing Coach/staff branch permissions, CSRF, snapshot membership, correction reasons, audit history, and transaction rules remain in force; cancelled sessions are excluded pending any future approved policy.

## Match Opponent catalog direction (2026-10-05)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** Opponents are required branch-scoped catalog records with active/inactive state and branch-local unique names. Match forms select active authorized Opponents by ID; free-text opponent entry is not available. Existing Match opponent values are retained and safely backfilled by forward-only migration.
## Achievements requirements reconciliation (2026-10-06)

`docs/ACHIEVEMENTS-REQUIREMENTS-DECISION-QUESTIONNAIRE.md` was reviewed. No response option, resolution, approver, approval date, or effective date is populated. Blank fields are not approval, so every Achievement area remains `NEEDS_DECISION`.

The implementation gate and evidence matrix are recorded in `docs/ACHIEVEMENTS-IMPLEMENTATION-MATRIX.md`. No Achievement routes, services, models, views, permissions, migrations, tables, navigation entries, or placeholder pages may be added until explicit decisions are recorded. Existing Tournament, Match, Attendance, Performance, Documents, and Portal decisions do not authorize Achievement behavior.

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL (2026-10-06):** Secure defaults are recorded for all Achievement areas: controlled categories; bounded title/description; explicit active Player/Coach eligibility; one owning branch; manual source references and manual creation; explicit approval; draft/approved/revoked/archived lifecycle; duplicate prevention; reasoned corrections/revocations; private certificates only after further approval; staff-only portal visibility by default; least-privilege future permissions; append-only audit; MCC-local input with UTC storage where required; non-destructive retention; no reports/exports or notifications until separately approved. These defaults do not authorize implementation.

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL (2026-10-06):** The bounded foundation slice implements only a branch-scoped Achievement catalog with title, category, description, active/inactive state, create/list screens, `achievement.view`/`achievement.create`, server validation, CSRF, transaction and audit behavior. Recipient eligibility, awards, approvals, certificates, portal visibility, reports, exports, notifications, and retention remain `NEEDS_DECISION`.

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL (2026-10-06):** The explicitly selected next scope is draft-only manual award creation with Player or Coach recipient assignment. It permits only active same-branch recipients, one recipient per draft, bounded nomination notes, `draft` status, existing branch isolation, centralized RBAC, CSRF, transactions, and audit metadata. It does not approve automatic eligibility, approval/publication, certificates, portal visibility, reports/exports, notifications, retention, or archival.

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL (2026-10-06):** Implementation evidence confirms the draft-only award routes, recipient validation, correction-reason requirement, transactions, and audit writes. No published award behavior is available.
## Phase 2 decision reconciliation

Reviewed 2026-10-06. No selected options, resolutions, comments, approver names, approval dates, or effective dates were recorded for APL-01 through APL-05. Blank fields are not approval. All five items remain NEEDS_DECISION; none is approved or rejected.

| ID | Workflow | Status |
| --- | --- | --- |
| APL-01 | Draft approval | NEEDS_DECISION |
| APL-02 | Publication | NEEDS_DECISION |
| APL-03 | Published lifecycle | NEEDS_DECISION |
| APL-04 | Revocation | NEEDS_DECISION |
| APL-05 | Correction after publication | NEEDS_DECISION |

Recommended defaults remain USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL. No Phase 2 code, route, permission, migration, table, menu item, or test is authorized.
## Phase 2 secure-default direction — USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL

On 2026-10-06, user-provided defaults were recorded for APL-01 through APL-05: explicit approval before publication using achievement.approve; branch-scoped publication of approved drafts using achievement.publish; read-only published records with reasoned achievement.update corrections; non-destructive reasoned revocation using achievement.revoke; and explicit post-publication correction preserving actor, branch, timestamp, old values, new values, and reason, with revoked-record correction restricted.

These are not client approvals. Approver, approval date, and effective date were not provided. All five items remain NEEDS_DECISION and no implementation is authorized.
## Phase 2 lifecycle implementation evidence — USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL

The APL-01 through APL-05 defaults are implemented as a bounded lifecycle slice: draft approval with achievement.approve, publication with achievement.publish, read-only published records, reasoned revocation with achievement.revoke, and reasoned published corrections using achievement.update. Status changes are branch-scoped, transactional, version-checked, and audited with actor, branch, timestamp, reason, and before/after values. Revoked records are retained and no deletion workflow is introduced.

Certificates, portal visibility, reports, exports, notifications, retention, and automatic eligibility remain unavailable and unresolved.
## Phase 3 secure-default direction — USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL

Secure defaults were recorded for A3-01 Certificates, A3-02 Parent/Student portal visibility, A3-03 Reports and exports, A3-04 Notifications, A3-05 Retention and archival, and A3-06 Automatic eligibility. Each remains NEEDS_DECISION because no client approver, approval date, or effective date is provided. No code, route, permission, migration, table, menu, or test is authorized.
## A3-01 certificate implementation evidence — USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL

A bounded certificate slice is implemented for published Achievement awards only. It uses private LocalPrivateStorage, safe deterministic generated filenames, branch-scoped generate/download permissions, idempotent award-to-certificate linkage, and append-only generation audit events. No certificate portal visibility, replacement, retirement, or public URL workflow is introduced.

A3-02 portal visibility and A3-03 reports/exports have bounded user-directed slices implemented; A3-04 notifications, A3-05 retention/archival, and A3-06 automatic eligibility remain NEEDS_DECISION and are not implemented.

### A3-02 portal visibility implementation note (2026-10-06)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** The bounded A3-02 slice now exposes a read-only portal view for published Player Achievement awards. Student visibility is restricted to the authenticated active Player. Parent visibility is restricted to active Players linked through the authenticated verified guardian relationship. Queries require active owning branches and matching award/player branch IDs. Draft, revoked, unpublished, coach-recipient, nomination-note, lifecycle-reason, approval, and audit metadata are excluded. A3-03 through A3-06 remain unimplemented and `NEEDS_DECISION`.

### A3-03 bounded implementation note (2026-10-06)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** Implemented a read-only Achievement report for published awards with authorized branch, Player, Coach, category, and publication date filters plus CSV export. Existing `report.view` authorization is enforced server-side. Exports preserve active filters and write an audit event containing only non-sensitive filter-presence and row-count metadata. Drafts, revoked awards, nomination notes, approval/lifecycle metadata, and audit internals are excluded. Notifications, retention, and automatic eligibility remain `NEEDS_DECISION`.


## Director Dashboard secure-default direction (2026-10-11)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** DASH-01 through DASH-20 use branch-scoped access with an explicit authorized “All Branches” option for Super Admin only; MCC-local date/month inputs with UTC conversion for stored timestamps; active, authorized source records; no sensitive medical, document, guardian, or private contact data; no invented KPI or performance formulas outside approved existing metrics; clear safe empty states; no caching until freshness and invalidation rules are defined; existing centralized permissions and branch authorization; exports inheriting dashboard filters and authorization; and audit limited to sensitive administrative actions without private data. Exact numeric formulas remain unresolved and every DASH item remains NEEDS_DECISION. No implementation, permission grant, migration, cache, export, or UI change is authorized by this direction.

## Individual Session Skill taxonomy direction (2026-10-11)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** The secure defaults recorded for SKILL-01 through SKILL-13 are planning direction only. They remain `NEEDS_DECISION` with blank approver, approval date, and effective date. No skill fields, taxonomy, routes, permissions, migrations, media, reports, or portal visibility are approved for implementation.
## Match-Tournament ownership and result direction (2026-10-11)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** The planning flow is that a
Tournament schedules Fixtures, Fixtures may lead to Matches, and verified
completed Match results feed Tournament standings. Standalone Matches remain
allowed; a Fixture may link to one Match; and a Match may link to only one
Tournament Fixture.

The Fixture planning boundary owns Tournament scheduling, round, date/time,
Ground, and Fixture status. The linked Match owns cricket result and completion
details. A verified completed Match result is planned as authoritative for
winner, tie, no-result, and Match completion. Standings points are planned to
come only from a completed, verified Match linked to the Fixture; scheduled,
postponed, cancelled, and incomplete Fixtures contribute no points. Postponed
Fixtures may be rescheduled with audit history, while cancelled Fixtures are
retained and contribute no result or points.

Result corrections are planned to require authorized permission, a reason,
optimistic version checking, before/after audit metadata, and a transaction.
Ordinary editing must not change completed results or awarded points. Match,
Fixture, Tournament, Ground, Opponent, and Player references remain planned as
same-authorized-branch relationships with no implicit cross-branch links.

Decision status for every item above: `NEEDS_DECISION`. Approver, approval date,
and effective date are blank. No requirement, route, permission, schema,
migration, test, or implementation authorization is created by this direction.
## Complete Admission Form capture (user direction)

USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL. The Admission create/edit form
captures the September 2026 reference fields using nullable columns where no
existing required rule applies. Capture does not authorize payment collection,
hostel allocation, signature verification, medical visibility, notifications,
or portal publication.
