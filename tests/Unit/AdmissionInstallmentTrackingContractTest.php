<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdmissionInstallmentTrackingContractTest extends TestCase
{
    public function testFeeInstallmentTrackingUsesExistingTablesAndBranchScopedFilters(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/AdmissionInstallmentTrackingService.php');
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Admissions/Config/Routes.php');
        $navigation = file_get_contents(ROOTPATH . 'app/Services/NavigationService.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admissions/Views/fee_installments.php');
        $documentation = file_get_contents(ROOTPATH . 'docs/ADMISSION-FEE-INSTALLMENTS.md');
        $paymentService = file_get_contents(ROOTPATH . 'app/Services/AdmissionInstallmentPaymentService.php');
        $paymentView = file_get_contents(ROOTPATH . 'app/Modules/Admissions/Views/fee_installment_payment.php');
        $paymentMigration = file_get_contents(ROOTPATH . 'app/Database/Migrations/20261018000200_CreateAdmissionInstallmentPayments.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Admissions/Controllers/Browser.php');

        self::assertIsString($service);
        self::assertIsString($routes);
        self::assertIsString($navigation);
        self::assertIsString($view);
        self::assertIsString($documentation);
        self::assertIsString($paymentService);
        self::assertIsString($paymentView);
        self::assertIsString($paymentMigration);
        self::assertIsString($controller);
        self::assertStringContainsString('admission_coaching_installments', $service);
        self::assertStringContainsString('admission_hostel_installments', $service);
        self::assertStringContainsString('authorizedBranches(true)', $service);
        self::assertStringContainsString("a.admission_number", $service);
        self::assertStringContainsString("config('App')->appTimezone", $service);
        self::assertStringContainsString('dueState', $service);
        self::assertStringContainsString("'fee-installments'", $routes);
        self::assertStringContainsString("'Fee Installments'", $navigation);
        self::assertStringContainsString('name="applicant"', $view);
        self::assertStringContainsString('name="admission_number"', $view);
        self::assertStringContainsString('name="installment_type"', $view);
        self::assertStringContainsString('name="due_state"', $view);
        self::assertStringContainsString('name="payment_status"', $view);
        self::assertStringContainsString('data-show-label="Show Filters"', $view);
        self::assertStringContainsString('data-hide-label="Hide Filters"', $view);
        self::assertStringContainsString('mcc-follow-up-summary', $view);
        self::assertStringContainsString('All installments', $view);
        self::assertStringContainsString('Due today', $view);
        self::assertStringContainsString('mcc-admission-action-menu', $view);
        self::assertStringContainsString('class="mcc-action-caret"', $view);
        self::assertStringContainsString('aria-haspopup="true"', $view);
        self::assertStringNotContainsString('â–¾', $view);
        self::assertStringContainsString('initStandaloneAdmissionActionMenus', file_get_contents(ROOTPATH . 'public/assets/js/app.js'));
        self::assertStringContainsString('mcc-page-subtitle', file_get_contents(ROOTPATH . 'app/Views/staff_header.php'));
        self::assertStringContainsString('Not captured', $view);
        self::assertStringContainsString('mcc_admission_payment_status_presentation', $view);
        $detailView = file_get_contents(ROOTPATH . 'app/Modules/Admissions/Views/show.php');
        self::assertStringContainsString('Fee Installment Status', $detailView);
        self::assertSame(1, substr_count($detailView, 'Fee Installment Status'));
        self::assertStringNotContainsString('Planned schedule', $detailView);
        self::assertStringNotContainsString('coaching_installment_history', $detailView);
        self::assertStringNotContainsString('hostel_installment_history', $detailView);
        self::assertStringContainsString('Total received', $detailView);
        self::assertStringContainsString('Latest payment', $detailView);
        self::assertStringContainsString('Payment status', $detailView);
        self::assertStringContainsString('Record Payment', $detailView);
        self::assertStringContainsString('forAdmission', $service);
        self::assertStringContainsString('Record Payment', $view);
        self::assertStringContainsString('payment_history', $paymentView);
        self::assertStringContainsString('admission_installment_payments', $paymentService);
        self::assertStringContainsString('admission.update', $paymentService);
        self::assertStringContainsString('calculateStatus', $paymentService);
        self::assertStringContainsString("['installment_id']", $paymentService);
        self::assertStringContainsString("['installment_id']", $service);
        self::assertStringContainsString('paymentMissingId', $routes);
        self::assertStringContainsString("'receipt_number'", $paymentMigration);
        self::assertStringContainsString('correction_of_payment_id', $paymentMigration);
        self::assertStringContainsString('Manual payment receipts', $documentation);
        self::assertStringContainsString('public function recordPayment', $controller);
        self::assertStringContainsString('catch (Throwable $e)', $controller);
        self::assertStringContainsString("->withInput()", $controller);
        self::assertStringContainsString("->with('field_errors'", $controller);
        $paymentController = substr(
            $controller,
            strpos($controller, 'public function paymentPage'),
            strpos($controller, 'private function pageNumber') - strpos($controller, 'public function paymentPage'),
        );
        self::assertStringNotContainsString('show_error(', $paymentController);
        self::assertStringNotContainsString('view(', $paymentController);
        self::assertStringNotContainsString('payment gateway', strtolower($service . $view));
    }
}
