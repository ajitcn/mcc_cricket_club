# Scheduling/Sessions One-Time Verification Report

**Verification date:** 2026-10-07  
**Database target:** `mcc_cricket_club_test`  
**Scope:** SCH-01 through SCH-05 and OPS-09  
**Verification status:** Complete for the approved bounded one-time scope

## Exact test result

The requested command was run exactly:

```text
php vendor/bin/phpunit tests/Unit/SessionManagementContractTest.php tests/database/SchedulingSafeWorkflowTest.php tests/database/SessionManagementRegressionTest.php --no-progress
```

Final result:

```text
OK (40 tests, 482 assertions)
```

No application behavior, migration, production data, or `docs/SRS-original.md` was modified. The repair was limited to insert-only test fixtures and stale test expectations.

## Issue classification and repair

### Missing or invalid synthetic fixture — 16 errors

All 16 errors reported `Player is not eligible.` because the prior shared seeded Player did not have an active `player_batch_assignments` row for the selected Batch. The affected tests were:

- `testAuthorizedCreationPersistsUtcBranchRosterAndAudit`
- `testCompletedSessionRequiresSnapshotAndAttendanceAndIsIdempotent`
- `testCompletionRetainsIncompleteAndCancelledGuards`
- `testCreateOutsideAvailabilityIsAllowedWhenLeaveDoesNotOverlap`
- `testCancellationAndReschedulePreserveOriginalAndRosterHistory`
- `testAuthorizedSessionHistoryShowsCancellationAuditDetailsOnly`
- `testAuthorizedSessionHistoryShowsRescheduleReplacementLinkage`
- `testSessionHistoryIsDeniedAcrossBranches`
- `testConflictAvailabilityAndInvalidWindowAreRejectedWithoutPartialWrites`
- `testCoachAvailabilityIsEnforcedForCreateEditAndReschedule`
- `testSessionCanBeEditedBeforeAttendanceSnapshotCapture`
- `testEditAcceptsIndiaLocalDateAndTimeAndReturnsLocalDisplay`
- `testEditAcceptsDatepickerMinutePrecisionPayload`
- `testSessionEditIsRejectedAfterAttendanceSnapshotCapture`
- `testIndiaLocalDatetimePersistsUtcAndBranchIsolationProtectsDetail`
- `testCoachViewCanReadSessionDetailWithoutWriteActions`

The Scheduling safe suite now creates a unique active Player, Person, branch history row, and active Batch assignment using insert-only writes. The regression suite adds the missing assignment only when absent, also without cleanup or deletion.

### Stale test expectation — 2 failures

- `testSessionEditWorkflowUsesExistingPermissionAndModalPattern` expected the prior branch option expression. It now checks the current `old('branch_id', $session['branch_id'])` expression.
- `testSessionDetailsExposeAttendanceFirstFeedbackForEveryRosterPlayer` expected “feedback” labels and wording. It now checks the current “remark” and “Attendance required” UI text.

### Missing or invalid synthetic fixture — 3 failures

- `testCreateBrowserRequestPersistsAndReturnsToSessionDetails`
- `testCreateBrowserRequestAllowsCrossDaySessionWhenLeaveDoesNotOverlap`
- `testSessionFormOptionsExposeAuthorizedActivePlayersAsPlayerIds`

These failures had the same missing active Player/Batch assignment cause and were resolved by the unique insert-only fixture.

### Migration/history mismatch — none causal

The pre-existing migration count is 66 available migrations and 67 applied history rows. It did not cause any Scheduling test failure and was not changed.

### Confirmed application defect — none

After fixture repair and stale expectation updates, the exact requested suites pass. No service, controller, view, route, or schema fix was required.

## Current implementation evidence

- `app/Services/SchedulingService.php` implements one-time session creation, editing, cancellation, completion, rescheduling, resource conflict locking, branch/resource validation, roster snapshots, audit events, and timezone conversion.
- `app/Modules/Scheduling/` contains the canonical Browser routes, create/edit/list/detail views, and session models.
- `SessionManagementContractTest` covers one-time policy, deterministic locks, permissions, edit UI contracts, session detail behavior, and authorized selectors.
- `SchedulingSafeWorkflowTest` covers MCC-local/UTC conversion, branch/resource authorization, roster and completion rules, browser create/edit flows, cancellation/rescheduling, branch history access, conflict rejection, audit, CSRF, idempotency, and UI behavior.
- `SessionManagementRegressionTest` covers Player overlap and non-Coach assignment rejection.

## Acceptance criteria

- MCC-local date/time round-trips to persisted UTC and displays correctly in local time.
- Branch, Coach, Ground, Batch, and Player relationships are revalidated server-side.
- Unauthorized and cross-branch requests are denied.
- Roster snapshots are created once and remain immutable after attendance capture.
- Attendance boundaries and completion prerequisites are enforced.
- Cancellation preserves history and requires the existing reason/audit flow.
- Linked rescheduling preserves original-session lineage and roster history.
- Invalid windows and resource conflicts fail without partial writes.
- Existing POST actions remain CSRF-protected and permission-scoped.
- Repeated completion/rescheduling operations remain idempotent where defined.
- Actor, branch, transition, reason, and lineage audit metadata is preserved without sensitive leakage.
- Session Details and create/edit UI behavior match the approved one-time workflow.
- Focused Unit and insert-only database tests pass.

## Migration and deployment evidence

Read-only migration status was checked with:

```text
php bin/migrate.php status --group=tests
```

Evidence:

- Database: `mcc_cricket_club_test`
- Available migrations: 66
- Applied history rows: 67
- `20260829000500_CreateSchedulingTablesDeployment` is applied.
- `20260902000100_CreateAttendanceRosterSnapshotsDeployment` is applied.
- `20260915000200_AddSessionViewPermission` is applied.
- Coach availability and leave migrations are applied.
- No migration was added, run, rolled back, or modified.
- The pre-existing 66/67 migration-count mismatch remains unchanged.

Repository tests do not establish deployed HTTPS termination, PHP SAPI/session configuration, timezone configuration, database concurrency behavior, or production log handling. Those require deployment-owner evidence before release.

## Security review

The passing suites verify server-side branch ownership, authorized Coach/Ground/Batch/Player relationships, permissions, CSRF, transaction rollback, deterministic resource locking, roster snapshot immutability, audit history, idempotency, and safe cross-branch responses. The test fixture repair does not weaken any authorization or eligibility checks.

## Explicitly deferred policies

- Recurring sessions remain deferred.
- Capacity expansion remains deferred.
- No new resource-conflict override, alert, recipient, attendance denominator, or other unresolved scheduling policy was implemented.
- No new permissions, migration, relationship rule, portal behavior, or external delivery was added.

`docs/SRS-original.md` was not present in the workspace, so its unchanged state could not be independently hashed. No operation in this task created, edited, or deleted that path.

## Final verification addendum

**Verification result:** 44 tests, 551 assertions, exit code 0; 2 tests skipped.

The final Scheduling verification confirmed:

- Successful browser create/update redirect to `/sessions/{id}/page`.
- Valid cross-day sessions are accepted when the session window is valid.
- Ineligible Players return the specific `Player is not eligible.` validation message.
- Transaction rollback prevents partial session, roster, and audit writes after validation or conflict failures.
- MCC-local date/time input continues to persist as UTC and display in the configured local timezone.
- Optional schema-aware fields remain compatible when their supporting schema is available.

The two skipped tests were environment or fixture dependent and did not change the passing result. No migration was modified or rerun, and no database cleanup, truncation, rollback, or foreign-key disabling was performed.
