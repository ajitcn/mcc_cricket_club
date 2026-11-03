<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class BranchOptionsContractTest extends TestCase
{
    public function testSharedBranchContextExposesAuthorizedIdsAndReadableNames(): void
    {
        $context = file_get_contents(ROOTPATH . 'app/Services/BranchContextService.php');
        $admin = file_get_contents(ROOTPATH . 'app/Services/AdminService.php');
        $match = file_get_contents(ROOTPATH . 'app/Services/MatchManagementService.php');
        $development = file_get_contents(ROOTPATH . 'app/Services/PlayerDevelopmentService.php');

        self::assertIsString($context);
        self::assertIsString($admin);
        self::assertIsString($match);
        self::assertIsString($development);
        self::assertStringContainsString('public function authorizedBranchIds(bool $activeOnly = false): array', $context);
        self::assertStringContainsString('b.id, b.name, b.code, b.is_active', $context);
        self::assertStringContainsString('service(\'branchContext\')->authorizedBranchIds(true)', $admin);
        self::assertStringContainsString('service(\'branchContext\')->authorizedBranchIds(true)', $match);
        self::assertStringContainsString('service(\'branchContext\')->authorizedBranchIds(true)', $development);
    }

    public function testWorkflowBranchOptionsAreActiveOnlyAndSessionFiltersStartWithAllBranches(): void
    {
        $match = file_get_contents(ROOTPATH . 'app/Services/MatchManagementService.php');
        $trial = file_get_contents(ROOTPATH . 'app/Services/TrialService.php');
        $attendance = file_get_contents(ROOTPATH . 'app/Modules/Attendance/Models/AttendanceModel.php');
        $scheduling = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Models/SessionModel.php');

        self::assertIsString($match);
        self::assertIsString($trial);
        self::assertIsString($attendance);
        self::assertIsString($scheduling);
        self::assertStringContainsString("->where('is_active', 1)", $match);
        self::assertStringContainsString("->where('is_active', 1)", $trial);
        self::assertStringContainsString("->whereIn('id', \$branchIds)", $attendance);
        self::assertStringContainsString("->whereIn('id', \$branchIds)", $scheduling);
    }
}
