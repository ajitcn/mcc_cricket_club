# Attendance → Coach Feedback Evidence Review

**Review date:** 2026-10-11  
**Mode:** Source, route, view, migration, schema, and focused-test review

The archival migration was made idempotent and applied forward-only to the
testing database. No migration history was rewritten, no data was deleted,
and `docs/SRS-original.md` was not changed.

## Flow classification

| Flow | Classification | Evidence and remaining gap |
| --- | --- | --- |
| Attendance recorded → eligible Player/session row | **Verified** | `Attendance::sessionPage()` loads the persisted roster through `AttendanceService::roster()`. `CoachManagementService::sessionFeedback()` joins immutable snapshot members to the session, Player, attendance and feedback rows. The projection is branch-scoped and returns one row per snapshot member. |
| Add feedback from Session Details | **Verified** | Scheduling Browser loads `session_feedback` and `can_feedback_update`; `app/Modules/Scheduling/Views/show.php` renders the Feedback / remark column and shared modal trigger. The action is shown only after attendance exists, for scheduled, active, or completed sessions, and with branch-scoped `coach.update`. |
| Add/edit feedback from Attendance Session | **Verified** | Attendance Browser passes `session_feedback` and branch-scoped `can_feedback_update` to `app/Modules/Attendance/Views/session.php`. The same attendance-first row states and modal pattern are rendered. |
| Feedback POST and safe return | **Verified** | `POST /coaches/(:num)/feedback` is permission-filtered. `Coaches\\Controllers\\Browser::feedback()` validates internal `return_to` targets for Session Details, Attendance Session, or Coach Profile, preserves input/errors on failure, and returns an explicit success target. |
| Feedback persistence and audit | **Verified** | `CoachManagementService::saveFeedback()` validates session status, branch, Coach authorization, session membership, immutable snapshot membership and recorded attendance; writes feedback and `auditPersistence` inside a transaction. `coach_feedback.version` is now checked and incremented atomically for corrections. Database evidence verifies actor, branch, reason, before/after summaries, version metadata and correction history. |
| Correction after feedback | **Verified** | Existing feedback edits require the existing feedback record, `coach.update`, a non-empty reason, and the submitted expected version. Stale updates are rejected safely; successful corrections increment the version and retain append-only audit metadata. |
| Cancelled Session handling | **Verified** | `saveFeedback()` restricts sessions to `scheduled`, `active`, or `completed`; cancelled sessions are rejected with `Feedback is not available for this session.` |

**Overall classification: `Verified` for the bounded implementation.** The
attendance-first, branch, snapshot, permission, CSRF, transaction, audit,
state, redirect, and feedback-specific optimistic concurrency controls are
implemented and covered by the focused contract and safe database tests.

## Route and controller evidence

- Attendance session page: `GET /attendance/sessions/(:num)` →
  `App\\Modules\\Attendance\\Controllers\\Attendance::sessionPage`.
- Session Details page: Scheduling Browser session detail route →
  `SchedulingService`/attendance roster projection.
- Feedback state-changing route: `POST /coaches/(:num)/feedback` →
  `App\\Modules\\Coaches\\Controllers\\Browser::feedback` with
  `permission:coach.update`.
- The form includes `session_id`, `player_id`, `feedback_id` when editing,
  `return_to`, CSRF, rating, and reason fields. The controller rejects unsafe
  return paths and falls back to Session Details or Coach Profile.

## Business and security checks

### Attendance-first and roster membership

`saveFeedback()` requires a session in `scheduled`, `active`, or `completed`
status, an existing `session_players` row, an attendance roster snapshot, a
snapshot member row for the submitted Player, and an attendance row for the
same session, Player, and branch. This prevents forged Player IDs and feedback
for Players outside the immutable snapshot.

### Branch and permission isolation

The persisted session branch is used for authorization. `coach.update` is
checked against that branch, and `sessionFeedback()` returns no rows unless the
viewer has `attendance.view` or `coach.update` for the branch. Snapshot,
attendance, feedback, Player and session joins use the same branch context.

### CSRF and transactions

The browser POST is covered by the application CSRF protection. Feedback writes
and their audit event are enclosed in a database transaction; failures roll
back the write and rethrow a safe error for the controller to flash.

### Audit and sensitive data

Create and correction events include actor, action, entity, branch, before
summary, after summary, timestamp, and reason. The audit projection used by
the session pages exposes only a readable reason/remark preview. No raw JSON,
credentials, document paths, or private contact data are selected by the
feedback projection. A feedback-specific reason is free text and is stored in
audit metadata; there is no separate redaction test proving that users cannot
enter sensitive text, so this remains a privacy evidence limitation.

## Existing test evidence

The final bounded nine-file verification passed with 101 tests and 906
assertions, exit code 0. It includes the modernized integration fixture:

- `tests/Unit/CoachManagementModuleContractTest.php`
- `tests/Feature/CoachManagementRoutesTest.php`
- `tests/database/CoachManagementDirectionSafeTest.php`
- `tests/database/CoachManagementSafeTest.php`
- `tests/Unit/AttendanceModuleContractTest.php`
- `tests/Feature/AttendanceRoutesTest.php`
- `tests/database/AttendanceIntegrationTest.php`
- `tests/database/AttendanceSafeWorkflowTest.php`
- `tests/Unit/AchievementArchivalContractTest.php`

The verification covers Coach feedback version propagation, stale or missing
correction-version rejection, atomic correction increments, idempotent first
submission, attendance eligibility, branch isolation, permissions, CSRF,
transactions, and audit metadata. `AttendanceIntegrationTest` now uses
insert-only unique fixtures and passes with 28 tests and 152 assertions.
Append-only roster triggers remain enabled; no attendance data was deleted or
truncated.

PHP lint passed for `app/Modules/Attendance/Models/AttendanceModel.php` and
`tests/database/AttendanceIntegrationTest.php`. The narrow filter-options alias
correction is covered by the successful integration route assertions. No
additional application, migration, trigger, schema, or production-data changes
were made during final verification, and `docs/SRS-original.md` is unchanged.

## Gaps and required follow-up

| Gap | Classification | Required evidence or decision | Migration impact |
| --- | --- | --- | --- |
| Legacy Attendance integration fixture | **Verified** | Insert-only unique fixtures pass within the final nine-file suite; append-only roster triggers remain enabled and no attendance records are deleted | None |
| Free-text correction reason privacy/redaction boundary | **NEEDS_DECISION** if stronger redaction or length policy is required | Decide whether the existing audit reason policy is sufficient before changing it | Unknown |

## Excluded scope

This review does not authorize changes to attendance statuses, session
completion rules, feedback fields, Coach KPIs, media, portal visibility,
notifications, retention, reports, or database schema. No code or migration
should be added until the feedback version behavior and any privacy policy are
explicitly resolved.
