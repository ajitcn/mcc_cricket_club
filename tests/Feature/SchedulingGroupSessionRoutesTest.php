<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class SchedulingGroupSessionRoutesTest extends TestCase
{
    public function testGroupSessionRoutesAndAttendanceRouteRemainExplicit(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Config/Routes.php');
        $attendanceRoutes = file_get_contents(ROOTPATH . 'app/Modules/Attendance/Config/Routes.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Controllers/Browser.php');
        $create = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Views/create.php');
        $edit = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Views/edit.php');
        $list = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Views/index.php');
        $detail = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Views/show.php');

        self::assertIsString($routes);
        self::assertIsString($attendanceRoutes);
        self::assertIsString($controller);
        self::assertIsString($create);
        self::assertIsString($edit);
        self::assertIsString($list);
        self::assertIsString($detail);
        self::assertStringContainsString("group('sessions'", $routes);
        self::assertStringContainsString("get('new'", $routes);
        self::assertStringContainsString("get('(:num)/edit'", $routes);
        self::assertStringContainsString('Browser::createPage', $routes);
        self::assertStringContainsString('Browser::editPage/$1', $routes);
        self::assertStringContainsString("group('attendance'", $attendanceRoutes);
        self::assertStringContainsString("get('sessions/(:num)'", $attendanceRoutes);
        self::assertStringContainsString('Attendance::sessionPage/$1', $attendanceRoutes);
        self::assertStringContainsString("post('sessions/(:num)/save'", $attendanceRoutes);
        self::assertStringContainsString("permission:attendance.view", $attendanceRoutes);
        self::assertStringContainsString('sessionFormOptions', $controller);
        self::assertStringContainsString('attendance_roster', $controller);
        self::assertStringContainsString("attendance.record", $controller);
        self::assertStringContainsString('name="session_type"', $create);
        self::assertStringContainsString('Choose session type', $create);
        self::assertStringContainsString('value="one_to_one"', $create);
        self::assertStringContainsString('value="group"', $create);
        self::assertStringContainsString('name="session_type"', $edit);
        self::assertStringContainsString('Session Type', $list);
        self::assertStringContainsString('Session Type', $detail);
    }
}
