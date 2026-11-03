<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PerformanceImprovementIndexContractTest extends TestCase
{
    public function testPiiUsesApprovedMarkersAndSourceBackedProjection(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/PerformanceService.php');
        $coach = file_get_contents(ROOTPATH . 'app/Services/CoachManagementService.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/20260925000500_AddPerformanceApprovalMarkers.php');

        self::assertStringContainsString("->where('r.approved', 1)", $service);
        self::assertStringContainsString('abs($baseline)', $service);
        self::assertStringContainsString('A correction reason is required.', $service);
        self::assertStringContainsString('coachImprovementIndex($userId)', $coach);
        self::assertStringContainsString("'approved'", $migration);
        self::assertStringContainsString("'approved_by'", $migration);
        self::assertStringContainsString('Forward-only', $migration);
    }

    public function testPiiRemainsUnavailableWithoutAValidComparison(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/PerformanceService.php');

        self::assertStringContainsString('return $improvements === [] ? null', $service);
        self::assertStringContainsString('if ($baseline == 0.0)', $service);
        self::assertStringContainsString("coach.kpi.view", $service);
    }
}
