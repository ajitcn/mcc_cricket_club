# Migration-History Reconciliation Report

Date: 2026-10-25  
Mode: read-only  
Project: MCC Cricket Club Management System

## Explicit safety statement

This reconciliation did not modify application code, migration files, migration history, schema, database data, production data, or `docs/SRS-original.md`.

No `latest`, `rollback`, `refresh`, or other state-changing migration command was executed. The custom migration command was not used for this query because its `status` path calls `ensureTable()`, which can create the history table. History and schema were queried directly through read-only `information_schema` and `SELECT` statements.

## Database and environment verification

| Check | Result | Classification |
|---|---|---|
| `.env` readable | Yes | Verified |
| Composer autoload | `vendor/autoload.php` readable | Verified |
| CodeIgniter bootstrap | `app/Config/Paths.php`, `app/Config/Boot/development.php`, and framework bootstrap readable | Verified |
| `CI_ENVIRONMENT` | `development` | Verified |
| Active DB driver | MySQLi | Verified |
| Active database | `mcc_cricket_club_test` | Verified |
| Tests DB target | `mcc_cricket_club_test` | Verified |
| Approved target | Test database, not the production-like `mcc_cricket_club` target | Verified |
| Production writes | None performed | Verified |

Secrets, usernames, and passwords were not included in this report.

## Source migration inventory

There are 113 PHP files under `app/Database/Migrations`. Every source file parsed successfully for a namespace and migration class. All 113 use `App\\Database\\Migrations`; all 113 class names match the filename stem after the migration ID. No source namespace inconsistency and no source filename/class mismatch were found.

The complete source filename inventory is:

```text
202608280001_CreateSprintOneSchema.php
20260828000200_CreateSprintOneSchemaWrapper.php
20260828000300_CreateRecoveryRateLimitTables.php
202608290001_AddCrmLeadClosedAt.php
20260829000100_AddCrmLeadClosedAtDeployment.php
202608290002_AddApprovedAdmissionLifecycle.php
20260829000200_AddApprovedAdmissionLifecycleDeployment.php
202608290003_AddPlayerIdentityConstraint.php
20260829000300_AddPlayerIdentityConstraintDeployment.php
202608290004_CreatePlayerAssignmentTables.php
20260829000400_CreatePlayerAssignmentTablesDeployment.php
202608290005_CreateSchedulingTables.php
20260829000500_CreateSchedulingTablesDeployment.php
202608290006_CreateAttendanceTables.php
20260829000600_CreateAttendanceTablesDeployment.php
20260830000100_AddUniqueBranchName.php
20260830000200_AddBranchAddress.php
20260830000300_CreateLeadConfigurationTables.php
20260830000400_CreateTrialTables.php
20260831000100_AddApprovedFollowUpWorkflow.php
20260831000200_AddDirectAdmissionIdentity.php
20260831000300_AddAdmissionIntakeFields.php
20260831000400_CreateAuditAppendOnlyProtection.php
20260831000500_CreatePlayerStatusHistory.php
202609020001_CreateAttendanceRosterSnapshots.php
20260902000100_CreateAttendanceRosterSnapshotsDeployment.php
20260903000100_CreateCoachProfiles.php
20260903000200_AddCoachPermissions.php
20260904000100_CreateFitnessManagement.php
20260904000200_CreateGroundManagement.php
20260904000300_CreateMatchManagement.php
20260904000400_CreateTournamentManagement.php
20260904000500_CreatePlayerDevelopment.php
20260904000600_CreateParentPortal.php
20260904000700_CreateDocumentManagement.php
20260904000800_AddReportPermission.php
20260904000900_CreateNotifications.php
20260904001000_AddMarketingDashboardPermission.php
20260904001100_AddPlayer360Permission.php
20260905000100_CreatePerformanceRecords.php
20260905000200_AddFitnessSoftDeletion.php
20260905000300_AddCrmLeadRemarks.php
20260907000100_AddCrmLeadCreator.php
20260908000100_AddAdmissionDocumentRelationship.php
20260908000200_AddAdmissionDocumentPermissions.php
20260914000100_RestrictAttendanceHistoryDeletion.php
20260915000100_AddDevelopmentGoalVersion.php
20260915000200_AddSessionViewPermission.php
20260921000100_CreateEquipmentInventory.php
20260921000200_AddEquipmentUpdateDelete.php
20260921000300_AddTrialBookPermission.php
20260922000100_AllowNullableTrialLocation.php
20260922000200_CreateProgramsAndLinkBatches.php
20260922000300_AddProgramBatchPermissions.php
202609250001_ReconcileCoachAttendanceScope.php
20260925000100_ReconcileCoachAttendanceScopeDeployment.php
20260925000200_AddCoachManagementDirection.php
20260925000300_AddCoachAvailability.php
20260925000400_AddCoachLeaveApproval.php
20260925000500_AddPerformanceApprovalMarkers.php
20260927000100_AddDocumentPolicyFoundation.php
20261002000100_CreatePortalAccounts.php
20261002000200_AddPortalOtpAuthentication.php
20261003000200_AddTournamentFixtureWorkflowFields.php
20261003000300_CreateMatchPlayerPerformances.php
20261003000400_AddMatchPlayerFieldingDetails.php
20261005000100_CreateOpponents.php
20261006000100_CreateAchievementCatalog.php
20261006000200_CreateAchievementAwardDrafts.php
20261006000300_AddAchievementApprovalLifecycle.php
20261006000400_CreateAchievementCertificates.php
20261007000100_CreateAchievementNotificationRecipients.php
20261007000200_AddAchievementArchival.php
20261007000300_ReconcileAchievementSchemaDrift.php
20261007000400_AddAchievementEligibilityAutomation.php
20261011000100_CreateIndividualSessionSkillCatalog.php
20261011000200_AddCoachFeedbackVersion.php
20261011000300_AddCompleteAdmissionFormFields.php
20261011000300_AddMatchTournamentLinkageVersion.php
20261011000400_AddMatchPlayerPerformanceVersion.php
20261011000500_CreateAdmissionMasterData.php
20261012000100_ExpandAdmissionSignatureFields.php
20261012000200_CreateAdmissionAgeGroups.php
20261012000300_MakeBatchesGlobal.php
20261013000100_AddAdmissionCoachingSelections.php
20261013000200_AddAdmissionEliteCampDetails.php
20261013000300_CreateAdmissionInstallmentRows.php
20261013000400_AddAdmissionInstallmentDates.php
20261013000500_AddHostelInstallmentSchedule.php
20261013000600_CreateAdmissionPeriods.php
20261014000100_RemoveAdmissionPlanMasters.php
20261014000200_CreateSeparateAdmissionInstallmentTables.php
20261015000100_AddAdmissionCoachingTypes.php
20261015000200_AddTrialRatingsAndAdmissionRatingSnapshots.php
20261016000100_AddAdmissionPersonalCoach.php
20261016000200_CreateAdmissionFollowups.php
20261016000300_AddAdmissionNumberUniqueness.php
20261016000400_BackfillAdmissionNumbers.php
20261016000500_ConvertAdmissionNumbersToMccSeries.php
20261018000100_AddAdmissionRenewalSelectionSnapshot.php
20261018000200_CreateAdmissionInstallmentPayments.php
20261018000300_CreateTrialMedia.php
20261019000100_AddTrialSecondaryCoach.php
20261019000200_CreateAdmissionTestWorkflow.php
20261019000300_AddAdmissionTestAssessmentHistory.php
20261019000400_AddMeetingFollowUpScheduleFields.php
20261021000100_AddAdmissionTestFees.php
20261021000200_AddAdmissionTestAssessmentRemarks.php
20261021000300_AddAdmissionTaxInformation.php
20261022000100_AddProgramDescription.php
20261022000200_GrantCoachProgramPermissions.php
20261022000300_AllowNullableSessionBatch.php
20261022000400_ReconcileSessionSkillCatalog.php
```

### Source IDs, classes, groups, and destructive behavior

The source migration ID is the filename prefix before the first underscore. CodeIgniter discovery exposes the source files to the configured migration runner; the files themselves do not declare a database group. The observed history groups are `tests` and, for two rows, `default`.

Source findings:

- Duplicate source ID: `20261011000300` is used by both `AddCompleteAdmissionFormFields` and `AddMatchTournamentLinkageVersion`.
- Duplicate source class names: none.
- Namespace inconsistencies in source: none.
- Filename/class mismatches: none.
- Same logical schema change pairs: nine old-format implementation files have timestamped deployment/wrapper counterparts: Sprint One, CRM lead closed date, approved admission lifecycle, player identity constraint, player assignment tables, scheduling tables, attendance tables, attendance roster snapshots, and coach attendance scope reconciliation.
- All 113 migrations define `down()`.
- 84 `down()` methods are explicitly forward-only or retain history/data.
- 13 `down()` methods contain potentially destructive operations such as dropping tables/columns or deleting schema objects. They were inspected only; none was executed.

The 13 potentially destructive `down()` sources are:

```text
202608280001_CreateSprintOneSchema.php
20260828000300_CreateRecoveryRateLimitTables.php
202608290001_AddCrmLeadClosedAt.php
202608290002_AddApprovedAdmissionLifecycle.php
202608290004_CreatePlayerAssignmentTables.php
202608290005_CreateSchedulingTables.php
202608290006_CreateAttendanceTables.php
20260830000100_AddUniqueBranchName.php
20260830000200_AddBranchAddress.php
20260830000300_CreateLeadConfigurationTables.php
20260903000100_CreateCoachProfiles.php
20260915000100_AddDevelopmentGoalVersion.php
20261011000100_CreateIndividualSessionSkillCatalog.php
```

Classification: **Verified** for the inventory; **Needs approval** before any rollback involving a destructive `down()`.

## Applied migration history inventory

The `migrations` table contains 107 rows. The following is the complete applied history inventory, ordered by migration ID and history row order. `group` is the database migration group and `batch` is the recorded batch.

```text
20260828000200 | App\Database\Migrations\CreateSprintOneSchemaWrapper | tests | 1
20260828000300 | App\Database\Migrations\CreateRecoveryRateLimitTables | tests | 1
20260829000100 | App\Database\Migrations\AddCrmLeadClosedAtDeployment | tests | 1
20260829000200 | App\Database\Migrations\AddApprovedAdmissionLifecycleDeployment | tests | 1
20260829000300 | App\Database\Migrations\AddPlayerIdentityConstraintDeployment | tests | 1
20260829000400 | App\Database\Migrations\CreatePlayerAssignmentTablesDeployment | tests | 1
20260829000500 | App\Database\Migrations\CreateSchedulingTablesDeployment | tests | 1
20260829000600 | App\Database\Migrations\CreateAttendanceTablesDeployment | tests | 1
20260830000100 | App\Database\Migrations\AddUniqueBranchName | tests | 2
20260830000200 | App\Database\Migrations\AddBranchAddress | tests | 3
20260830000300 | App\Database\Migrations\CreateLeadConfigurationTables | tests | 4
20260830000400 | App\Database\Migrations\CreateTrialTables | tests | 5
20260831000100 | App\Database\Migrations\AddApprovedFollowUpWorkflow | tests | 6
20260831000200 | App\Database\Migrations\AddDirectAdmissionIdentity | tests | 7
20260831000300 | App\Database\Migrations\AddAdmissionIntakeFields | tests | 8
20260831000400 | App\Database\Migrations\CreateAuditAppendOnlyProtection | tests | 9
20260831000500 | App\Database\Migrations\CreatePlayerStatusHistory | tests | 9
20260902000100 | App\Database\Migrations\CreateAttendanceRosterSnapshotsDeployment | tests | 10
20260903000100 | App\Database\Migrations\CreateCoachProfiles | tests | 11
20260903000200 | App\Database\Migrations\AddCoachPermissions | tests | 11
20260904000100 | App\Database\Migrations\CreateFitnessManagement | tests | 12
20260904000200 | App\Database\Migrations\CreateGroundManagement | tests | 13
20260904000300 | App\Database\Migrations\CreateMatchManagement | tests | 14
20260904000400 | App\Database\Migrations\CreateTournamentManagement | tests | 15
20260904000500 | App\Database\Migrations\CreatePlayerDevelopment | tests | 16
20260904000600 | App\Database\Migrations\CreateParentPortal | tests | 17
20260904000700 | App\Database\Migrations\CreateDocumentManagement | tests | 18
20260904000800 | App\Database\Migrations\AddReportPermission | tests | 19
20260904000900 | App\Database\Migrations\CreateNotifications | tests | 20
20260904001000 | App\Database\Migrations\AddMarketingDashboardPermission | tests | 21
20260904001100 | App\Database\Migrations\AddPlayer360Permission | tests | 22
20260905000100 | App\Database\Migrations\CreatePerformanceRecords | tests | 23
20260905000200 | App\Database\Migrations\AddFitnessSoftDeletion | tests | 24
20260905000300 | App\Database\Migrations\AddCrmLeadRemarks | tests | 25
20260907000100 | App\Database\Migrations\AddCrmLeadCreator | tests | 26
20260908000100 | App\Database\Migrations\AddAdmissionDocumentRelationship | tests | 27
20260908000200 | App\Database\Migrations\AddAdmissionDocumentPermissions | tests | 28
20260914000100 | App\Database\Migrations\RestrictAttendanceHistoryDeletion | tests | 29
20260915000100 | App\Database\Migrations\AddDevelopmentGoalVersion | tests | 30
20260915000200 | App\Database\Migrations\AddSessionViewPermission | tests | 30
20260921000100 | App\Database\Migrations\CreateEquipmentInventory | tests | 31
20260921000200 | App\Database\Migrations\AddEquipmentUpdateDelete | tests | 32
20260921000300 | App\Database\Migrations\AddTrialBookPermission | tests | 33
20260922000100 | App\Database\Migrations\AllowNullableTrialLocation | tests | 33
20260922000200 | App\Database\Migrations\CreateProgramsAndLinkBatches | tests | 33
20260922000300 | App\Database\Migrations\AddProgramBatchPermissions | tests | 33
20260925000100 | App\Database\Migrations\ReconcileCoachAttendanceScopeDeployment | tests | 34
20260925000200 | App\Database\Migrations\AddCoachManagementDirection | tests | 35
20260925000300 | App\Database\Migrations\AddCoachAvailability | tests | 36
20260925000400 | App\Database\Migrations\AddCoachLeaveApproval | tests | 37
20260925000500 | App\Database\Migrations\AddPerformanceApprovalMarkers | tests | 38
20260927000100 | App\Database\Migrations\AddDocumentPolicyFoundation | tests | 39
20261002000100 | App\Database\Migrations\CreatePortalAccounts | tests | 40
20261002000200 | App\Database\Migrations\AddPortalOtpAuthentication | tests | 40
20261003000200 | App\Database\Migrations\AddTournamentFixtureWorkflowFields | tests | 41
20261003000300 | App\Database\Migrations\CreateMatchPlayerPerformances | tests | 42
20261003000400 | App\Database\Migrations\AddMatchPlayerFieldingDetails | tests | 43
20261005000100 | App\Database\Migrations\CreateOpponents | tests | 44
20261006000100 | App\Database\Migrations\CreateAchievementCatalog | tests | 45
20261006000200 | App\Database\Migrations\CreateAchievementAwardDrafts | tests | 46
20261006000300 | App\Database\Migrations\AddAchievementApprovalLifecycle | tests | 47
20261006000400 | App\Database\Migrations\CreateAchievementCertificates | tests | 48
20261007000100 | App\Database\Migrations\CreateAchievementNotificationRecipients | default | 49
20261007000200 | App\Database\Migrations\AddAchievementArchival | default | 50
20261007000100 | App\Database\Migrations\CreateAchievementNotificationRecipients | tests | 51
20261007000300 | App\Database\Migrations\ReconcileAchievementSchemaDrift | tests | 52
20261007000400 | App\Database\Migrations\AddAchievementEligibilityAutomation | tests | 53
20261007000200 | App\Database\Migrations\AddAchievementArchival | tests | 54
20261011000100 | App\Database\Migrations\CreateIndividualSessionSkillCatalog | tests | 54
20261011000200 | App\Database\Migrations\AddCoachFeedbackVersion | tests | 54
20261011000300 | App\Database\Migrations\AddMatchTournamentLinkageVersion | tests | 55
20261011000400 | App\Database\Migrations\AddMatchPlayerPerformanceVersion | tests | 56
20261011000300 | App\Database\Migrations\AddCompleteAdmissionFormFields | tests | 57
20261011000500 | App\Database\Migrations\CreateAdmissionMasterData | tests | 58
20261012000100 | App\Database\Migrations\ExpandAdmissionSignatureFields | tests | 59
20261012000200 | App\Database\Migrations\CreateAdmissionAgeGroups | tests | 59
20261012000300 | App\Database\Migrations\MakeBatchesGlobal | tests | 60
20261013000100 | App\Database\Migrations\AddAdmissionCoachingSelections | tests | 61
20261013000200 | App\Database\Migrations\AddAdmissionEliteCampDetails | tests | 62
20261013000300 | App\Database\Migrations\CreateAdmissionInstallmentRows | tests | 62
20261013000400 | App\Database\Migrations\AddAdmissionInstallmentDates | tests | 62
20261013000500 | App\Database\Migrations\AddHostelInstallmentSchedule | tests | 62
20261013000600 | App\Database\Migrations\CreateAdmissionPeriods | tests | 62
20261014000100 | App\Database\Migrations\RemoveAdmissionPlanMasters | tests | 63
20261014000200 | App\Database\Migrations\CreateSeparateAdmissionInstallmentTables | tests | 64
20261015000100 | App\Database\Migrations\AddAdmissionCoachingTypes | tests | 65
20261015000200 | App\Database\Migrations\AddTrialRatingsAndAdmissionRatingSnapshots | tests | 66
20261016000100 | App\Database\Migrations\AddAdmissionPersonalCoach | tests | 67
20261016000200 | App\Database\Migrations\CreateAdmissionFollowups | tests | 68
20261016000300 | App\Database\Migrations\AddAdmissionNumberUniqueness | tests | 69
20261016000400 | App\Database\Migrations\BackfillAdmissionNumbers | tests | 69
20261016000500 | App\Database\Migrations\ConvertAdmissionNumbersToMccSeries | tests | 69
20261018000100 | App\Database\Migrations\AddAdmissionRenewalSelectionSnapshot | tests | 69
20261018000200 | App\Database\Migrations\CreateAdmissionInstallmentPayments | tests | 69
20261018000300 | App\Database\Migrations\CreateTrialMedia | tests | 70
20261019000100 | AppDatabaseMigrations\AddTrialSecondaryCoach | tests | 71
20261019000100 | App\Database\Migrations\AddTrialSecondaryCoach | tests | 72
20261019000200 | App\Database\Migrations\CreateAdmissionTestWorkflow | tests | 73
20261019000300 | App\Database\Migrations\AddAdmissionTestAssessmentHistory | tests | 74
20261019000400 | App\Database\Migrations\AddMeetingFollowUpScheduleFields | tests | 75
20261021000100 | App\Database\Migrations\AddAdmissionTestFees | tests | 76
20261021000200 | App\Database\Migrations\AddAdmissionTestAssessmentRemarks | tests | 77
20261021000300 | App\Database\Migrations\AddAdmissionTaxInformation | tests | 78
20261022000100 | App\Database\Migrations\AddProgramDescription | tests | 79
20261022000200 | App\Database\Migrations\GrantCoachProgramPermissions | tests | 80
20261022000300 | App\Database\Migrations\AllowNullableSessionBatch | tests | 81
20261022000400 | App\Database\Migrations\ReconcileSessionSkillCatalog | tests | 82
```

The history table has no unique constraint on `(version, group)` or `(version, class)`, so duplicate rows are physically representable.

## Source-to-history mapping

The mapping is complete using the following partition:

- 99 source files have exactly one history row matching both ID and fully qualified class.
- 9 source files have no history row because the old short IDs were superseded by timestamped wrapper/deployment files.
- 5 source files participate in history conflicts described below; their rows are individually listed.

### Missing history

These source files have no matching history row:

```text
202608280001_CreateSprintOneSchema.php
202608290001_AddCrmLeadClosedAt.php
202608290002_AddApprovedAdmissionLifecycle.php
202608290003_AddPlayerIdentityConstraint.php
202608290004_CreatePlayerAssignmentTables.php
202608290005_CreateSchedulingTables.php
202608290006_CreateAttendanceTables.php
202609020001_CreateAttendanceRosterSnapshots.php
202609250001_ReconcileCoachAttendanceScope.php
```

Classification: **Missing history**. These are paired with separately named deployment/wrapper files that are recorded, but the original source files remain undiscoverable by the applied history.

### Duplicate/conflicting mappings

| Source migration | History row(s) | Group/batch | Status and reason |
|---|---|---|---|
| `20261007000100_CreateAchievementNotificationRecipients.php` | Same ID/class twice | `default/49`, `tests/51` | **Duplicate**, same migration recorded under two groups. |
| `20261007000200_AddAchievementArchival.php` | Same ID/class twice | `default/50`, `tests/54` | **Duplicate**, same migration recorded under two groups. |
| `20261011000300_AddMatchTournamentLinkageVersion.php` | ID row with `AddMatchTournamentLinkageVersion` | `tests/55` | **Duplicate ID**, class-specific match. |
| `20261011000300_AddCompleteAdmissionFormFields.php` | Same ID row with `AddCompleteAdmissionFormFields` | `tests/57` | **Duplicate ID**, class-specific match. |
| `20261019000100_AddTrialSecondaryCoach.php` | `AppDatabaseMigrations\\AddTrialSecondaryCoach` | `tests/71` | **Inconsistent history**, namespace does not match source. |
| `20261019000100_AddTrialSecondaryCoach.php` | `App\\Database\\Migrations\\AddTrialSecondaryCoach` | `tests/72` | **Duplicate**, same ID/class recorded again. |

There are no applied history rows without a corresponding source migration class after normalizing the stored namespace separator, except the wrong-namespace row above, which maps by class name only and is therefore **Inconsistent history**, not a valid exact match.

## Duplicate IDs, classes, and logical changes

### Duplicate migration IDs

1. `20261007000100`: duplicated across `default` and `tests` groups.
2. `20261007000200`: duplicated across `default` and `tests` groups.
3. `20261011000300`: assigned to two different source classes and recorded twice in `tests`.
4. `20261019000100`: recorded twice in `tests`, once under a wrong namespace.

### Duplicate classes

No duplicate source class names were found. History duplicates exist for `CreateAchievementNotificationRecipients` and `AddAchievementArchival` because the same class was recorded under multiple groups.

### Same logical schema changes

The following pairs are wrapper/deployment duplicates of the same logical schema change:

| Logical change | Original source | Recorded wrapper/deployment source |
|---|---|---|
| Sprint One schema | `202608280001_CreateSprintOneSchema.php` | `20260828000200_CreateSprintOneSchemaWrapper.php` |
| CRM lead closed date | `202608290001_AddCrmLeadClosedAt.php` | `20260829000100_AddCrmLeadClosedAtDeployment.php` |
| Approved admission lifecycle | `202608290002_AddApprovedAdmissionLifecycle.php` | `20260829000200_AddApprovedAdmissionLifecycleDeployment.php` |
| Player identity constraint | `202608290003_AddPlayerIdentityConstraint.php` | `20260829000300_AddPlayerIdentityConstraintDeployment.php` |
| Player assignment tables | `202608290004_CreatePlayerAssignmentTables.php` | `20260829000400_CreatePlayerAssignmentTablesDeployment.php` |
| Scheduling tables | `202608290005_CreateSchedulingTables.php` | `20260829000500_CreateSchedulingTablesDeployment.php` |
| Attendance tables | `202608290006_CreateAttendanceTables.php` | `20260829000600_CreateAttendanceTablesDeployment.php` |
| Attendance roster snapshots | `202609020001_CreateAttendanceRosterSnapshots.php` | `20260902000100_CreateAttendanceRosterSnapshotsDeployment.php` |
| Coach attendance scope | `202609250001_ReconcileCoachAttendanceScope.php` | `20260925000100_ReconcileCoachAttendanceScopeDeployment.php` |

The timestamped wrappers are the rows present in history. Their presence means the schema changes may already be present; it does not prove the original files are safe to rerun.

## Schema comparison

The following read-only checks were run against `information_schema.TABLES`, `COLUMNS`, `STATISTICS`, `KEY_COLUMN_USAGE`, and `REFERENTIAL_CONSTRAINTS`.

| Migration conflict/change | Schema evidence | Classification |
|---|---|---|
| Player assignment deployment pair | `batches`, `player_batch_assignments`, and `player_coach_assignments` exist with expected assignment columns and foreign-key-backed indexes. | Schema already present |
| Attendance roster deployment pair | `attendance_roster_snapshots` and `attendance_roster_snapshot_members` exist with `session_id`, `branch_id`, `snapshot_id`, and `player_id`. | Schema already present |
| Trial secondary coach duplicate/wrong namespace | `trials.secondary_coach_user_id` exists, with `trials_secondary_coach_idx` and `trials_secondary_coach_user_fk`. | Schema already present; potential rerun failure if an unguarded copy is run, although the current source is guarded. |
| Match linkage migration | `matches.version` and `tournament_fixtures.version` exist. `tournament_fixtures.match_id` has a unique index by column set, but the observed index name is `tournament_fixtures_match_id_foreign`, not the migration’s requested `uq_tournament_fixtures_match_id`. | Schema already present; naming drift. Do not assume the migration’s index-name operation is safe to repeat. |
| Complete admission fields migration | `admissions` contains the added intake, date, fee, text, `age`, and `coach_user_id` fields. `admissions_coach_user_fk` exists. | Schema already present |
| Achievement archival duplicate | `achievement_awards` contains archival columns, archival indexes, and `archived_by`/`restored_by` foreign keys. | Schema already present; duplicate history. |
| Achievement notification recipients | Source file named for `achievement_notification_recipients` actually creates `player_user_links`; `player_user_links` exists, while `achievement_notification_recipients` does not. | Inconsistent history/source intent; needs approval |
| Achievement eligibility | `achievement_eligibility_evaluations` exists and the award metadata columns/foreign key exist. | Schema already present |
| Session skill catalog | `session_skill_options` exists with area/name/version/active fields. | Schema already present |
| Attendance history protection | `attendance_history.attendance_id` has `RESTRICT` delete behavior to `attendance.id`. | Schema already present; explains fixture cleanup failures |

Observed tables also include `users`, `roles`, `role_permissions`, `sessions`, `trials`, `admissions`, `matches`, `tournament_fixtures`, and `achievement_awards`, with the reviewed indexes and foreign keys present.

### Rerun risk

The duplicated/wrong history means CodeIgniter’s migration runner cannot be treated as a reliable statement of pending work. Rerunning any original or wrapper migration without an approved state plan could cause duplicate-column, duplicate-index, duplicate-constraint, duplicate-table, or duplicate-permission errors. Several current migrations use guards, but this report does not infer that any migration is safe to rerun.

Classification: **Potential rerun failure** and **Needs approval**.

## Migration-group behavior

`app/Config/Migrations.php` uses the `migrations` history table and the configured timestamp format. `bin/migrate.php` accepts an explicit group, defaults its read/write command target to `tests`, refuses `default` unless explicitly allowed, and refuses production-like database names. Its `status` implementation also calls `ensureTable()`, so it was not used for this read-only inspection.

The same physical database contains rows for both `default` and `tests` for achievement migrations. That creates group ambiguity: CodeIgniter group history is not an independent schema when both groups point to the same database. The schema is shared, while history rows are group-labelled.

Classification: **Inconsistent history**; **Needs approval** before changing group policy or reconciling rows.

## Dependency-order review

History is generally chronological, but the following dependency/order risks are verified:

- `20261007000300_ReconcileAchievementSchemaDrift` is recorded in `tests` batch 52 while the `tests` row for `AddAchievementArchival` is batch 54. The same archival migration is recorded in `default` batch 50 against the same physical database, so the schema may have existed before batch 52, but group-local order is inconsistent.
- `20261011000300` is used for two different schema changes, with one recorded at batch 55 and the other at batch 57. A version-only runner cannot unambiguously identify which source migration the ID represents.
- `20261019000100_AddTrialSecondaryCoach` is recorded first under the wrong namespace and then again under the correct namespace, making replay/order semantics ambiguous.

No claim is made that the current schema is invalid solely from batch order. These are **Inconsistent history** findings requiring approval.

## Test impact

Read-only tests run:

```text
php vendor/bin/phpunit \
  tests/Unit/AchievementSchemaReconciliationContractTest.php \
  tests/Unit/AchievementArchivalContractTest.php \
  tests/Unit/MatchTournamentLinkageContractTest.php \
  tests/Unit/AdmissionCompleteFormContractTest.php \
  tests/Unit/AdmissionPlanRemovalContractTest.php \
  tests/Unit/AttendanceModuleContractTest.php \
  tests/Unit/TrialModuleContractTest.php \
  --no-progress
```

Result: 38 tests, 1,973 assertions, 1 failure.

The failure was `AdmissionPlanRemovalContractTest::testHistoricalLabelFieldsRemainInAdmissionModelsAndViews`, which expects a removed historical-label field in the current renewal view. It is a stale contract test, not a migration execution result. No test in this run applied, rolled back, or cleaned migrations.

Migration-related test dependencies found by static inspection:

- Unit contract tests read migration files directly to verify source contracts.
- Database/feature tests commonly use `DatabaseTestTrait` with `$migrate = false` and depend on the pre-existing test schema.
- `bin/migrate.php` is the project migration entry point for the explicit `tests` group, but it was not run in a state-changing mode.
- Existing database test cleanup is known to conflict with append-only audit rows and attendance-history foreign keys; no such cleanup was run for this reconciliation.

Classification: **Test/environment blocker** for any suite requiring a clean reconstructed database until the history and fixture strategy are resolved.

## Risks

1. A migration runner may skip, duplicate, or select the wrong source when IDs are duplicated.
2. Group-labelled history rows share one physical schema, so `default` and `tests` history cannot be interpreted independently.
3. The wrong namespace row may prevent exact source matching or cause a second application attempt.
4. The same ID is assigned to unrelated admission and match schema changes.
5. Nine old-format source files are absent from history, while wrappers/deployment files are recorded instead.
6. The notification-recipient migration’s filename/class description does not match the table it creates; the expected named table is absent.
7. Some migrations have destructive rollback behavior; rollback cannot be considered safe from the current history alone.
8. Schema presence does not prove that every migration-side permission, index name, trigger, or data backfill completed exactly as intended.

## Safe reconciliation options

These are options for owner review, not instructions executed by this report:

1. **Freeze migration writes and take a schema/history backup.** Preserve the current database and migration table before any reconciliation.
2. **Choose one authoritative migration namespace and group policy.** Decide whether the shared database is represented by `tests` or `default`, and whether duplicate group rows are retained, superseded, or documented.
3. **Perform a source-to-schema review for each conflict.** Compare the actual columns, indexes, foreign keys, permissions, and data effects against the intended migration body; do not rerun solely because a row is missing.
4. **Use a forward-only reconciliation migration only after approval.** It should add only verified missing schema objects with existence guards, preserve historical data, and record explicit audit metadata. It must not delete or rewrite existing migration history without approval.
5. **Resolve the `20261011000300` collision in source control through an approved new ID.** Do not edit an already-applied migration in place.
6. **Resolve the wrong-namespace `20261019000100` row operationally.** Do not silently delete the row or rerun the class.
7. **Repair test fixtures separately.** Use isolated test databases and dependency-aware cleanup that respects append-only audit and attendance-history constraints.

Every option requires approval before implementation. No option was applied here.

## Recommended next action

Obtain owner approval for a migration reconciliation plan that first snapshots the test database and migration history, then explicitly decides group ownership, duplicate-row treatment, the `20261011000300` ID collision, the wrong namespace row, and the notification-recipient table discrepancy. Only after that decision should a forward-only reconciliation migration or controlled history repair be designed and reviewed.

Do not run `latest`, `rollback`, `refresh`, or an unapproved migration against this database.

## Final classifications

- **Verified:** environment, database identity, 113 source files, source namespace/class alignment, 107 history rows, schema object observations, and read-only test command result.
- **Inconsistent history:** group duplication, wrong namespace, dependency-order ambiguity, and shared database/group history.
- **Duplicate:** four duplicate-ID conditions; duplicate logical wrapper/deployment changes; same-class history rows under multiple groups.
- **Missing source:** none for the applied classes after normalizing namespace separators; the wrong-namespace row is not an exact source match.
- **Missing history:** nine old-format source files.
- **Schema already present:** reviewed assignment, attendance, trial, match, admission, achievement, and skill objects are present.
- **Potential rerun failure:** duplicate IDs/classes/groups and schema objects already present; rerun safety is not inferred.
- **Test/environment blocker:** stale read-only contract failure and known database fixture cleanup limitations.
- **Needs approval:** every history repair, group decision, source-ID collision resolution, namespace correction, and any reconciliation migration.

No changes were made.
