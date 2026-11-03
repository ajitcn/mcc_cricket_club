<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdmissionStatusSummaryContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__, 2);
    }

    public function testSummaryUsesTheAuthorizedAdmissionQueryAndFiveReadableCards(): void
    {
        $service = (string) file_get_contents($this->root . '/app/Services/AdmissionService.php');
        $controller = (string) file_get_contents($this->root . '/app/Modules/Admissions/Controllers/Browser.php');
        $view = (string) file_get_contents($this->root . '/app/Modules/Admissions/Views/index.php');

        self::assertStringContainsString('statusSummaryForAuthorizedBranches', $controller);
        self::assertStringContainsString("unset(\$filters['status'], \$filters['page'])", $service);
        self::assertStringContainsString("service('branchContext')->authorizedBranchIds(true)", $service);
        self::assertStringContainsString("whereIn('a.status', \$card['statuses'])", $service);
        self::assertStringContainsString("'label' => 'Intake / Pending'", $service);
        self::assertStringContainsString("'label' => 'Approved'", $service);
        self::assertStringContainsString("'label' => 'Completed'", $service);
        self::assertStringContainsString("'label' => 'Converted'", $service);
        self::assertStringContainsString("'label' => 'Rejected / Closed'", $service);
        self::assertStringContainsString('mcc-admission-status-summary', $view);
        self::assertStringContainsString("'class' => 'mcc-status-approved'", $service);
        self::assertStringContainsString("'class' => 'mcc-status-completed'", $service);
        self::assertStringContainsString("'class' => 'mcc-status-converted'", $service);
        self::assertStringContainsString("'class' => 'mcc-status-rejected'", $service);
    }

    public function testSummaryLinksPreserveAuthorizedFiltersAndResetPagination(): void
    {
        $view = (string) file_get_contents($this->root . '/app/Modules/Admissions/Views/index.php');

        self::assertStringContainsString("unset(\$summaryFilters['status'], \$summaryFilters['page'])", $view);
        self::assertStringContainsString("array_merge(\$summaryFilters, ['status' => \$status])", $view);
        self::assertStringContainsString("href=\"<?= esc(\$statusUrl", $view);
        self::assertStringContainsString('aria-current=', $view);
    }

    public function testAdmissionStatusPresentationNeverRendersDatabaseCodes(): void
    {
        $helper = (string) file_get_contents($this->root . '/app/Helpers/common_helper.php');
        $view = (string) file_get_contents($this->root . '/app/Modules/Admissions/Views/index.php');

        self::assertStringContainsString('mcc_admission_status_presentation', $helper);
        self::assertStringContainsString("<?= esc(\$statusPresentation['label']) ?>", $view);
        self::assertStringNotContainsString("<?= esc(\$admission['status']) ?>", $view);
    }
}
