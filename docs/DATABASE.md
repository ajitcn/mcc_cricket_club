# MCC Database Record

This document records existing and user-authorized schema additions. It does not establish client approval of a replacement SRS baseline.

## Trial media upload (2026-10-18; scoped implementation direction)

Trial media uses the forward-only `20261018000300_CreateTrialMedia` migration and the separate `trial_media` table. The existing `documents` table remains unchanged because its ownership model is Admission/Player-specific. Metadata stores the Trial, branch, original display name, private storage key, MIME type, file size, uploader, status, and timestamps. Video objects are stored through `LocalPrivateStorage` below `WRITEPATH/private`, never under `public/assets` and never exposed through a public URL.

The first implementation supports `video/mp4`, `video/webm`, `video/quicktime`, and `video/x-msvideo`, with a 100 MB maximum upload size. Uploads require the existing branch-scoped `lead.update` permission; controlled preview/download requires `lead.view`. Upload metadata is written transactionally and a stored object is removed when metadata persistence fails. Media is append-only in this slice; no deletion or replacement action is exposed. Retention, consent, scanning, and future approved image/document types remain separate policy decisions.

## Programs and Batches

**Source/status:** `PROG-BATCH-01`, user-provided direction dated 2026-09-22; not client approval.

Migrations: `app/Database/Migrations/20260922000200_CreateProgramsAndLinkBatches.php` and `app/Database/Migrations/20260922000300_AddProgramBatchPermissions.php`.

| Table | Fields | Constraints and purpose |
|---|---|---|
| `programs` | `id`, `name`, `description`, `created_by`, `created_at`, `updated_at` | Required validated name, optional nullable description (maximum 5,000 application characters), and system-managed creator/timestamps. No status, duration, fee, curriculum, or branch field is added. |
| `batches` | Existing fields plus nullable `program_id` | `program_id` references `programs.id`; new Batch creation requires an existing Program. Nullable preserves historical rows that predate this direction. `branch_id` remains the existing branch-owned relationship. |

No capacity column is added. The previous `BatchModel` capacity allowlist mismatch is resolved by aligning the model to the existing schema. Existing-row Program backfill, making `program_id` non-null, lifecycle/deletion behavior, and dependent-record constraints remain `NEEDS_DECISION`. No migration was applied to a database by this change.

## Equipment inventory

**Source/status:** `EQUIP-01`, user-provided limited implementation direction dated 2026-09-21; implemented, not client approval.

Migrations: `app/Database/Migrations/20260921000100_CreateEquipmentInventory.php` and `app/Database/Migrations/20260921000200_AddEquipmentUpdateDelete.php`.

| Table | Fields | Constraints and purpose |
|---|---|---|
| `equipment` | `id`, `name`, `quantity`, `branch_id`, `created_by`, `created_at`, `updated_at`, `deleted_at` | `name` is `VARCHAR(150)`; `quantity` is unsigned integer; branch and creator are foreign keys. A branch/name lookup index supports scoped listing. `deleted_at` is nullable and marks soft-deleted rows. No name uniqueness constraint is imposed. The timestamps, creator, and deletion marker are system-managed metadata, not additional user-entered Equipment fields. |

Inventory records are branch-owned. Branch deletion is restricted while referenced; branch ID updates cascade consistently with current schema conventions. Both migrations are forward-only to preserve deployed inventory and soft-deletion history. The initial migration grants `equipment.view` and `equipment.create`; the follow-up grants `equipment.update` and `equipment.delete` to the existing Super Admin and Branch Admin roles recorded in `EQUIP-01`.

No assignment, maintenance, availability, category, hard-delete, restore, or integration tables are included. Soft deletion is recorded in `deleted_at`; application list queries exclude those rows and update/delete audit events are persisted transactionally. The current positive whole-number constraint, maximum database range, 150-character Name limit, duplicate-name behavior, fractional/serialized inventory, retention, restore workflow, and additional fields are implementation constraints or scope questions rather than client-approved rules; unresolved behavior remains `NEEDS_DECISION` under `EQUIP-01`.

The Coach and Attendance migration also changes `coach_profiles.experience_years` to optional text, preserving existing values, and maps legacy `EXCUSED` attendance values to `LEAVE` in current and history rows without deleting records.
## Coach and Attendance scoped user direction (2026-09-25; not client approval)

The existing `coach_profiles`, `user_branches`, `sessions`, `session_players`, `attendance`, and `attendance_history` tables are reused. Base migration `202609250001_ReconcileCoachAttendanceScope` changes legacy `EXCUSED` values to `LEAVE` in both current attendance rows and history rows without deleting records; deployment wrapper `20260925000100_ReconcileCoachAttendanceScopeDeployment` invokes the same base migration. No duplicate Coach leave table is introduced. Percentage, KPI, and report-ready alert data is calculated from existing source records; feedback and approved performance assessment sources remain unavailable when those tables contain no approved data.

Coach Management scoped user direction (2026-09-25; not client approval): existing Coach fields, branch assignments, sessions, `coach_unavailability`, attendance, and `performance_records` remain authoritative. A future implementation may add only the minimum feedback-rating source and retained leave/audit metadata required by the reconciled direction; no manual KPI totals, duplicate performance records, or destructive history operation is permitted. Exact column names, retention, correction versioning, and migration order must be recorded with the implementation migration and remain `NEEDS_DECISION` where not specified.

Implementation direction update (2026-09-25; user-provided, not client approval): migration `20260925000200_AddCoachManagementDirection` adds retained leave status/actor/timestamp metadata to `coach_unavailability` and creates `coach_feedback` with branch, Coach, Player, completed-session, 1–5 rating, approval, creator, timestamps, and soft-delete fields. It is forward-only and must be reviewed before application. Existing `user_branches`, `sessions`, and `performance_records` remain the source tables; no performance values are copied into KPI totals.

Coach availability policy direction (2026-09-25; user-provided, not client approval): migration `20260925000300_AddCoachAvailability.php` adds the forward-only `coach_availability` table with Coach, branch, nullable weekday/date selector, start/end time, reason, creator/timestamps, and cancellation timestamp. Foreign keys preserve branch and Coach ownership. Active rows are retained and overlap-checked by the service; cancellation never deletes history. The configured application timezone governs date/time interpretation. No availability migration is applied automatically by this direction.

Coach leave approval direction (2026-09-25; user-provided, not client approval): migration `20260925000400_AddCoachLeaveApproval.php` adds forward-only `status_changed_by`, `status_changed_at`, `status_reason`, and `cancelled_by` metadata to the existing `coach_unavailability` table. Existing rows are preserved. Leave status values are `PENDING`, `APPROVED`, `REJECTED`, and `CANCELLED`; approved rows remain the only rows used by scheduling conflict checks. No leave row is hard-deleted and no attendance row is changed.


Performance Improvement Index direction (PII-v1; user-provided, not client approval): migration `20260925000500_AddPerformanceApprovalMarkers.php` adds nullable approval metadata and an `approved` marker to `performance_records`. The marker defaults to zero so existing records remain excluded until explicitly approved. Baseline/latest ordering uses existing `recorded_on` plus ID; no duplicate assessment table or stored PII total is introduced. The migration is forward-only and preserves all existing rows.

## Documents secure-default direction (2026-09-27; user-provided direction — not client approval)

The current `documents` and `document_history` tables remain unchanged by this direction. Sensitive objects continue to use the private storage abstraction; no public path or storage key is introduced.

The intended future schema behavior is:

- verification state values `PENDING`, `VERIFIED`, and `REJECTED`, with verifier identity, timestamp, and reason metadata;
- expiry metadata that prevents expired documents from normal use;
- immutable replacement versions, with the prior version retired and retained for audit;
- retired rows and their audit/history remain queryable only through authorized audit paths and are not downloadable;
- scan/quarantine state and scan outcome metadata before verification/download;
- consent/legal-basis and relationship metadata only if the approved Parent/Guardian, Player, medical, and sensitive-document policies require it.

No migration, column, permission grant, retention job, scanner provider, encryption setting, or data change is authorized by this entry. Exact fields, indexes, foreign keys, retention periods, physical deletion rules, and deployment storage settings remain `NEEDS_DECISION` pending client reconciliation and deployment-owner evidence.

Implementation evidence (2026-09-27; user-provided direction — not client approval): migration `20260927000100_AddDocumentPolicyFoundation.php` adds nullable verification actor/time/reason fields, scanner status/time/reason fields, and `version_number`/`previous_version_id` to `documents`. New uploads start `PENDING` and `QUARANTINED`; replacement inserts a new version and retires the prior row without deleting its storage or history. No migration has been applied automatically, no role grants were changed, and deployment-specific scanner, encryption, retention, and storage settings remain unresolved evidence requirements.

## Portal account foundation (user-provided direction — not client approval)

Migration `20261002000100_CreatePortalAccounts` adds forward-only `portal_accounts` records with account type, unique identifier, password hash, Player or guardian contact link, enabled state, failed-attempt lockout, and session version metadata. Foreign keys preserve Player and contact-person lineage. The migration is not applied automatically. Activation, reset-token delivery, consent, and retention details remain NEEDS_DECISION.

Portal mobile OTP direction (2026-10-02; user-provided direction — not client approval): migration `20261002000200_AddPortalOtpAuthentication` adds nullable `persons.mobile_verified_at` and forward-only `portal_otp_challenges` storage for account type, mobile/session hashes, OTP hash, expiry, attempts, request/consumed timestamps, purpose, and provider status. OTP plaintext, mobile values, provider credentials, and tokens are not stored. The migration is not applied automatically.

Portal development OTP display direction (2026-10-02; user-provided direction — not client approval): `PORTAL_OTP_DEBUG` defaults to false in `.env.example`. No plaintext OTP column or debug table is added; `portal_otp_challenges.otp_hash` remains the only persisted OTP representation. The temporary display is gated by environment, exact test database, and flag checks in application code. No migration is authorized by this direction.
### Tournament fixture workflow — user-provided direction

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL (2026-10-03).** Existing `tournament_fixtures` storage is extended only by the forward-only fixture workflow migration with a nullable `round` field and normalized scheduled status. Existing `scheduled_at` remains the UTC storage column; forms submit separate MCC-local date and time values and the service performs conversion. `match_id` remains the result reference. No scorecard, innings, registration, selection, notification, export, or net-run-rate tables are added.

## Match Player Performance direction (2026-10-03)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** Migration `20261003000300_CreateMatchPlayerPerformances` adds branch, Match, Player, innings grouping, batting, bowling, correction, actor, and timestamp fields. It is forward-only and must not be applied automatically. No existing performance rows are rewritten and no scorecard or event tables are introduced. The migration depends on existing Branch, User, Player, and Match tables.

## Match Player Fielding Details direction (2026-10-03)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** Migration `20261003000400_AddMatchPlayerFieldingDetails` adds nullable fielding status, numeric contribution fields, position, and notes to `match_player_performances`. It is forward-only and must be applied only through the normal migration process. Existing Match Player Performance rows remain valid with null fielding values.

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

## Opponent catalog direction (2026-10-05)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** A forward-only migration adds branch-scoped `opponents` records and nullable `matches.opponent_id`; existing free-text Match opponent values are retained and backfilled where possible.
## Achievements schema gate (2026-10-06)

No Achievement workflow is client-approved. The bounded catalog schema is recorded as user direction; recipient relationships, source references, lifecycle, audit/history extensions, certificates, portal visibility, retention, reporting, and notification storage remain `NEEDS_DECISION`. No additional tables, columns, indexes, foreign keys, seed data, or migrations are authorized beyond the bounded catalog migration. Any future schema must be forward-only, branch-safe, auditable, and derived from recorded decisions in `docs/ACHIEVEMENTS-IMPLEMENTATION-MATRIX.md`.

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL (2026-10-06):** Future schema planning defaults to one owning branch, explicit recipient/source references, transactional duplicate prevention, append-only history, private certificate metadata, logical archival, and no physical deletion. No migration or table is authorized by this direction.

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL (2026-10-06):** Forward-only migration `20261006000100_CreateAchievementCatalog` adds only `achievements` catalog fields: branch, category, title, description, active state, creator, and timestamps, with branch/title uniqueness and branch/creator foreign keys. No award recipient, source, approval, certificate, portal, report, notification, or retention columns are added.

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL (2026-10-06):** Migration `20261006000200_CreateAchievementAwardDrafts` adds the draft-only award structure with Achievement, branch, recipient type, exactly one Player/Coach foreign key, `draft` status, bounded nomination note, creator/timestamps, uniqueness protection, and audit linkage. The migration is forward-only and has not been applied in this managed environment. Published lifecycle, approval, certificate, portal, report, notification, and retention schema remain unresolved.

Implementation evidence: `20261006000200_CreateAchievementAwardDrafts.php` adds only the documented draft award fields, indexes, foreign keys, and `achievement.update` mapping. It does not alter existing catalog rows.
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

A3-02 portal visibility, A3-03 reports/exports, A3-04 notifications, A3-05 retention/archival, and A3-06 automatic eligibility remain NEEDS_DECISION and are not implemented.

### Achievement A3-03 database note (2026-10-06)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** The report reads existing `achievement_awards`, `achievements`, `players`, `users`, `persons`, `branches`, and branch-assignment data. It filters published records and matching branch relationships. No migration or new table is required.
### Achievement schema reconciliation (2026-10-07)

`20261007000200_AddAchievementArchival` is the canonical archival migration. The forward-only `20261007000300_ReconcileAchievementSchemaDrift` migration reconciles only the existing `achievement_awards` table. It inspects the live schema before adding the two archival indexes `(branch_id, is_archived, status)` and `(is_archived, archived_at)`, and the missing `archived_by` and `restored_by` foreign keys to `users`.

The reconciliation does not create, rename, drop, or alter columns; it does not create alternate Achievement or notification tables; and it does not rewrite migration history. It is safe to run repeatedly and preserves existing Achievement data and the duplicate notification migration history row for later non-destructive review.

### Achievement A3-06 eligibility schema (2026-10-07)

The forward-only `20261007000400_AddAchievementEligibilityAutomation` migration adds `achievement_eligibility_evaluations` and nullable eligibility metadata on `achievement_awards`. Evaluation rows preserve rule version, source event, branch, candidate, attempt/retry, status, failure reason, actor, and evaluation time. Existing award nomination notes remain independent and automatic evaluations always create `draft` awards only.


## Director Dashboard secure-default direction (2026-10-11)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** DASH-01 through DASH-20 use branch-scoped access with an explicit authorized “All Branches” option for Super Admin only; MCC-local date/month inputs with UTC conversion for stored timestamps; active, authorized source records; no sensitive medical, document, guardian, or private contact data; no invented KPI or performance formulas outside approved existing metrics; clear safe empty states; no caching until freshness and invalidation rules are defined; existing centralized permissions and branch authorization; exports inheriting dashboard filters and authorization; and audit limited to sensitive administrative actions without private data. Exact numeric formulas remain unresolved and every DASH item remains NEEDS_DECISION. No implementation, permission grant, migration, cache, export, or UI change is authorized by this direction.

## Individual Session Skill taxonomy direction (2026-10-11)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** No skill or option tables, fields, link tables, retention columns, or media relationships may be added from SKILL-01 through SKILL-13. All migration impacts remain unresolved and all items remain `NEEDS_DECISION`.
## Match-Tournament ownership database direction (2026-10-11)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** Keep standalone Match rows
valid and retain the planning relationship from a Tournament Fixture to at most
one Match. A Match may link to only one Tournament Fixture. Fixture scheduling
and status remain distinct from Match cricket result and completion details.

Standings points are planned to derive only from a completed, verified linked
Match. Scheduled, postponed, cancelled, and incomplete Fixtures contribute no
points; postponed Fixtures may be rescheduled with audit history and cancelled
Fixtures remain retained. Corrections require authorization, reason,
optimistic version checking, before/after audit metadata, and a transaction.

No schema constraint, ownership column, uniqueness index, version field,
backfill, data repair, or migration is authorized by this direction. Existing
nullable linkage semantics remain unchanged until the decisions are formally
approved. Tournament, Fixture, Match, Ground, Opponent, and Player references
must remain within the authorized branch. Every item remains `NEEDS_DECISION`;
approver, approval date, and effective date are blank.
## Admission PDF fields

USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL. Migration
`20261011000300_AddCompleteAdmissionFormFields` is forward-only and idempotent.
It adds nullable intake, plan, address, fee, hostel, declaration, signature,
and academic date fields plus an optional branch-validated Coach foreign key.
It does not delete, rewrite, or backfill existing Admission data.

## Admission Follow-ups (2026-10-16)

Migration `20261016000200_CreateAdmissionFollowups` adds the forward-only
`admission_followups` table. It stores one row per conversation/action with
Admission and branch ownership, follow-up and next-follow-up datetimes,
controlled type/status values, notes/outcome, optional assignee, creator,
updater, and timestamps. Admission, branch, user foreign keys and indexes for
Admission, branch/status, follow-up date, and next-follow-up date preserve
referential integrity and query scope. Existing Admission rows are unchanged;
follow-up history is never hard-deleted.
