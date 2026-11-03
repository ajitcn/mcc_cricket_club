<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdmissionPdfContractTest extends TestCase
{
    public function testRendererProducesPdfWithStructuredContent(): void
    {
        $service = new \App\Services\AdmissionPdfService();
        $logoPath = dirname(__DIR__, 2) . '/public/assets/img/logo.png';
        $signatureDataUrl = 'data:image/png;base64,' . base64_encode((string) file_get_contents($logoPath));
        $pdf = $service->render([
            'admission_number' => 'MCC-00056',
            'admission_date_display' => '18-10-2026',
            'branch_name' => 'Santacruz',
            'applicant_full_name' => 'Aarav Sharma',
            'applicant_email' => 'aarav@example.com',
            'applicant_phone' => '+91 9000000000',
            'applicant_date_of_birth' => '01-01-2012',
            'age' => '14',
            'father_name' => 'Rohan Sharma',
            'parent_mobile' => '+91 9111111111',
            'current_address' => 'Mumbai, Maharashtra',
            'playing_role' => 'All-rounder',
            'coaching_plan_label' => 'Annual coaching plan',
            'coaching_type' => 'Group coaching',
            'coaching_delivery' => 'Indoors and outdoors',
            'training_fee' => '50000.00',
            'hostel_charges' => '25000.00',
            'installment_plan' => 'Four instalments',
            'hostel_installment_plan' => 'Two instalments',
            'total_coaching_hostel_fee' => '75000.00',
            'student_signature' => $signatureDataUrl,
            'parent_signature' => $signatureDataUrl,
            'authorized_signature' => $signatureDataUrl,
            'confirmation_notes' => 'Verified for PDF rendering.',
            'coaching_installment_history' => [[
                'year_label' => 'Year 1',
                'installment_1' => '12500.00',
                'installment_1_date' => '01-11-2026',
            ]],
            'hostel_installment_history' => [[
                'year_label' => 'Year 1',
                'installment_1' => '12500.00',
                'installment_1_date' => '01-11-2026',
            ]],
        ]);

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertStringContainsString('Mumbai Cricket Club', $pdf);
        self::assertStringContainsString('Admission Form', $pdf);
        self::assertStringContainsString('MCC-00056', $pdf);
        self::assertStringContainsString('Personal Details', $pdf);
        self::assertStringContainsString('Coaching Instalments', $pdf);
        self::assertStringContainsString('Terms and Conditions', $pdf);
        self::assertGreaterThanOrEqual(4, substr_count($pdf, '/Subtype /Image'));
        self::assertStringNotContainsString('<html', $pdf);
        self::assertGreaterThanOrEqual(3, substr_count($pdf, '/Type /Page '));
        self::assertGreaterThan(5000, strlen($pdf));

        file_put_contents(dirname(__DIR__, 2) . '/tmp/pdfs/admission-redesign-test.pdf', $pdf);
    }

    public function testAdmissionPdfRendererContainsEveryRequiredSectionAndReusesTermsPartial(): void
    {
        $root = dirname(__DIR__, 2);
        $service = (string) file_get_contents($root . '/app/Services/AdmissionPdfService.php');
        $template = (string) file_get_contents($root . '/app/Modules/Admissions/Views/pdf.php');
        $terms = (string) file_get_contents($root . '/app/Modules/Admissions/Views/_terms_and_conditions.php');

        foreach ([
            'Admission No.',
            'Personal Details',
            'Cricket Information',
            'Coaching Plan',
            'Fees',
            'Coaching Instalments',
            'Hostel Instalments',
            'Declarations and Signatures',
            'Student signature',
            'Parent/Guardian signature',
            'Club Authorized Person signature',
            'Terms and Conditions',
        ] as $section) {
            self::assertTrue(
                str_contains($service, $section) || str_contains($template, $section),
                'Missing PDF section: ' . $section
            );
        }

        self::assertStringContainsString("_terms_and_conditions", $template);
        self::assertStringContainsString("'admission_number'", $template);
        self::assertStringContainsString('pdf-table', $template);
        self::assertStringContainsString('pdf-signatures', $template);
        self::assertStringContainsString('pdf-header', $template);
        self::assertStringContainsString('pdf-details', $template);
        self::assertStringContainsString("estimated_personal_session", $template);
        self::assertStringContainsString("estimated_elite_session", $template);
        self::assertStringContainsString("public/assets/img/logo.png", $template);
        self::assertStringContainsString('pdf-signature-box', $template);
        self::assertStringContainsString('Coaching Payment Plan', $template);
        self::assertStringContainsString('Hostel Payment Plan', $template);
        self::assertStringContainsString('Club shall not be responsible for any injury', $terms);
        self::assertStringContainsString('Request for refund shall not be entertained under any circumstances.', $terms);
        self::assertStringContainsString('exclusive jurisdiction of the court of Mumbai only.', $terms);
        self::assertStringContainsString('Enclosure: 1) Copy of Birth Certificate 2) Copy of Aadhar Card 3) 2 Photographs affix', $terms);
    }

    public function testAdmissionDownloadRouteIsPermissionGatedAndUsesCanonicalNumber(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/app/Modules/Admissions/Config/Routes.php');
        $controller = (string) file_get_contents($root . '/app/Modules/Admissions/Controllers/Browser.php');
        $show = (string) file_get_contents($root . '/app/Modules/Admissions/Views/show.php');
        $list = (string) file_get_contents($root . '/app/Modules/Admissions/Views/index.php');

        self::assertStringContainsString("'(:num)/download'", $routes);
        self::assertStringContainsString("permission:admission.view", $routes);
        self::assertStringContainsString('public function download(int $id)', $controller);
        self::assertStringContainsString("admission['admission_number']", $controller);
        self::assertStringContainsString('application/pdf', $controller);
        self::assertStringContainsString(
            "'MCC-' . " . '$safeNumber' . " . '.pdf'",
            $controller
        );
        self::assertStringContainsString('Download PDF', $show);
        self::assertStringContainsString('Download PDF', $list);
        self::assertStringNotContainsString('Download Admission with Terms &amp; Conditions', $show);
        self::assertStringNotContainsString('Download Admission with Terms &amp; Conditions', $list);
    }
}
