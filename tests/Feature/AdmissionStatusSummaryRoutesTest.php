<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;

final class AdmissionStatusSummaryRoutesTest extends CIUnitTestCase
{
    public function testAdmissionListRoutePassesSummaryAndFilterOptionsToTheView(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = (string) file_get_contents($root . '/app/Modules/Admissions/Controllers/Browser.php');
        $view = (string) file_get_contents($root . '/app/Modules/Admissions/Views/index.php');

        self::assertStringContainsString("'statusSummary' => service('admission')->statusSummaryForAuthorizedBranches(\$filters)", $controller);
        self::assertStringContainsString("\$statusOptions[] = ['value' => 'COMPLETED', 'label' => 'Completed'];", $controller);
        self::assertStringContainsString('statusSummary', $view);
        self::assertStringContainsString('statusOptions', $view);
    }

    public function testStatusCardsKeepSearchSourceAndBranchFilters(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Modules/Admissions/Views/index.php');

        self::assertStringContainsString("unset(\$summaryFilters['status'], \$summaryFilters['page'])", $view);
        self::assertStringContainsString("array_merge(\$summaryFilters, ['status' => \$status])", $view);
        self::assertStringContainsString("'q'", $view);
        self::assertStringContainsString("'source'", $view);
        self::assertStringContainsString("\$summaryFilters", $view);
    }
}
