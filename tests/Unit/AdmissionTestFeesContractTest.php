<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdmissionTestFeesContractTest extends TestCase
{
    public function testFeesWorkflowHasIndependentStorageAndSecurityBoundaries(): void
    {
        $root = dirname(__DIR__, 2);
        $service = file_get_contents($root . '/app/Modules/AdmissionTests/Services/AdmissionTestService.php');
        $migration = file_get_contents($root . '/app/Database/Migrations/20261021000100_AddAdmissionTestFees.php');
        $nullableMigration = file_get_contents($root . '/app/Database/Migrations/20261029000100_AllowNullableInstallmentValues.php');
        $view = file_get_contents($root . '/app/Modules/AdmissionTests/Views/_fees.php');
        $routes = file_get_contents($root . '/app/Modules/AdmissionTests/Config/Routes.php');
        $css = file_get_contents($root . '/public/assets/css/app.css');
        $javascript = file_get_contents($root . '/public/assets/js/app.js');

        foreach ([$service, $migration, $nullableMigration, $view, $routes, $css, $javascript] as $source) {
            self::assertIsString($source);
        }
        foreach ([
            'saveFees',
            'checkVersion',
            'transStart',
            'transRollback',
            'auditPersistence',
            'admission_test_coaching_installments',
            'admission_test_hostel_installments',
            'fee_update_reason',
            'Instalment Plan',
            'Full Payment',
            'Not applicable',
            'data-mcc-date-picker',
            'data-admission-fee-total',
        ] as $contract) {
            self::assertStringContainsString($contract, $service . $migration . $view);
        }
        self::assertStringContainsString("(:num)/fees", $routes);
        self::assertStringContainsString('permission:admission.update', $routes);
        self::assertStringContainsString('copyFeeSchedulesToAdmission', $service);
        self::assertStringNotContainsString('admission_fee', $view);
        self::assertStringContainsString("'null' => true", $nullableMigration);
    }

    public function testFeesUiIsScopedAndResponsiveWithoutChangingAdmissionStyles(): void
    {
        $root = dirname(__DIR__, 2);
        $css = file_get_contents($root . '/public/assets/css/app.css');
        $javascript = file_get_contents($root . '/public/assets/js/app.js');
        $view = file_get_contents($root . '/app/Modules/AdmissionTests/Views/_fees.php');

        self::assertIsString($css);
        foreach ([
            '.mcc-admission-test-fees',
            'overflow-x: auto',
            'table-layout: fixed',
            'overflow-wrap: anywhere',
            '@media (max-width: 767.98px)',
            'z-index: 4002',
        ] as $contract) {
            self::assertStringContainsString($contract, $css);
        }
        self::assertStringContainsString('flex-direction: column', $css);
        self::assertStringContainsString('data-admission-test-schedule', file_get_contents($root . '/app/Modules/AdmissionTests/Views/_fees.php'));
        self::assertStringContainsString('initAdmissionTestInstallmentSchedules', $javascript);
        self::assertStringContainsString('data-admission-test-saved-rows', $view);
        self::assertStringContainsString('coaching_installment_ids[]', $view);
        self::assertStringContainsString('hostel_installment_ids[]', $view);
        self::assertStringContainsString('data-admission-installment-ids-field="coaching_installment_ids[]"', $view);
        self::assertStringContainsString('data-admission-installment-ids-field="hostel_installment_ids[]"', $view);
        self::assertStringContainsString('data-admission-installment-plan-field="hostel_installment_plan"', $view);
        self::assertStringContainsString('data-admission-installment-plan-value', $view);
        self::assertStringContainsString('data-admission-installment-row', $view);
        self::assertStringContainsString('data-admission-installment-persisted-ids', $view);
        self::assertStringContainsString('data-admission-test-remove-row', $view);
        self::assertStringContainsString('removed_installment_ids', $view);
        self::assertStringContainsString('removed_hostel_installment_ids', $view);
        self::assertStringContainsString('data-admission-test-remove-row', $javascript);
        self::assertStringContainsString('data-admission-installment-ids', $javascript);
        self::assertStringContainsString('rowPersistedIds', $javascript);
        self::assertStringContainsString('installmentPlanValue', $javascript);
        self::assertStringContainsString('planField', $javascript);
        self::assertStringContainsString('data-admission-removed-installment-id', $javascript);
        self::assertStringContainsString("rows.querySelectorAll('[data-admission-installment-row]').length > 1", $javascript);
        self::assertStringContainsString('initDatePickers(row)', $javascript);
        self::assertStringContainsString('admissionTestFeesSubmitting', $javascript);
        self::assertStringContainsString('admissionTestFeesSubmitBound', $javascript);
        self::assertStringContainsString('isInstallmentValueInput', $javascript);
    }

    public function testAdmissionTestFeeMarkupStartsWithOneRowOnlyForInstallmentPlans(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/app/Modules/AdmissionTests/Views/_fees.php');

        self::assertIsString($view);
        self::assertStringContainsString('$defaultRows = [[\'year_number\' => 1]]', $view);
        self::assertStringContainsString("\$coachingPlan === \$installmentPlanValue", $view);
        self::assertStringContainsString("\$hostelPlan === \$installmentPlanValue", $view);
        self::assertStringContainsString('type="button"', $view);
    }

    public function testFeeValidationKeepsSubmittedTestDataOutsideTheFeeUpdatePath(): void
    {
        $root = dirname(__DIR__, 2);
        $service = file_get_contents($root . '/app/Modules/AdmissionTests/Services/AdmissionTestService.php');

        self::assertIsString($service);
        self::assertStringContainsString("['TEST_SUBMITTED', 'MEETING_FOLLOW_UP', 'APPROVED_FOR_ADMISSION']", $service);
        self::assertStringContainsString("'admission_tests'", $service);
        self::assertStringNotContainsString(
            "admission_test_responses')->delete",
            substr($service, strpos($service, 'public function saveFees')),
        );
        self::assertStringContainsString('Duplicate installment row.', $service);
        self::assertStringContainsString("'Duplicate installment row.' => 'Each installment year can be submitted only once.'", file_get_contents($root . '/app/Controllers/StaffController.php'));
        foreach (['normalizedRemovedInstallmentIds', 'installmentRowsById', 'syncFeeRows', 'removed_installment_ids', 'coaching_installment_ids', 'hostel_installment_ids'] as $contract) {
            self::assertStringContainsString($contract, $service);
        }
    }

    public function testInstallmentNormalizationRejectsDuplicateRowsButAllowsExplicitRows(): void
    {
        $service = new \App\Modules\AdmissionTests\Services\AdmissionTestService();
        $method = new \ReflectionMethod($service, 'normalizeFeeRows');
        $method->setAccessible(true);
        $base = [
            'installment_plan' => 'Instalment Plan',
            'installment_year' => [1],
            'installment_1_date' => ['2027-01-01'],
            'installment_1' => ['100'],
            'installment_2_date' => ['2027-02-01'],
            'installment_2' => ['100'],
            'installment_3_date' => ['2027-03-01'],
            'installment_3' => ['100'],
            'installment_4_date' => ['2027-04-01'],
            'installment_4' => ['100'],
        ];
        self::assertCount(1, $method->invoke($service, $base, 'installment_'));

        $twoRows = $base;
        $twoRows['installment_year'] = [1, 2];
        foreach (range(1, 4) as $number) {
            $twoRows['installment_' . $number . '_date'][] = '2028-0' . $number . '-01';
            $twoRows['installment_' . $number][] = '200';
        }
        self::assertCount(2, $method->invoke($service, $twoRows, 'installment_'));

        $duplicate = $base;
        $duplicate['installment_year'] = [1, 1];
        foreach (range(1, 4) as $number) {
            $duplicate['installment_' . $number . '_date'][] = $base['installment_' . $number . '_date'][0];
            $duplicate['installment_' . $number][] = $base['installment_' . $number][0];
        }
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Duplicate installment row.');
        $method->invoke($service, $duplicate, 'installment_');
    }

    public function testBlankRowsAreIgnoredAndPartialRowsNormalizeMissingValuesToNull(): void
    {
        $service = new \App\Modules\AdmissionTests\Services\AdmissionTestService();
        $method = new \ReflectionMethod($service, 'normalizeFeeRows');
        $method->setAccessible(true);
        $blank = [
            'installment_plan' => 'Instalment Plan',
            'installment_year' => [1],
        ];
        foreach (range(1, 4) as $number) {
            $blank['installment_' . $number . '_date'] = [''];
            $blank['installment_' . $number] = [''];
        }

        self::assertSame([], $method->invoke($service, $blank, 'installment_'));

        $partial = $blank;
        $partial['installment_1_date'] = ['2027-01-01'];
        $rows = $method->invoke($service, $partial, 'installment_');

        self::assertCount(1, $rows);
        self::assertSame('2027-01-01', $rows[0]['installment_1_date']);
        self::assertNull($rows[0]['installment_1']);
        self::assertNull($rows[0]['installment_2_date']);
        self::assertNull($rows[0]['installment_2']);
    }
}
