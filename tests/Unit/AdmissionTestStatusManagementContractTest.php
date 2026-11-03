<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdmissionTestStatusManagementContractTest extends TestCase
{
    public function testStatusManagementUsesReadableBadgesAndASeparateCsrfForm(): void
    {
        $root = dirname(__DIR__, 2);
        $view = file_get_contents($root . '/app/Modules/AdmissionTests/Views/index.php');
        $helper = file_get_contents($root . '/app/Helpers/common_helper.php');

        self::assertIsString($view);
        self::assertIsString($helper);
        foreach (['Change Status', 'data-admission-status-open', 'data-admission-status-modal', 'data-admission-status-form', 'name="status"', 'name="reason"', 'name="version"', 'csrf_field()', 'mcc-status-badge'] as $contract) {
            self::assertStringContainsString($contract, $view);
        }
        self::assertSame(['label' => 'Test submitted', 'class' => 'mcc-badge-warning'], mcc_admission_test_status_presentation('TEST_SUBMITTED'));
        self::assertSame(['label' => 'Approved for admission', 'class' => 'mcc-badge-success'], mcc_admission_test_status_presentation('APPROVED_FOR_ADMISSION'));
        self::assertSame(['label' => 'Rejected', 'class' => 'mcc-badge-danger'], mcc_admission_test_status_presentation('REJECTED'));
        self::assertSame(['label' => 'On hold', 'class' => 'mcc-badge-neutral'], mcc_admission_test_status_presentation('ON_HOLD'));
    }

    public function testStatusWorkflowKeepsAuthorizationConcurrencyAuditAndPendingEligibilityBoundaries(): void
    {
        $root = dirname(__DIR__, 2);
        $service = file_get_contents($root . '/app/Modules/AdmissionTests/Services/AdmissionTestService.php');
        $routes = file_get_contents($root . '/app/Modules/AdmissionTests/Config/Routes.php');
        $view = file_get_contents($root . '/app/Modules/AdmissionTests/Views/index.php');

        foreach ([$service, $routes, $view] as $source) {
            self::assertIsString($source);
        }
        foreach (['changeStatus', 'MANAGED_STATUSES', 'admission.update', 'FOR UPDATE', 'checkVersion', 'transStart', 'transRollback', 'auditPersistence', 'admission_test_status_changed', 'branch_id', 'version'] as $contract) {
            self::assertStringContainsString($contract, $service);
        }
        $javascript = file_get_contents($root . '/public/assets/js/app.js');
        self::assertIsString($javascript);
        foreach (['initAdmissionStatusModal', 'Accept: \'application/json\'', 'data-admission-status-submit', 'data-admission-status-error', 'submit.disabled = true', 'data-admission-status-badge'] as $contract) {
            self::assertStringContainsString($contract, $javascript);
        }
        self::assertStringContainsString("(:num)/status", $routes);
        self::assertStringContainsString('permission:admission.update', $routes);
        self::assertStringContainsString("strtoupper(\$status) === 'APPROVED_FOR_ADMISSION'", $view);
        self::assertStringNotContainsString("['TEST_SUBMITTED', 'MEETING_FOLLOW_UP', 'APPROVED_FOR_ADMISSION']", $view);
    }
}
