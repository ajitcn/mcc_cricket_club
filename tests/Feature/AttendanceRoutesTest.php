<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class AttendanceRoutesTest extends TestCase
{
    public function testAttendanceRoutesUseExplicitBranchScopedPermissions(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Attendance/Config/Routes.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Attendance/Controllers/Attendance.php');

        self::assertIsString($routes);
        self::assertIsString($controller);
        self::assertStringContainsString("\$routes->get('sessions/(:num)'", $routes);
        self::assertStringContainsString("\$routes->post('sessions/(:num)/save'", $routes);
        self::assertStringNotContainsString("\$routes->post('sessions/(:num)/snapshot'", $routes);
        self::assertStringContainsString("\$routes->post('sessions/(:num)/players/(:num)'", $routes);
        self::assertStringContainsString("\$routes->post('(:num)/correct'", $routes);
        self::assertStringContainsString('permission:attendance.view', $routes);
        self::assertStringContainsString('permission:attendance.record', $routes);
        self::assertStringContainsString('permission:attendance.correct', $routes);
        self::assertStringContainsString('updateStatuses', $controller);
        self::assertStringNotContainsString('captureSnapshot', $controller);
        self::assertStringContainsString('safeReturnTarget', $controller);
    }

    public function testAttendanceSessionViewKeepsInlineEditingAndOneSaveAction(): void
    {
        $session = file_get_contents(ROOTPATH . 'app/Modules/Attendance/Views/session.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/AttendanceService.php');

        self::assertIsString($session);
        self::assertIsString($service);
        self::assertStringContainsString('data-attendance-player', $session);
        self::assertStringContainsString('versions[', $session);
        self::assertStringContainsString('correction_reason', $session);
        self::assertStringContainsString('Save Attendance', $session);
        self::assertSame(1, substr_count($session, 'Save Attendance'));
        self::assertStringNotContainsString('Edit Attendance', $session);
        self::assertStringNotContainsString('Attendance status', $session);
        self::assertStringContainsString("allow((int) \$session['branch_id'], 'attendance.correct')", $service);
        self::assertStringContainsString('assertCoachSessionAccess', $service);
        self::assertStringContainsString('editableMemberIds', $service);
        self::assertStringContainsString('Stale attendance update.', $service);
    }

    public function testAttendanceSessionViewKeepsApprovedStatusesWithoutSnapshotControls(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/AttendanceService.php');
        $sessionView = file_get_contents(ROOTPATH . 'app/Modules/Attendance/Views/session.php');

        self::assertIsString($service);
        self::assertIsString($sessionView);

        foreach ([
            'PRESENT',
            'ABSENT',
            'LEAVE',
            'MEDICAL',
            'LATE',
            'RAIN_CANCELLED',
            'COACH_CANCELLED',
        ] as $status) {
            self::assertStringContainsString($status, $service);
            self::assertStringContainsString($status, $sessionView);
        }

        self::assertStringNotContainsString('EXCUSED', $sessionView);
        self::assertStringContainsString('data-attendance-bulk-form', $sessionView);
        self::assertStringNotContainsString('Capture roster snapshot', $sessionView);
        self::assertStringNotContainsString('name="snapshot_id"', $sessionView);
        self::assertStringContainsString('name="<?= csrf_token() ?>"', $sessionView);
        self::assertStringContainsString('Feedback / remark', $sessionView);
        self::assertStringContainsString('data-session-feedback-form', $sessionView);
    }

    public function testAttendanceCorrectionsPreserveSnapshotAndReasonRequirements(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/AttendanceService.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Attendance/Controllers/Attendance.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Attendance/Views/session.php');

        self::assertIsString($service);
        self::assertIsString($controller);
        self::assertIsString($view);
        self::assertStringContainsString('snapshot_id', $service);
        self::assertStringContainsString('correction reason', strtolower($service));
        self::assertStringContainsString('expected_version', $controller);
        self::assertStringContainsString('name="reason"', $view);
        self::assertStringContainsString('name="expected_version"', $view);
        self::assertStringContainsString('name="<?= csrf_token() ?>"', $view);
    }
}
