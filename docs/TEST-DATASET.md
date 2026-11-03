# Permanent MCC test dataset

Run `php bin/seed-test-data.php` to insert the reusable `TEST_MCC_*` browser dataset. The command refuses production and refuses every database except `mcc_cricket_club_test`.

The dataset covers two branches, approved application roles and implemented permissions, enabled/disabled authentication accounts, CRM lead/lost states, approved and converted admissions, one converted player, batch and coach assignments, scheduled/cancelled sessions, attendance, and a dataset audit marker. Guardian/contact relationships and documents are not seeded because their workflows are not implemented in the current approved slice.

All seeded identities use `.invalid` addresses and synthetic names. Local passwords are recorded only in the ignored `.test-credentials.local.md` file. The seeder is insert-only and idempotent: it positively identifies its own stable fixtures, preserves existing rows, and never truncates, deletes, resets, drops, or recreates data.

`mcc_cricket_club_test` is persistent and non-disposable. Do not use this dataset as PHPUnit disposable state. Existing tests containing `emptyTable()` or equivalent destructive cleanup must not be run against it.

## Player write-workflow fixtures

Player write integration tests use a permanent unique-fixture strategy. Each run creates only newly identifiable records named with the `TEST_MCC_PLAYER_WRITE_<unique-id>` convention (or a derived marker in related email/name fields), locates records through those identifiers, and never assumes tables are empty. Tests do not delete, truncate, replace, or globally clean fixtures; existing seeded and business records remain in place. This strategy is implemented by `tests/database/PlayerWriteWorkflowSafeTest.php`.

CRM Follow-Up safe tests follow the same permanent strategy. Any rows needed by a test are created through the approved service with a unique `TEST_MCC_FOLLOWUP_FILTER_<unique-id>` marker, and tests do not depend on execution order or pre-existing Follow-Up rows. Existing seed and business records remain untouched.

CRM Lead safe workflow tests use `tests/database/CrmLeadSafeWorkflowTest.php`. Each run creates only persistent, uniquely identifiable Lead fixtures marked `TEST_MCC_LEAD_<unique-id>` through `CrmService`; related status-history and audit rows are retained for inspection. The suite verifies the existing canonical/legacy route boundary, server-side filters and branch isolation, create/edit/status/audit behavior, duplicate warnings, CSRF, and relationship read context without cleanup. `CrmLeadBrowserSafeTest.php` is not considered safe because its teardown removes rows; the legacy `CrmLeadIntegrationTest.php` also remains excluded because it performs global cleanup.

Attendance verification must not run the legacy tests/database/AttendanceIntegrationTest.php against the persistent database because it contains destructive global cleanup. tests/database/AttendanceSafeWorkflowTest.php is the safe replacement for its approved workflow coverage: it uses the verified mcc_cricket_club_test database, creates only new session/roster/attendance fixtures, uses TEST_MCC_ATTENDANCE_<unique-id> correction markers, preserves all rows, and never truncates, deletes, rolls back, or cleans shared tables.

## Scheduling safe workflow fixtures

Scheduling verification reuses the stable `TEST_MCC_*` branches, batches, coach, and converted player from the dataset. Each test run generates a unique future UTC window and uses only its own session/availability rows; it never assumes the sessions tables are empty and never deletes or replaces fixtures. The safe coverage is in `tests/database/SchedulingSafeWorkflowTest.php`. The legacy `SchedulingIntegrationTest.php` contains destructive cleanup and must not be executed against the persistent test database.

## Branch-management safe workflow fixtures

Branch-management verification uses the stable seeded administrator and creates only persistent branches named `TEST_MCC_BRANCH_WRITE_<unique-id>`. Tests locate their own records, do not replace seeded branches, do not modify unrelated relationships, and perform no cleanup or deletion. The safe coverage is in `tests/database/BranchManagementSafeTest.php`; the legacy `BranchCreationIntegrationTest.php` remains excluded because it performs destructive teardown.

Cross-module UI and route verification uses the existing safe persistent fixtures. The safe matrix passed 88 tests / 1,035 assertions across implemented module contracts, browser-rendered routes, RBAC/navigation checks, and persistent workflow suites. No migrations or seeders were executed by the audit; no data cleanup was performed and deletes remain zero. Legacy tests containing destructive table cleanup remain excluded.
