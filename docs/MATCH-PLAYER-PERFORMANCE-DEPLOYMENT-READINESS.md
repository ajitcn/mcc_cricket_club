# Match Player Performance Version Migration Deployment Readiness

**Migration:** `20261011000400_AddMatchPlayerPerformanceVersion`  
**Review date:** 2026-10-11  
**Direction:** `USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL`  
**Production change performed:** No

## Deployment decision

This document prepares deployment-owner approval. It does not authorize or
execute a production migration. Match ↔ Tournament ownership and standings
decisions remain `NEEDS_DECISION`.

## Migration scope

The migration adds one unsigned integer `version` column to
`match_player_performances`:

- Existing records receive the database default version `1`.
- New records receive version `1`.
- Corrections use the existing primary-key lookup and submitted version for
  compare-and-swap behavior.
- Successful corrections increment the record version atomically.
- No existing performance values, audit rows, or relationships are rewritten.

The migration is forward-only and idempotent. Its `up()` method adds the column
only when the table exists and the column is absent. Its `down()` method does
not drop the column or remove data. Migration history is not rewritten.

## Index and constraint impact

No new index is required. The correction update uses the existing primary key
with a version predicate, so no standalone version index or new foreign key is
introduced. Existing primary keys, foreign keys, unique keys, branch
relationships, and append-oriented audit behavior are unchanged.

## Runtime, locking, and operational considerations

Adding a column may require a metadata or table lock depending on the deployed
MySQL/MariaDB version, table size, storage engine, and online-DDL settings.
The deployment owner must confirm the expected behavior for the target server.

Before execution:

- Confirm the target is the intended production database and environment.
- Take and validate a restorable database backup.
- Confirm a migration window with low Match Player Performance write volume.
- Confirm an operator, application owner, and database monitor are available.
- Record the migration command and resulting migration-history row.

During execution, monitor migration duration, metadata-lock waits, database
connections, error logs, application error rates, and Match Player Performance
write latency. Stop and escalate if locking or runtime exceeds the approved
window. Do not force-kill a transaction without database-owner guidance.

## Test-database evidence

The migration was verified in the test environment only:

- Database: `mcc_cricket_club_test`.
- Migration: `20261011000400_AddMatchPlayerPerformanceVersion`.
- Migration batch: 56.
- Focused verification: **23 tests, 276 assertions, exit code 0**.
- PHP lint passed for the service, controller, migration, performance view, and
  focused Unit, Feature, and database test files.
- Evidence report:
  `docs/MATCH-PLAYER-PERFORMANCE-CORRECTION-COMPLETION-REPORT.md`.

No production migration or production-data change was performed as part of
this readiness review.

## Pre-deployment checklist

- [ ] Backup completed and restoration has been validated or is covered by the
  approved recovery procedure.
- [ ] Production database identity, schema, and migration-history state
  confirmed read-only.
- [ ] Migration lock/runtime estimate approved by the database owner.
- [ ] Deployment window, operator, monitor, and escalation contact confirmed.
- [ ] Application release compatibility checked.
- [ ] No unrelated migrations are included in the deployment command.

## Application compatibility checks

Before production use, confirm that the deployed application:

- Reads existing rows with version `1` after migration.
- Submits the current version with correction forms.
- Requires a correction reason for existing-record changes.
- Rejects stale submissions without overwriting current data.
- Displays the safe reload message for stale forms.
- Preserves existing permissions, branch isolation, CSRF protection,
  transactions, audit metadata, rollback behavior, and completed-Match
  restrictions.
- Does not require Tournament, Fixture, standings, scorecard, innings, export,
  notification, portal, or new permission behavior.

## Post-deployment verification

After the approved migration window, perform read-only checks:

1. Confirm the target database and migration-history row for
   `20261011000400_AddMatchPlayerPerformanceVersion`.
2. Confirm `match_player_performances.version` exists with the expected
   unsigned integer type and default `1`.
3. Confirm existing performance rows are present and have version `1` unless
   an authorized correction has already incremented them.
4. Confirm no unexpected indexes, foreign keys, or column changes were added.
5. Run the approved application smoke checks for performance read, first-time
   save, correction, stale rejection, audit, branch isolation, and completed-
   Match behavior.
6. Review database and application logs for lock waits, errors, and failed
   writes.

## Failure handling

Do not roll back by dropping the column, deleting migration history, deleting
performance records, truncating tables, disabling foreign keys, or running
destructive cleanup. If execution fails:

- Preserve the database and migration-history state for diagnosis.
- Capture the exact database error, lock information, and migration output.
- Confirm whether the column was added and whether the migration-history row
  was recorded.
- Escalate to the deployment owner and database owner.
- Use a forward-only corrective migration or approved operational repair after
  impact analysis; do not rewrite migration history.

## Deployment-owner approval

| Field | Value |
| --- | --- |
| Name |  |
| Approval date |  |
| Planned deployment date |  |
| Environment |  |
| Backup confirmation |  |
| Monitoring owner |  |
| Post-deployment verification owner |  |
| Comments |  |
| Approval status |  |

Approval remains pending because no deployment-owner name, status, dates,
target environment, backup confirmation, monitoring owner, verification owner,
or comments were supplied. Select exactly one status when the owner reviews
the change: `Approved`, `Rejected`, or `Deferred`.

Until `Approved` is recorded, the migration must not be applied to production
or any other unapproved environment, and no production data may be modified.

If the owner records `Approved`, the approval confirms that:

- The forward-only migration may be applied in the approved environment.
- A restorable backup exists before deployment.
- Migration status will be checked before and after deployment.
- The schema version column and existing/default version values will be
  verified.
- Match Player Performance correction/version tests will be rerun.
- No destructive rollback or migration-history rewrite will be performed.
