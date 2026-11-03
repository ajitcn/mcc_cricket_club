<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SessionManagementContractTest extends TestCase
{
    public function testExistingSchedulingServiceOwnsApprovedSessionWorkflow(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/SchedulingService.php');

        self::assertIsString($service);
        self::assertStringContainsString("'session_created'", $service);
        self::assertStringContainsString("'session_rescheduled'", $service);
        self::assertStringContainsString("'Player scheduling conflict.'", $service);
        self::assertStringContainsString("'Coach', 'Fitness Coach'", $service);
        self::assertStringContainsString("'rescheduled_from_id'", $service);
    }

    public function testRecurringSessionsAreNotAddedBecauseApprovedPolicyDefersThem(): void
    {
        $decisions = file_get_contents(ROOTPATH . 'docs/DECISIONS.md');

        self::assertIsString($decisions);
        self::assertStringContainsString('Implement one-time sessions only.', $decisions);
        self::assertStringContainsString('Recurrence is deferred.', $decisions);
    }

    public function testSchedulingConflictChecksUseDeterministicDatabaseResourceLocks(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/SchedulingService.php');

        self::assertIsString($service);
        self::assertStringContainsString('lockResources', $service);
        self::assertStringContainsString("SELECT GET_LOCK(?, 5) AS acquired", $service);
        self::assertStringContainsString("SELECT RELEASE_LOCK(?)", $service);
        self::assertStringContainsString('sort($resourceNames, SORT_STRING)', $service);
        self::assertStringContainsString("'mcc:scheduling:coach:'", $service);
        self::assertStringContainsString("'mcc:scheduling:ground:'", $service);
        self::assertStringContainsString("'mcc:scheduling:player:'", $service);
        self::assertStringContainsString("throw new RuntimeException('Scheduling conflict.')", $service);
        self::assertGreaterThanOrEqual(2, substr_count($service, '$this->releaseResources($db, $lockedResources)'));
    }

    public function testCoachAvailabilityUsesOnlyStructuredLeaveAndAllWriteWorkflows(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/SchedulingService.php');

        self::assertIsString($service);
        self::assertStringContainsString('private function approvedLeaveConflict(', $service);
        self::assertStringContainsString('coach_unavailability', $service);
        self::assertStringContainsString('unavailable_from<?', $service);
        self::assertStringContainsString('unavailable_to>?', $service);
        self::assertStringContainsString('coach_user_id=? AND branch_id=?', $service);
        self::assertStringContainsString("throw new RuntimeException('Coach unavailable.')", $service);
        self::assertGreaterThanOrEqual(4, substr_count($service, '$this->approvedLeaveConflict('));
        self::assertStringNotContainsString("Coach is outside availability", $service);
        self::assertStringNotContainsString("where('availability'", $service);
    }

    public function testSessionEditWorkflowUsesDedicatedPagePattern(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Config/Routes.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/SchedulingService.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Controllers/Browser.php');
        $list = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Views/index.php');
        $create = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Views/create.php');
        $detail = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Views/show.php');
        $edit = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Views/edit.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/app.js');
        $styles = file_get_contents(ROOTPATH . 'public/assets/css/app.css');

        foreach ([$routes, $service, $controller, $list, $create, $detail, $edit, $script, $styles] as $source) {
            self::assertIsString($source);
        }
        self::assertStringContainsString("\$routes->get('(:num)/edit'", $routes);
        self::assertStringContainsString("\$routes->post('(:num)/edit'", $routes);
        self::assertStringContainsString("'permission:session.update'", $routes);
        self::assertStringContainsString("Browser::availability", $routes);
        self::assertStringContainsString('public function update(int $id', $service);
        self::assertStringContainsString('session_updated', $service);
        self::assertStringContainsString('expected_updated_at', $service);
        self::assertStringContainsString('Session is locked because Attendance has started.', $service);
        self::assertStringContainsString("service('authorization')->can(\$permission", $service);
        self::assertStringNotContainsString('data-session-action="edit"', $list);
        self::assertStringNotContainsString('data-session-action="edit"', $controller);
        self::assertStringNotContainsString('data-session-modal', $list);
        self::assertStringContainsString('data-session-edit-form', $edit);
        self::assertStringNotContainsString('data-session-modal-close', $edit);
        self::assertStringContainsString('csrf_field()', $edit);
        self::assertStringContainsString("\$selectedBranch = (string) old('branch_id', \$session['branch_id'] ?? '')", $edit);
        self::assertStringContainsString("in_array((string) \$player['id'], \$selectedPlayers, true) ? 'selected'", $edit);
        self::assertStringContainsString('Saving...', $script);
        self::assertStringContainsString('sessionSubmitting', $script);
        self::assertStringContainsString('initSessionAvailability', $script);
        self::assertStringContainsString('data-session-submit-form', $create);
        self::assertStringContainsString('data-session-availability', $create);
        self::assertStringContainsString('data-session-availability', $edit);
        self::assertStringContainsString('availabilityWindows', $service);
        self::assertStringNotContainsString('mcc-page-actions', $create);
        self::assertStringNotContainsString('form="session-create-form"', $create);
        self::assertStringContainsString('fa-calendar-plus', $create);
        self::assertStringContainsString('mcc-scheduling-card', $create);
        self::assertStringContainsString('name="status"', $create);
        self::assertStringContainsString('name="status"', $edit);
        self::assertStringContainsString('name="session_type"', $create);
        self::assertStringContainsString('name="session_type"', $edit);
        self::assertStringContainsString('name="description"', $create);
        self::assertStringContainsString('name="description"', $edit);
        self::assertStringContainsString('Description / Notes', $create);
        self::assertStringContainsString('Description / Notes', $edit);
        self::assertStringContainsString('nl2br(esc((string) $session[\'description\']))', $detail);
        self::assertStringContainsString('description', $service);
        self::assertStringContainsString('Choose session type', $create);
        self::assertStringContainsString('One-to-One', $create);
        self::assertStringContainsString('Group', $create);
        self::assertStringContainsString('session_type', $service);
        self::assertStringContainsString('Invalid session type.', $service);
        self::assertStringContainsString('Session Type', $list);
        self::assertStringContainsString('Session Type', $detail);
        self::assertStringContainsString('Invalid session status.', $service);
        self::assertStringContainsString('name="starts_time"', $create);
        self::assertStringContainsString('name="starts_time"', $edit);
        self::assertStringNotContainsString('name="ends_at"', $create . $edit);
        self::assertStringContainsString('name="ends_time"', $create . $edit);
        self::assertStringNotContainsString('data-mcc-datetime-submit-format', $edit);
        self::assertStringContainsString('data-session-edit-form', $edit);
        self::assertStringContainsString('data-mcc-date-picker-time-input="starts-time"', $create);
        self::assertStringNotContainsString('data-mcc-date-picker-time-input="ends-time"', $create . $edit);
        self::assertStringNotContainsString('data-batch-id=', $create);
        self::assertStringContainsString("foreach ((\$formOptions['players'] ?? []) as \$player)", $create);
        self::assertStringContainsString("value=\"<?= (int) \$player['id'] ?>\"", $create);
        self::assertStringContainsString("foreach ((\$formOptions['players'] ?? []) as \$player)", $edit);
        self::assertStringContainsString("where('p.status', 'active')", $service);
        self::assertStringContainsString('needs_active_branch', $service);
        self::assertStringContainsString('Select an active branch in the shared navigation', $create);
        self::assertStringContainsString('data-session-branch', $create);
        self::assertStringContainsString('data-session-submit-form', $detail);
        self::assertStringContainsString('aria-busy', $script);
        self::assertStringContainsString('sessionWorkflowInitialized', $script);
        self::assertStringContainsString('initSessionOptionFilters();', $script);
        self::assertStringContainsString('returnedForm', $script);
        self::assertStringContainsString('mcc-field-error', $script);
        self::assertStringContainsString('closeButton.focus()', $script);
        self::assertStringContainsString('.mcc-session-modal .mcc-modal-dialog', $styles);
        self::assertStringContainsString('.mcc-scheduling-card:has(.mcc-bt-select)', $styles);
        self::assertStringContainsString('.mcc-session-modal-dialog:has(.mcc-bt-select)', $styles);
        self::assertStringContainsString('overflow: visible;', $styles);
        self::assertStringNotContainsString('mcc-session-modal-dialog', $detail);
        self::assertStringContainsString('.mcc-session-edit-form .mcc-form-grid', $styles);
        self::assertStringContainsString('@media (max-width: 767px)', $styles);
    }

    public function testSessionListUsesSqlPaginationAndAuthorizedFilterInputs(): void
    {
        $model = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Models/SessionModel.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/SchedulingService.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Controllers/Browser.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Views/index.php');

        foreach ([$model, $service, $controller, $view] as $source) {
            self::assertIsString($source);
        }
        self::assertStringContainsString('sessionRows', $model);
        self::assertStringContainsString('countSessionRows', $model);
        self::assertStringContainsString('whereIn(\'s.branch_id\', $branchIds)', $model);
        self::assertStringContainsString('->limit($limit, $offset)', $model);
        self::assertStringContainsString('sessionPage', $service);
        self::assertStringContainsString('date_from', $service);
        self::assertStringContainsString("['branch_id', 'batch_id', 'coach_user_id', 'ground_id']", $service);
        self::assertStringContainsString("->orderBy('s.id', 'DESC')", $model);
        self::assertSame(1, substr_count($controller, '->sessionPage('));
        foreach (['name="date"', 'name="branch_id"', 'name="batch_id"', 'name="coach_user_id"', 'name="ground_id"', 'name="status"'] as $field) {
            self::assertStringContainsString($field, $view);
        }
        self::assertStringContainsString('mcc-pagination', $view);
        self::assertStringContainsString('pageUrl', $view);
        self::assertStringContainsString('DD-MM-YYYY', $view);
    }

    public function testSessionDetailsExposeAttendanceFirstFeedbackForEveryRosterPlayer(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/CoachManagementService.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Controllers/Browser.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Views/show.php');

        self::assertIsString($service);
        self::assertIsString($controller);
        self::assertIsString($view);
        self::assertStringContainsString('public function sessionFeedback(int $sessionId)', $service);
        self::assertStringContainsString('attendance_roster_snapshot_members', $service);
        self::assertStringContainsString('coach_feedback', $service);
        self::assertStringContainsString("in_array((string) \$session['status'], ['scheduled', 'active', 'completed'], true)", $service);
        self::assertStringContainsString("'session_feedback' => \$sessionFeedback", $controller);
        self::assertStringContainsString('Feedback / remark', $view);
        self::assertStringContainsString('Attendance required', $view);
        self::assertStringContainsString('Add remark', $view);
        self::assertStringContainsString('Edit remark', $view);
        self::assertStringContainsString('csrf_field()', $view);
    }

    public function testSessionDetailsExposePersistedRosterAndAttendanceSnapshotStates(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/SchedulingService.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Controllers/Browser.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Views/show.php');
        $styles = file_get_contents(ROOTPATH . 'public/assets/css/app.css');

        foreach ([$service, $controller, $view, $styles] as $source) {
            self::assertIsString($source);
        }
        self::assertStringContainsString("table('session_players sp')", $service);
        self::assertStringContainsString("table('attendance_roster_snapshots')", $service);
        self::assertStringContainsString("table('audit_logs al')", $service);
        self::assertStringContainsString("where('al.entity_type', 'session')", $service);
        self::assertStringContainsString("where('al.branch_id', \$branch)", $service);
        self::assertStringContainsString("'snapshot_status'", $service);
        self::assertStringNotContainsString("'ready' => [", $view);
        self::assertStringNotContainsString('Snapshot missing', $view);
        self::assertStringContainsString('Previous values', $view);
        self::assertStringContainsString('mcc-session-metadata-list', $view);
        self::assertStringContainsString('Feedback / remark', $view);
        self::assertStringContainsString('data-session-feedback-open', $view);
        self::assertStringContainsString('data-session-feedback-modal', $view);
        self::assertStringContainsString('data-feedback-return', $view);
        self::assertStringContainsString('data-feedback-version', $view);
        self::assertStringContainsString('data-session-feedback-version', $view);
        self::assertStringContainsString('initSessionFeedbackModal', file_get_contents(ROOTPATH . 'public/assets/js/app.js'));
        self::assertStringContainsString('event.key === \'Escape\'', file_get_contents(ROOTPATH . 'public/assets/js/app.js'));
        self::assertSame(1, substr_count($view, 'Player Feedback / Remarks'));
        self::assertStringContainsString('storage[_-]?path', $view);
        self::assertStringContainsString('$safeBefore', $view);
        self::assertStringContainsString('$safeAfter', $view);
        self::assertStringContainsString('Metadata unavailable', $view);
        self::assertStringContainsString('No metadata available', $view);
        self::assertStringContainsString('metadataMalformed', $service);
        self::assertStringContainsString('mcc-session-history-table', $view);
        self::assertStringContainsString('New start date', $view);
        self::assertStringNotContainsString('New end date', $view);
        self::assertStringNotContainsString('View raw metadata', $view);
        self::assertStringNotContainsString('json_encode', $view);
        self::assertStringNotContainsString('<details', $view);
        self::assertStringContainsString('white-space: pre-wrap', $styles);
        self::assertStringContainsString('Cancelled at:', $view);
        self::assertStringContainsString('Event timestamp', $view);
        self::assertStringNotContainsString('Prepare attendance roster', $view);
        self::assertStringNotContainsString('Capture roster snapshot', $view);
        self::assertStringNotContainsString("attendance/sessions/' . (int) \$session['id'] . '/snapshot", $view);
        self::assertStringNotContainsString('$canCaptureSnapshot', $view);
        self::assertStringNotContainsString('$players !== []', $view);
        self::assertStringNotContainsString('Assign at least one eligible Player before capturing', $view);
        self::assertStringContainsString('attendance_statuses', $controller);
        self::assertStringContainsString('attendanceRoster', $controller);
        self::assertStringContainsString('name="status"', $view);
        self::assertStringContainsString('RAIN_CANCELLED', $view);
        self::assertStringContainsString('COACH_CANCELLED', $view);
        self::assertStringNotContainsString('EXCUSED', $view);
        self::assertStringContainsString('mcc-attendance-summary-meta', $view);
        self::assertStringNotContainsString('total roster', $view);
        self::assertStringContainsString('attendance_updated_at', $view);
        self::assertStringContainsString('Corrected', $view);
        self::assertStringContainsString('mcc-attendance-status', $view);
        self::assertStringContainsString('mcc-attendance-session-table', $styles);
        self::assertStringContainsString('.mcc-scheduling-detail > section', $styles);
        self::assertStringContainsString('.mcc-scheduling-card .mcc-form-grid', $styles);
        self::assertStringContainsString('row-gap: 1.15rem', $styles);
        self::assertStringContainsString('name="return_to"', $view);
        self::assertStringContainsString('safeReturnTarget', file_get_contents(ROOTPATH . 'app/Modules/Attendance/Controllers/Attendance.php'));
        self::assertStringContainsString("attendance.view', \$branch", $controller);
        self::assertStringContainsString("attendance.record', \$branch", $controller);
        self::assertStringNotContainsString('attendance()->record', $controller);
    }

    public function testSessionDetailsAllowReadOnlyCoachViewAndGuardActionsByBranchPermission(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Config/Routes.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/SchedulingService.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Controllers/Browser.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Views/show.php');
        $styles = file_get_contents(ROOTPATH . 'public/assets/css/app.css');

        foreach ([$routes, $service, $controller, $view, $styles] as $source) {
            self::assertIsString($source);
        }

        self::assertStringContainsString("Browser::showPage/$1'", $routes);
        self::assertStringContainsString('private function allowSessionRead', $service);
        self::assertStringContainsString("can('coach.view'", $service);
        self::assertStringContainsString("can('session.update'", $service);
        self::assertStringContainsString('detailPageActions(', $controller);
        self::assertStringContainsString("(:num)/complete", $routes);
        self::assertStringContainsString('function complete', $controller);
            self::assertStringContainsString('completionEligible', $service);
            self::assertStringContainsString('session_completed', $service);
            self::assertStringNotContainsString('A session cannot be completed before it ends.', $service);
        self::assertStringContainsString('Complete session', $controller);
        self::assertStringContainsString('mcc-status-badge is-completed', $view);
        self::assertStringContainsString('if ($canUpdate && $status === \'scheduled\')', $view);
        self::assertStringContainsString('can_attendance_view', $view);
        self::assertStringContainsString('No eligible Players are selected for this session.', $view);
          self::assertStringContainsString('No Coach is assigned to this session.', $view);
          self::assertStringContainsString('This session has no Ground assignment.', $view);
        self::assertStringContainsString('.mcc-session-information', $styles);
        self::assertStringContainsString('@media (max-width: 767px)', $styles);
        self::assertStringContainsString('mcc-scheduling-detail', $view);
        self::assertStringContainsString('data-mcc-date-picker-time-input="new-start-time"', $view);
        self::assertStringNotContainsString('data-mcc-date-picker-time-input="new-end-time"', $view);
        self::assertStringContainsString('data-mcc-date-submit-format="Y-m-d"', $view);
        self::assertStringContainsString('name="starts_time"', $view);
        self::assertStringContainsString('End time', $view);
        self::assertStringNotContainsString('placeholder="DD-MM-YYYYTHH:MM:SS+05:30"', $view);
        self::assertStringNotContainsString('data-mcc-datetime-submit-format', $view);
        self::assertStringContainsString('normalizeDateTimeInput($this->request->getPost())', $controller);
        self::assertStringContainsString("'Invalid session time.' => ['starts_time', 'ends_time']", $controller);
        self::assertStringContainsString('detailPageActions(', $controller);
        self::assertStringContainsString("site_url('attendance/sessions/' . \$id)", $controller);
        self::assertStringContainsString('href="#coach-assignment-heading"', $controller);
        self::assertStringContainsString('href="#session-actions-heading"', $controller);
        self::assertStringContainsString('mcc-button-danger', $controller);
        self::assertStringContainsString('csrf_field()', $view);
    }

    public function testSessionFormsUseAuthorizedRelationshipSelectors(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/SchedulingService.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Controllers/Browser.php');
        $create = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Views/create.php');
        $edit = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Views/edit.php');
        $javascript = file_get_contents(ROOTPATH . 'public/assets/js/app.js');
        $list = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Views/index.php');
        $detail = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Views/show.php');

        foreach ([$service, $controller, $create, $edit, $list] as $source) {
            self::assertIsString($source);
        }
        self::assertStringContainsString('sessionFormOptions', $service);
        self::assertStringContainsString("where('ba.is_active', 1)", $service);
        self::assertStringContainsString('whereIn(\'ub.branch_id\', $branchIds)', $service);
        self::assertStringContainsString("orWhere('cp.id IS NULL', null, false)", $service);
        self::assertStringContainsString('ground.book', $service);
        self::assertStringContainsString('whereIn(\'g.branch_id\', $bookableBranchIds)', $service);
        self::assertStringContainsString('whereIn(\'p.branch_id\', $branchIds)', $service);
        foreach (['branch_id', 'program_id', 'batch_id', 'age_group_id', 'coach_user_id', 'ground_id'] as $field) {
            self::assertStringContainsString('name="' . $field . '"', $create);
            self::assertStringContainsString('name="' . $field . '"', $edit);
        }
        self::assertStringContainsString("'session.create'", $controller);
        self::assertStringContainsString('sessionFormOptions(', $controller);
        self::assertStringContainsString('Invalid batch.', $service);
        self::assertStringContainsString('Coach is not authorized for branch.', $service);
        self::assertStringContainsString('Player is not eligible.', $service);
        self::assertStringContainsString('Invalid branch.', $service);
        self::assertStringContainsString('array_unique(array_map', $service);
        self::assertStringContainsString('Invalid coach.', $service);
        self::assertStringContainsString('The action you requested is not allowed.', $controller);
        self::assertStringContainsString("'SCH-' . strtoupper", $controller);
        self::assertStringContainsString("Reference ' . \$reference", $controller);
        self::assertStringContainsString('log_message', $controller);
        self::assertStringContainsString('data-session-branch-options', $create);
        self::assertStringContainsString('data-session-branch-options', $edit);
        self::assertStringContainsString('mcc-scheduling-card', $list ?? '');
        self::assertStringContainsString('mcc-field-error', $create);
        self::assertStringContainsString('No active authorized Coaches are available for this branch.', $create);
        self::assertStringContainsString('Select a branch to load available Coaches.', $create);
        self::assertStringContainsString('Select a Coach and date to view configured availability.', $edit);
        self::assertStringContainsString('Choose coach', $create);
        self::assertStringContainsString('Choose coach', $edit);
        self::assertStringContainsString("old('coach_user_id', \$session['coach_user_id'] ?? '')", $edit);
        self::assertStringContainsString("old('branch_id', \$session['branch_id'] ?? '')", $edit);
        self::assertStringContainsString("old('batch_id', \$session['batch_id'] ?? '')", $edit);
        self::assertStringContainsString("old('age_group_id', \$session['age_group_id'] ?? '')", $edit);
        self::assertStringContainsString("old('program_id', \$session['program_id'] ?? '')", $edit);
        self::assertStringContainsString("fieldExists('program_id', 'sessions')", $service);
        self::assertStringContainsString("s.program_id AS program_id", $service);
        self::assertStringContainsString('validateProgram', $service);
        self::assertStringContainsString("'program_id' => \$input['program_id']", $service);
        self::assertStringContainsString('data-bt-select', $create);
        self::assertStringContainsString('data-bt-select', $edit);
        self::assertStringNotContainsString('title="Select players"', $create);
        self::assertStringNotContainsString('title="Select players"', $edit);
        self::assertStringContainsString("old('player_ids', array_column(\$session['players'] ?? [], 'player_id'))", $edit);
        self::assertStringNotContainsString("select.name === 'batch_id'", file_get_contents(ROOTPATH . 'public/assets/js/app.js'));
        self::assertStringNotContainsString("option.dataset.programId === program.value", file_get_contents(ROOTPATH . 'public/assets/js/app.js'));
        self::assertStringContainsString('option.selected = false;', file_get_contents(ROOTPATH . 'public/assets/js/app.js'));
        self::assertStringContainsString('visiblePlayers', file_get_contents(ROOTPATH . 'public/assets/js/app.js'));
        self::assertStringContainsString('age_group_name', $list);
        self::assertStringContainsString('Session category', $detail);
        $ageGroupMigration = file_get_contents(
            ROOTPATH . 'app/Database/Migrations/20261026000400_AddSessionAgeGroup.php'
        );
        self::assertStringContainsString("'age_group_id'", $ageGroupMigration);
        self::assertStringContainsString('Forward-only migration', $ageGroupMigration);
    }

    public function testIndividualSessionSkillCatalogUsesTheRecordedOptions(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/20261011000100_CreateIndividualSessionSkillCatalog.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/SchedulingService.php');
        $create = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Views/create.php');
        $edit = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Views/edit.php');
        $show = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Views/show.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/app.js');

        foreach ([$migration, $service, $create, $edit, $show, $script] as $source) {
            self::assertIsString($source);
        }

        foreach ([
            'Front-foot drive', 'Back-foot defence', 'Pull shot', 'Cut shot', 'Sweep shot', 'Running between wickets',
            'Line and length', 'Seam movement', 'Swing control', 'Spin control', 'Yorker', 'Bouncer',
            'Ground fielding', 'Catching', 'Throwing accuracy', 'High catches', 'Boundary fielding', 'Direct hit',
            'Stance and footwork', 'Taking behind the stumps', 'Glovework', 'Standing up to the stumps', 'Standing back', 'Stumping technique',
            'Speed and agility', 'Strength', 'Endurance', 'Mobility', 'Core stability', 'Recovery',
            'Shot selection', 'Strike rotation', 'Field placement', 'Bowling plans', 'Match awareness', 'Decision-making',
        ] as $option) {
            self::assertStringContainsString($option, $migration);
        }

        self::assertStringContainsString('skill_option_id', $service);
        self::assertStringContainsString('Invalid skill pairing.', $service);
        self::assertStringContainsString('Retired skill option.', $service);
        self::assertStringContainsString("addUniqueKey(['area', 'name', 'version'])", $migration);
        self::assertStringContainsString("'skill_option_label'", $migration);
        self::assertStringContainsString('data-session-skill-area', $create);
        self::assertStringContainsString('data-session-skill-option', $create);
        self::assertStringContainsString('data-session-skill-area', $edit);
        self::assertStringContainsString('old(\'skill_option_id\'', $edit);
        self::assertStringContainsString('data-skill-active', $create);
        self::assertStringContainsString('data-skill-retired', $create);
        self::assertStringContainsString('data-skill-active', $edit);
        self::assertStringContainsString('data-skill-retired', $edit);
        self::assertStringContainsString('data-bt-select-placeholder="Choose skill"', $create);
        self::assertStringContainsString('data-bt-select-placeholder="Choose skill"', $edit);
        self::assertStringContainsString('optionSelect.disabled = area === \'\';', $script);
        self::assertStringContainsString('optionSelect.value = \'\';', $script);
        self::assertStringContainsString('toggle.disabled = select.disabled;', $script);
        self::assertStringContainsString('normalizeOptionalSkillOptionId', $service);
        self::assertStringContainsString("optionSelect.value = '';", file_get_contents(ROOTPATH . 'public/assets/js/app.js'));
        self::assertStringContainsString('sync(true)', file_get_contents(ROOTPATH . 'public/assets/js/app.js'));
        self::assertStringContainsString('Skill focus', $show);
        self::assertStringContainsString('initSessionSkillSelectors', $script);
        self::assertStringContainsString('initBtSelect(form);', $script);
        self::assertStringContainsString('option.dataset.skillRetired', $script);
        self::assertStringContainsString('bt-select-refresh', $script);
    }
}
