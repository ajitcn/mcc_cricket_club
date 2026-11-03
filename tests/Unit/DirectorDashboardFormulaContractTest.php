<?php

declare(strict_types=1);

namespace Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class DirectorDashboardFormulaContractTest extends TestCase
{
    public function testDashboardUsesAuthorizedScopeAndSafeUnavailableStates(): void
    {
        $fromUtc = '$fromUtc';
        $periodEndUtc = '$periodEndUtc';
        $service = file_get_contents(ROOTPATH . 'app/Services/DashboardService.php');
        $model = file_get_contents(ROOTPATH . 'app/Models/DashboardModel.php');
        $view = file_get_contents(ROOTPATH . 'app/Views/dashboard.php');

        foreach ([$service, $model, $view] as $source) {
            self::assertIsString($source);
        }
        self::assertStringContainsString('authorizedBranches', $service);
        self::assertStringContainsString("in_array('Super Admin'", $service);
        self::assertStringContainsString('selectedMonth', $service);
        self::assertStringContainsString('selectedDate', $service);
        self::assertStringContainsString('countActiveCoaches', $model);
        self::assertStringContainsString('batchDistribution', $model);
        self::assertStringContainsString("where('b.branch_id = pba.branch_id', null, false)", $model);
        self::assertStringNotContainsString("where('b.is_active', 1)", $model);
        self::assertStringContainsString('branchDistribution', $model);
        self::assertStringContainsString('upcomingSessionsForPeriod', $model);
        self::assertStringContainsString('countUpcomingSessionsForPeriod', $model);
        self::assertStringContainsString('countGroupSessionsForPeriod', $model);
        self::assertStringContainsString('countPersonalSessionsForPeriod', $model);
        self::assertStringContainsString('coachDirectory', $model);
        self::assertStringContainsString('coachingDistribution', $model);
        self::assertStringContainsString("whereIn('s.status', ['scheduled', 'active', 'completed'])", $model);
        self::assertStringContainsString("having('COUNT(DISTINCT p.id) >', 1, false)", $model);
        self::assertStringContainsString("having('COUNT(DISTINCT p.id) =', 1, false)", $model);
        self::assertStringContainsString("where('s.status', 'scheduled')", $model);
        self::assertStringContainsString("where('s.starts_at >=', {$fromUtc})", $model);
        self::assertStringContainsString("where('s.starts_at <', {$periodEndUtc})", $model);
        self::assertStringContainsString('sessionPeriod', $service);
        self::assertStringContainsString('Scheduled after now in the selected period', $service);
        self::assertStringContainsString('Group coaching sessions', $service);
        self::assertStringContainsString('Personal coaching sessions', $service);
        self::assertStringContainsString("'coachingVisible' => false", $service);
        self::assertStringContainsString("'coachesVisible' => false", $service);
        self::assertStringNotContainsString("'Personal coaching',", $service);
        self::assertStringNotContainsString("'Group coaching',", $service);
        self::assertStringContainsString('Active Players by Batch', $view);
        self::assertStringContainsString('Branch totals', $view);
        self::assertStringContainsString('Active Coaches', $view);
        self::assertStringContainsString('branch_id', $view);
        self::assertStringContainsString('dashboard-unavailable', $view);
        self::assertStringContainsString('Not available', $view);
        self::assertStringContainsString('Medical metrics — Not available on this dashboard', $service);
        self::assertStringContainsString('unavailableMetrics', $view);
        self::assertStringContainsString('dashboard-director-grid', $view);
        self::assertStringContainsString('Coaching-type distribution', $view);
        self::assertStringContainsString('Coach list', $view);
        self::assertStringContainsString('dashboard-branch-card-grid', $view);
        self::assertStringContainsString('Age-wise Player distribution', $view);
        self::assertStringContainsString('Coaching type vs batch', $view);
        self::assertStringContainsString('Coach schedule', $view);
        self::assertStringContainsString('Coach workload overview', $view);
        self::assertStringContainsString('Profile specialization not available', $view);
        self::assertStringContainsString('dashboard-filter-summary', $view);
        self::assertStringContainsString("'title' => 'Director Dashboard'", $view);
        self::assertStringContainsString('dashboard-kpi-icon', $view);
        self::assertStringContainsString('dashboard-director-grid', file_get_contents(ROOTPATH . 'public/assets/css/app.css'));
        self::assertStringNotContainsString('medical_conditions', strtolower($service));
        self::assertStringNotContainsString('medical_conditions', strtolower($view));
        self::assertStringNotContainsString('medical_documents', strtolower($service));
        self::assertStringNotContainsString('medical_documents', strtolower($view));
        self::assertStringNotContainsString('FROM medical', strtolower($service));
    }

    public function testDashboardInitializesMccDayAndMonthBoundariesBeforeUtcQueries(): void
    {
        $timezone = new DateTimeZone(config('App')->appTimezone);
        $dayStart = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            '2026-10-11',
            $timezone
        );
        $monthStart = DateTimeImmutable::createFromFormat(
            '!Y-m',
            '2026-10',
            $timezone
        );
        $dayStartUtc = $dayStart instanceof DateTimeImmutable
            ? $dayStart->setTimezone(new DateTimeZone('UTC'))
            : null;
        $monthStartUtc = $monthStart instanceof DateTimeImmutable
            ? $monthStart->setTimezone(new DateTimeZone('UTC'))
            : null;
        $service = file_get_contents(ROOTPATH . 'app/Services/DashboardService.php');

        self::assertInstanceOf(DateTimeImmutable::class, $dayStart);
        self::assertInstanceOf(DateTimeImmutable::class, $monthStart);
        self::assertInstanceOf(DateTimeImmutable::class, $dayStartUtc);
        self::assertInstanceOf(DateTimeImmutable::class, $monthStartUtc);
        self::assertSame('UTC', $dayStartUtc->getTimezone()->getName());
        self::assertSame('UTC', $monthStartUtc->getTimezone()->getName());
        self::assertIsString($service);
        self::assertStringContainsString("createFromFormat('!Y-m-d'", $service);
        self::assertStringContainsString("createFromFormat('!Y-m'", $service);
        self::assertStringContainsString("new DateTimeZone(config('App')->appTimezone)", $service);
        self::assertStringContainsString("setTimezone(new DateTimeZone('UTC'))", $service);
    }
}
