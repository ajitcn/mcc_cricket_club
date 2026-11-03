<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\AdmissionService;
use CodeIgniter\Test\CIUnitTestCase;

final class AdmissionTaxInformationTest extends CIUnitTestCase
{
    public function testTaxInformationUsesTheCapturedTaxableFeeAndRate(): void
    {
        $service = new AdmissionService();

        self::assertSame([
            'total_taxable_fee' => '1500.00',
            'tax_rate' => '18.00',
            'calculated_tax_amount' => '270.00',
            'total_including_tax' => '1770.00',
        ], $service->calculateTaxInformation([
            'training_fee' => '1000.00',
            'hostel_charges' => '500.00',
            'total_taxable_fee' => '1500.00',
            'tax_rate' => '18',
        ]));
    }

    public function testTaxInformationDefaultsTaxableFeeToCoachingAndHostelTotal(): void
    {
        $service = new AdmissionService();

        self::assertSame([
            'total_taxable_fee' => '300.30',
            'tax_rate' => null,
            'calculated_tax_amount' => '0.00',
            'total_including_tax' => '300.30',
        ], $service->calculateTaxInformation([
            'training_fee' => '100.10',
            'hostel_charges' => '200.20',
        ]));
    }

    public function testTaxInformationRejectsRatesOutsideZeroToOneHundred(): void
    {
        $service = new AdmissionService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid tax rate.');
        $service->calculateTaxInformation([
            'training_fee' => '1000.00',
            'tax_rate' => '100.01',
        ]);
    }

    public function testAdmissionFeeViewContainsTaxFieldsAndSharedHooks(): void
    {
        $view = (string) file_get_contents(
            ROOTPATH . 'app/Modules/Admissions/Views/_fees_fields.php',
        );

        foreach ([
            'name="total_taxable_fee"',
            'name="tax_rate"',
            'data-admission-calculated-tax',
            'data-admission-taxable-fee-manual',
            'placeholder="Enter tax rate"',
            'Taxable Amount',
            'class="col-12 col-md-4 mcc-admission-fee-column mcc-admission-fee-tax-column"',
            'mcc-admission-fee-tax-column',
            'mcc-admission-fee-error-slot',
        ] as $contract) {
            self::assertStringContainsString($contract, $view);
        }

        self::assertStringNotContainsString('Total Including Tax', $view);
        self::assertSame(3, substr_count($view, 'col-12 col-md-4 mcc-admission-fee-column'));
        self::assertSame(3, substr_count($view, 'mcc-admission-fee-tax-column'));
        self::assertSame(3, substr_count($view, 'mcc-admission-fee-error-slot'));
        self::assertLessThan(
            strpos($view, 'name="tax_rate"'),
            strpos($view, 'name="total_taxable_fee"'),
        );
        self::assertLessThan(
            strpos($view, 'data-admission-calculated-tax'),
            strpos($view, 'name="tax_rate"'),
        );
        self::assertStringContainsString('id="admission-calculated-tax-amount"', $view);
        self::assertStringContainsString('readonly', $view);

        $detailView = (string) file_get_contents(
            ROOTPATH . 'app/Modules/Admissions/Views/show.php',
        );
        $pdfView = (string) file_get_contents(
            ROOTPATH . 'app/Modules/Admissions/Views/pdf.php',
        );

        foreach ([$detailView, $pdfView] as $outputView) {
            self::assertStringContainsString('Taxable Amount', $outputView);
            self::assertStringContainsString('Tax Rate', $outputView);
            self::assertStringContainsString('Calculated Tax Amount', $outputView);
            self::assertStringNotContainsString('Total Including Tax', $outputView);
        }
    }
}
