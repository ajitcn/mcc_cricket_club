<?php

declare (strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AttendanceModuleContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__, 2);
    }

    public function testCanonicalRoutesUseExplicitMethodsAndPermissions(): void
    {
        $routes = file_get_contents($this->root . '/app/Modules/Attendance/Config/Routes.php');

        self::assertIsString($routes);
        self::assertStringContainsString("['filter' => 'auth']", $routes);
        self::assertStringContainsString("\$routes->get('/',", $routes);
        self::assertStringContainsString("\$routes->get('sessions/(:num)',", $routes);
        self::assertStringContainsString("\$routes->get('records/(:num)',", $routes);
        self::assertStringContainsString("\$routes->post('sessions/(:num)/players/(:num)',", $routes);
        self::assertStringContainsString("\$routes->post('sessions/(:num)/save',", $routes);
        self::assertStringNotContainsString("\$routes->post('sessions/(:num)/snapshot',", $routes);
        self::assertStringContainsString("\$routes->post('(:num)/correct',", $routes);
        self::assertStringContainsString("'permission:attendance.view'", $routes);
        self::assertStringContainsString("'permission:attendance.record'", $routes);
        self::assertStringContainsString("'permission:attendance.correct'", $routes);
    }

    public function testUserDirectedStatusesAndReportingStayWithinScope(): void
    {
        $service = file_get_contents($this->root . '/app/Services/AttendanceService.php');
        $requirements = file_get_contents($this->root . '/docs/REQUIREMENTS.md');
        $moduleStatus = file_get_contents($this->root . '/docs/MODULE-STATUS.md');

        self::assertIsString($service);
        self::assertIsString($requirements);
        self::assertIsString($moduleStatus);
        self::assertStringContainsString("['PRESENT', 'ABSENT', 'LEAVE', 'MEDICAL', 'LATE', 'RAIN_CANCELLED', 'COACH_CANCELLED']", $service);

        foreach (['sessions', 'roster', 'record', 'correct', 'find'] as $method) {
            self::assertStringContainsString("function $method(", $service);
        }

        self::assertStringContainsString('denominator', strtolower($requirements));
        self::assertStringContainsString('remain explicitly deferred', strtolower($requirements));
        self::assertStringContainsString('denominator', strtolower($moduleStatus));
        self::assertStringContainsString('calculations/alerts explicitly deferred', strtolower($moduleStatus));
        self::assertStringContainsString('function metrics(', $service);
        self::assertStringContainsString('function alerts(', $service);
        self::assertStringNotContainsString('notification', strtolower($service));
    }

    public function testAttendanceViewsExposeApprovedFiltersAndHistory(): void
    {
        $index = file_get_contents($this->root . '/app/Modules/Attendance/Views/index.php');
        $session = file_get_contents($this->root . '/app/Modules/Attendance/Views/session.php');
        $detail = file_get_contents($this->root . '/app/Modules/Attendance/Views/detail.php');
        $styles = file_get_contents($this->root . '/public/assets/css/app.css');

        self::assertIsString($index);
        self::assertIsString($session);
        self::assertIsString($detail);
        self::assertIsString($styles);
        self::assertStringContainsString("view('staff_header'", $index);
        self::assertStringContainsString("view('staff_footer')", $detail);

        foreach (['name="date"', 'name="session_id"', 'name="player"', 'name="attendance_status"',
            'name="status"', 'name="branch_id"', 'name="batch_id"'] as $filter) {
            self::assertStringContainsString($filter, $index);
        }

        foreach (['PRESENT', 'ABSENT', 'LEAVE', 'MEDICAL', 'LATE', 'RAIN_CANCELLED', 'COACH_CANCELLED'] as $status) {
            self::assertStringContainsString($status, $session);
        }

        self::assertStringContainsString('csrf_token()', $session);
        self::assertStringContainsString('No remark added', $session);
        self::assertStringContainsString('Attendance history', $detail);
        self::assertStringContainsString('Append-only attendance event history', $detail);
        self::assertStringContainsString('Attendance session pages', $index);
        self::assertStringContainsString('pageUrl', $index);
        self::assertStringContainsString('data-attendance-bulk-form', $session);
        self::assertStringNotContainsString('attendance-snapshot-form', $session);
        self::assertStringContainsString('data-attendance-form-feedback', $session);
        self::assertStringContainsString('data-mcc-attendance-batches-by-branch', $index);
        self::assertStringNotContainsString("</label>\n</label>", $session);
        self::assertStringContainsString('mcc-attendance-summary-grid', $session);
        self::assertStringContainsString('mcc-scheduling-detail mcc-attendance-session-page', $session);
        self::assertStringContainsString('mcc-session-information', $session);
        self::assertStringNotContainsString('>Excused<', $session);
        foreach (['Player', 'Status', 'Remark', 'attendance-status-select'] as $column) {
            self::assertStringContainsString($column, $session);
        }
        self::assertStringNotContainsString('Record attendance', $session);
        self::assertStringNotContainsString('Submit correction', $session);
        self::assertStringNotContainsString('Edit remark', $session);
        self::assertStringNotContainsString('Record attendance for <?=', $session);
        self::assertStringNotContainsString('Correct attendance for <?=', $session);
        self::assertStringContainsString('mcc-attendance-session-table-wrap', $styles);
        self::assertStringContainsString('mcc-attendance-session-table', $styles);
        self::assertStringNotContainsString('attendance-roster-table', $styles);
        self::assertStringContainsString("site_url('sessions/' . \$sessionId . '/page')", $session);
        self::assertStringContainsString('The related Session Details link is unavailable.', $session);
        self::assertStringNotContainsString("site_url('attendance')", $session);
        self::assertStringContainsString('Feedback / remark', $session);
        self::assertStringContainsString('data-session-feedback-modal', $session);
        self::assertStringContainsString('data-attendance-feedback-open', $session);
        self::assertStringContainsString('data-session-feedback-version', $session);
        self::assertStringContainsString('Attendance required', $session);
        self::assertStringContainsString(
            "'session_feedback' => service('coach')->sessionFeedback(\$id)",
            file_get_contents($this->root . '/app/Modules/Attendance/Controllers/Attendance.php'),
        );
    }

    public function testAttendanceViewsExposeSemanticStatusesAndStateActions(): void
    {
        $index = file_get_contents($this->root . '/app/Modules/Attendance/Views/index.php');
        $session = file_get_contents($this->root . '/app/Modules/Attendance/Views/session.php');
        $detail = file_get_contents($this->root . '/app/Modules/Attendance/Views/detail.php');
        $components = file_get_contents($this->root . '/public/assets/css/components.css');

        self::assertIsString($index);
        self::assertIsString($session);
        self::assertIsString($detail);
        self::assertIsString($components);

        foreach ([
            'mcc-attendance-status-present',
            'mcc-attendance-status-absent',
            'mcc-attendance-status-late',
            'mcc-attendance-status-excused',
        ] as $statusClass) {
            self::assertStringContainsString($statusClass, $session);
            self::assertStringContainsString($statusClass, $detail);
            self::assertStringContainsString($statusClass, $components);
        }

        self::assertStringNotContainsString('Snapshot missing', $session);
        self::assertStringNotContainsString('Capture failed', $session);
        self::assertStringNotContainsString('Capture roster snapshot', $session);
        self::assertStringContainsString('Attendance already recorded', $session);
        self::assertStringContainsString('attendance-readonly-alert', $session);
        self::assertStringContainsString('attendance-session-cancelled', $index);
        self::assertStringContainsString('No attendance sessions found', $index);
        self::assertStringContainsString('attendance-empty-state', $session);
        self::assertStringContainsString('mcc-badge mcc-status-badge', $index);
        self::assertStringContainsString('mcc-badge mcc-status-badge', $detail);
    }

    public function testAttendanceRosterProvidesSmallScreenCardLayoutHooks(): void
    {
        $session = file_get_contents($this->root . '/app/Modules/Attendance/Views/session.php');
        $styles = file_get_contents($this->root . '/public/assets/css/app.css');

        self::assertIsString($session);
        self::assertIsString($styles);
        self::assertStringContainsString('mcc-attendance-session-table', $session);
        self::assertStringContainsString('mcc-attendance-session-table-wrap', $session);
        self::assertStringContainsString('data-label="Status"', $session);
        self::assertStringNotContainsString('data-label="Actions"', $session);
        self::assertStringNotContainsString('attendance-mobile-label', $session);
        self::assertStringContainsString('mcc-session-feedback-modal', $session);
        self::assertStringNotContainsString('style="', $session);
        self::assertStringContainsString('data-attendance-bulk-form', $session);
        self::assertStringNotContainsString('attendance-snapshot-form', $session);
        self::assertStringContainsString('@media (max-width: 700px)', $styles);
        self::assertStringContainsString('.mcc-attendance-session-table tbody', $styles);
        self::assertStringNotContainsString('.attendance-roster-table', $styles);
        self::assertStringContainsString('width: 100%', $styles);
        self::assertStringContainsString('.mcc-attendance-session-page', $styles);
        self::assertStringContainsString('overflow-wrap: anywhere', $styles);
        self::assertStringContainsString('.mcc-attendance-session-page > section', $styles);
        self::assertStringContainsString('.mcc-attendance-session-page .mcc-attendance-bulk-form', $styles);
        self::assertStringContainsString('.mcc-attendance-session-page .attendance-status-select', $styles);
    }

    public function testAttendanceSaveFeedbackSupportsLoadingAndConflictRecovery(): void
    {
        $script = file_get_contents($this->root . '/public/assets/js/app.js');

        self::assertIsString($script);
        self::assertStringContainsString('initAttendanceFormFeedback', $script);
        self::assertStringContainsString('Saving...', $script);
        self::assertStringContainsString('attendanceSubmitting', $script);
        self::assertStringContainsString('response.status === 409', $script);
        self::assertStringContainsString(
            'This attendance record changed in another session. Reload the latest attendance data and try again.',
            $script
        );
        self::assertStringContainsString(
            "'.attendance-save-form, [data-attendance-bulk-form]'",
            $script
        );
    }

    public function testAttendanceFiltersSupportServerFallbackAndBranchBatchOptions(): void
    {
        $service = file_get_contents($this->root . '/app/Services/AttendanceService.php');
        $model = file_get_contents($this->root . '/app/Modules/Attendance/Models/AttendanceModel.php');
        $script = file_get_contents($this->root . '/public/assets/js/app.js');

        self::assertIsString($service);
        self::assertIsString($model);
        self::assertIsString($script);
        self::assertStringContainsString("? 'Y-m-d'", $service);
        self::assertStringContainsString("'d-m-Y'", $service);
        self::assertStringContainsString('batchesByBranch', $model);
        self::assertStringContainsString('initAttendanceBranchBatchFilter', $script);
    }

    public function testAttendanceSessionListUsesSqlPaginationAndSingleControllerPageQuery(): void
    {
        $model = file_get_contents($this->root . '/app/Modules/Attendance/Models/AttendanceModel.php');
        $service = file_get_contents($this->root . '/app/Services/AttendanceService.php');
        $controller = file_get_contents($this->root . '/app/Modules/Attendance/Controllers/Attendance.php');

        self::assertIsString($model);
        self::assertIsString($service);
        self::assertIsString($controller);
        self::assertStringContainsString('countSessionRows', $model);
        self::assertStringContainsString('limit($limit, $offset)', $model);
        self::assertStringContainsString('->whereIn(\'s.branch_id\', $branchIds)', $model);
        self::assertStringContainsString('sessionPage', $service);
        self::assertStringContainsString('date_from', $service);
        self::assertSame(0, substr_count($controller, '->sessions('));
        self::assertSame(1, substr_count($controller, '->sessionPage('));
    }

    public function testAttendanceUsesCentralAuditBoundary(): void
    {
        $service = file_get_contents($this->root . '/app/Services/AttendanceService.php');
        $rules = file_get_contents($this->root . '/docs/PROJECT-RULES.md');

        self::assertIsString($service);
        self::assertIsString($rules);
        self::assertSame(7, substr_count($service, "service('auditPersistence')->record("));
        self::assertStringContainsString("'attendance_recorded'", $service);
        self::assertStringContainsString("'attendance_corrected'", $service);
        self::assertStringContainsString('audit', strtolower($rules));
        self::assertStringNotContainsString('new AuditLogger', $service);
    }

    public function testUserDirectedReportingExcludesLeaveMedicalAndCancelledOutcomes(): void
    {
        $service = file_get_contents($this->root . '/app/Services/AttendanceService.php');

        self::assertIsString($service);
        self::assertStringContainsString("'LEAVE', 'MEDICAL', 'RAIN_CANCELLED', 'COACH_CANCELLED'", $service);
        self::assertStringContainsString("'PRESENT', 'LATE'", $service);
        self::assertStringContainsString("'monday this week'", $service);
        self::assertStringContainsString("config('App')->appTimezone", $service);
    }

    public function testRecordEligibilityUsesDefinedSessionVariables(): void
    {
        $service = file_get_contents($this->root . '/app/Services/AttendanceService.php');

        self::assertIsString($service);
        self::assertStringContainsString("(int) \$s['id']", $service);
        self::assertStringContainsString("\$p,", $service);
        self::assertStringContainsString("(int) \$s['branch_id']", $service);
        self::assertStringContainsString('static fn(array $member): bool => (int) $member[\'player_id\'] === $p', $service);
        self::assertStringContainsString('if ($row === [])', $service);
    }

    public function testCorrectionUsesFetchedAttendanceRow(): void
    {
        $service = file_get_contents($this->root . '/app/Services/AttendanceService.php');

        self::assertIsString($service);
        self::assertStringContainsString('if (!is_array($attendance))', $service);
        self::assertStringContainsString("\$attendance['attendance_status']", $service);
        self::assertStringContainsString("\$attendance['session_branch']", $service);
        self::assertStringNotContainsString("\$a['session_branch']", $service);
    }

    public function testAttendanceBranchAuthorizationUsesSessionLineage(): void
    {
        $service = file_get_contents($this->root . '/app/Services/AttendanceService.php');
        $model = file_get_contents($this->root . '/app/Modules/Attendance/Models/AttendanceModel.php');

        self::assertIsString($service);
        self::assertIsString($model);
        self::assertStringContainsString('assertBranchLineage', $service);
        self::assertStringContainsString('$this->allow((int) $r[\'session_branch\'], \'attendance.view\')', $service);
        self::assertStringContainsString('$this->allow((int) $attendance[\'session_branch\'], \'attendance.correct\')', $service);
        self::assertStringContainsString('s.branch_id AS session_branch', $model);
        self::assertStringContainsString("'Attendance branch mismatch.'", $service);
    }

    public function testRosterSnapshotContractRemainsInsertOnlyCompatibilityStorage(): void
    {
        $migration = file_get_contents($this->root . '/app/Database/Migrations/202609020001_CreateAttendanceRosterSnapshots.php');
        $service = file_get_contents($this->root . '/app/Services/AttendanceService.php');
        $snapshotModel = file_get_contents($this->root . '/app/Modules/Attendance/Models/AttendanceRosterSnapshotModel.php');
        $controller = file_get_contents($this->root . '/app/Modules/Attendance/Controllers/Attendance.php');
        $sessionView = file_get_contents($this->root . '/app/Modules/Attendance/Views/session.php');

        self::assertIsString($migration);
        self::assertIsString($service);
        self::assertIsString($snapshotModel);
        self::assertIsString($controller);
        self::assertIsString($sessionView);
        self::assertStringContainsString("createTable('attendance_roster_snapshots', true)", $migration);
        self::assertStringContainsString("createTable('attendance_roster_snapshot_members', true)", $migration);
        self::assertStringContainsString("addUniqueKey('session_id', 'uq_attendance_roster_snapshots_session')", $migration);
        self::assertStringContainsString("addUniqueKey(['snapshot_id', 'player_id'], 'uq_attendance_roster_snapshot_player')", $migration);
        self::assertStringContainsString('public function captureRosterSnapshot(int $sessionId): int', $service);
        self::assertStringContainsString('private function ensureRosterSnapshot(', $service);
        self::assertStringContainsString("'snapshot_status'", $service);
        self::assertStringContainsString("'Attendance roster snapshot required.'", $service);
        self::assertStringContainsString("'Attendance is outside the recording window.'", $controller);
        self::assertStringContainsString('Attendance can only be recorded during the session window.', $controller);
        self::assertStringContainsString("'Attendance roster snapshot mismatch.'", $controller);
        self::assertStringContainsString("'Attendance version required.'", $controller);
        self::assertStringContainsString('Attendance can only be recorded during the session window.', $controller);
        self::assertStringContainsString('sessionIdFor($id)', $controller);
        self::assertStringContainsString('public function sessionIdFor(int $attendanceId): int', $service);
        self::assertStringContainsString("trim(\$reason),\n                \$db", $service);
        self::assertStringContainsString("'attendance_recorded'", $service);
        self::assertStringContainsString("null,\n                null,\n                \$db", $service);
        self::assertStringContainsString('record was busy. Reload Session Details and try again.', $controller);
        self::assertStringContainsString(
            'Attendance has already been recorded for this Player. Reload the latest attendance data before trying again.',
            $controller
        );
        self::assertStringContainsString(
            'This attendance record changed in another session. Reload the latest attendance data and try again.',
            $controller
        );
        self::assertStringContainsString('if (! $inserted)', $service);
        self::assertStringContainsString('if ($id < 1)', $service);
        self::assertStringContainsString('if (! $historyInserted)', $service);
        self::assertStringNotContainsString('name="snapshot_id"', $sessionView);
        self::assertStringContainsString("->withInput()->with('error'", $controller);
        self::assertStringContainsString("attendance_roster_snapshots_no_update", $migration);
        self::assertStringContainsString("attendance_roster_snapshot_members_no_delete", $migration);
    }

    public function testAttendanceRecordRejectsDuplicateInsertAndReturnsSpecificJsonErrors(): void
    {
        $service = file_get_contents($this->root . '/app/Services/AttendanceService.php');
        $controller = file_get_contents($this->root . '/app/Modules/Attendance/Controllers/Attendance.php');

        self::assertIsString($service);
        self::assertIsString($controller);
        self::assertStringContainsString("throw new RuntimeException('Attendance already exists.')", $service);
        self::assertStringContainsString(
            "str_contains(strtolower(\$e->getMessage()), 'duplicate')",
            $service
        );
        self::assertStringContainsString("'Attendance already exists.' =>", $controller);
        self::assertStringContainsString("'Stale attendance update.' =>", $controller);
        self::assertStringContainsString("->setJSON(['error' => \$message])", $controller);
    }
}
