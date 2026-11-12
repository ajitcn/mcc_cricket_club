<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class DirectorDashboardRoutesTest extends TestCase
{
    public function testDashboardUsesAuthenticatedHomeRouteAndFilters(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Controllers/Home.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/DashboardService.php');
        $view = file_get_contents(ROOTPATH . 'app/Views/dashboard.php');

        self::assertIsString($controller);
        self::assertIsString($service);
        self::assertIsString($view);
        self::assertStringContainsString('isLoggedIn', $controller);
        self::assertStringContainsString('getGet()', $controller);
        self::assertStringContainsString('service(\'dashboard\')->data', $controller);
        self::assertStringContainsString('branch_id', $view);
        self::assertStringContainsString('month', $view);
        self::assertStringContainsString('date', $view);
        self::assertStringContainsString('Upcoming sessions', $service);
        self::assertStringContainsString('upcomingSessionsForPeriod', $service);
        self::assertStringContainsString('countGroupSessionsForPeriod', $service);
        self::assertStringContainsString('countPersonalSessionsForPeriod', $service);
        self::assertStringContainsString('branchDistribution', $service);
        self::assertStringContainsString('coachDirectory', $service);
        self::assertStringContainsString('dashboard-director-grid', $view);
        self::assertStringContainsString('Coaching-type distribution', $view);
        self::assertStringContainsString('Coach list', $view);
        self::assertStringContainsString('Age-wise Player distribution', $view);
        self::assertStringContainsString('Coaching type vs batch', $view);
        self::assertStringContainsString('Coach schedule', $view);
        self::assertStringContainsString('Coach workload overview', $view);
        self::assertStringContainsString('dashboard-kpi-icon', $view);
        self::assertStringContainsString("'title' => 'Dashboard'", $view);
        self::assertStringContainsString("'Total leads'", $view);
        self::assertStringContainsString("'Open admissions'", $view);
        self::assertStringContainsString('coachWorkload(', $service);
    }

    public function testSourceBackedDashboardPanelsRemainVisible(): void
    {
        $styles = file_get_contents(ROOTPATH . 'public/assets/css/app.css');

        self::assertIsString($styles);
        self::assertStringNotContainsString(
            ".dashboard-primary-grid,\n.dashboard-analytics-grid,\n.dashboard-recent {\n    display: none;",
            $styles,
        );
    }
}
