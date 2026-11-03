<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\AdmissionInstallmentPaymentService;
use PHPUnit\Framework\TestCase;

final class AdmissionInstallmentPaymentTest extends TestCase
{
    public function testPaymentStatusIsCalculatedFromReceivedAmount(): void
    {
        $service = new AdmissionInstallmentPaymentService();

        self::assertSame('Not captured', $service->calculateStatus('1000.00', '0.00'));
        self::assertSame('Partially paid', $service->calculateStatus('1000.00', '500.00'));
        self::assertSame('Paid', $service->calculateStatus('1000.00', '1000.00'));
        self::assertSame('Overpaid', $service->calculateStatus('1000.00', '1000.01'));
    }

    public function testPaymentMethodsMatchTheApprovedManualReceiptOptions(): void
    {
        $service = new AdmissionInstallmentPaymentService();

        self::assertSame([
            'Cash',
            'Bank transfer',
            'UPI',
            'Cheque',
            'Card',
            'Other',
        ], $service->paymentMethods());
    }

    public function testInstallmentIdsNormalizeToPositiveIntegersOnly(): void
    {
        $service = new AdmissionInstallmentPaymentService();

        self::assertSame(17, $service->normalizeInstallmentId('17'));
        self::assertSame(17, $service->normalizeInstallmentId(17));

        foreach ([0, '0', '', 'abc', '-1', '1.5', null] as $invalid) {
            try {
                $service->normalizeInstallmentId($invalid);
                self::fail('Expected invalid installment ID to be rejected.');
            } catch (\RuntimeException $exception) {
                self::assertSame('Invalid installment.', $exception->getMessage());
            }
        }
    }

    public function testPaymentStatusPresentationUsesSharedMccBadgeClasses(): void
    {
        self::assertSame(
            ['label' => 'Not captured', 'class' => 'mcc-status-payment-neutral'],
            mcc_admission_payment_status_presentation('Not captured'),
        );
        self::assertSame(
            ['label' => 'Partially paid', 'class' => 'mcc-status-payment-partial'],
            mcc_admission_payment_status_presentation('Partially paid'),
        );
        self::assertSame(
            ['label' => 'Paid', 'class' => 'mcc-status-payment-paid'],
            mcc_admission_payment_status_presentation('Paid'),
        );
        self::assertSame(
            ['label' => 'Overpaid', 'class' => 'mcc-status-payment-overpaid'],
            mcc_admission_payment_status_presentation('Overpaid'),
        );
    }
}
