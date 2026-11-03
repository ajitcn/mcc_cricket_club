<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ReportBranchScopeContractTest extends TestCase
{
    public function testReportServiceAppliesRequestedAuthorizedBranchScope(): void
    {
        $service = file_get_contents(APPPATH . 'Services/ReportService.php');

        self::assertStringContainsString('$' . "filters['branch_id']", $service);
        self::assertStringContainsString("throw new RuntimeException('Forbidden')", $service);
        self::assertStringContainsString('whereIn($branchColumn, $branches ?: [0])', $service);
    }
}
