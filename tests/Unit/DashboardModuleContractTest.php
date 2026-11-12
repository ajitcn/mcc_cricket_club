<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DashboardModuleContractTest extends TestCase
{
    public function testDashboardUsesExistingPersistedSourcesAndPermissions(): void
    {
        $service = file_get_contents(APPPATH . 'Services/DashboardService.php');
        $model = file_get_contents(APPPATH . 'Models/DashboardModel.php');

        self::assertStringContainsString("\$can('match.view')", $service);
        self::assertStringContainsString("\$can('tournament.view')", $service);
        self::assertStringContainsString("\$can('development.view')", $service);
        self::assertStringContainsString("'matches'", $model);
        self::assertStringContainsString("'tournaments'", $model);
        self::assertStringNotContainsString('fabricated', strtolower($service));
        self::assertStringNotContainsString("'excused'", $service);
    }

    public function testDashboardAttendanceUsesCurrentStatusBuckets(): void
    {
        $model = file_get_contents(APPPATH . 'Models/DashboardModel.php');
        $view = file_get_contents(APPPATH . 'Views/dashboard.php');

        self::assertStringContainsString("'leave' => 0", $model);
        self::assertStringContainsString("'medical' => 0", $model);
        self::assertStringContainsString("'leave', 'medical'", $view);
        self::assertStringNotContainsString("'excused'", $model);
        self::assertStringNotContainsString("'excused'", $view);
    }

    public function testDashboardUsesConfiguredTimezoneForStoredUtcActivity(): void
    {
        $model = file_get_contents(APPPATH . 'Models/DashboardModel.php');
        $view = file_get_contents(ROOTPATH . 'app/Views/dashboard.php');

        self::assertStringContainsString("config('App')->appTimezone", $model);
        self::assertStringContainsString("new DateTimeZone('UTC')", $model);
        self::assertStringContainsString("\$formatDate(\$item['starts_at'], 'd-m-Y', true)", $view);
        self::assertStringContainsString("\$formatDate(\$item['starts_at'] ?? null, 'd-m-Y', true)", $view);
    }

    public function testDashboardCoachWorkloadUsesTransparentSessionCounts(): void
    {
        $service = file_get_contents(APPPATH . 'Services/DashboardService.php');
        $model = file_get_contents(APPPATH . 'Models/DashboardModel.php');
        $view = file_get_contents(ROOTPATH . 'app/Views/dashboard.php');

        self::assertStringContainsString('coachWorkload(', $service);
        self::assertStringContainsString('COUNT(*) AS session_count', $model);
        self::assertStringContainsString('dashboard-workload-table', $view);
        self::assertStringNotContainsString('No approved workload unit', $view);
    }
}
