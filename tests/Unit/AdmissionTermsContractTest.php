<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdmissionTermsContractTest extends TestCase
{
    public function testTermsViewContainsTheAuthoritativeAdmissionTextAndSections(): void
    {
        $root = dirname(__DIR__, 2);
        $terms = (string) file_get_contents($root . '/app/Modules/Admissions/Views/_terms_and_conditions.php');

        foreach ([
            'Club shall not be responsible for any injury or damage caused to the player during the club activities, Trainings &amp; playing matches either infield or outside the field.',
            'Rules And Regulations:',
            'Below Coaching plan is decided with you before you take admission and proceed with the formalities and payments.',
            'Request for refund shall not be entertained under any circumstances.',
            'Fee once paid towards the admission is non-refundable/ Transferable under any circumstances.',
            'Right to confirm renewal is reserved with club. No explanation will be given if renewal is not granted.',
            'For the adjudication of any dispute here under or in connection herewith, the exclusive jurisdiction of the court of Mumbai only.',
            'please report us at + 91 9137566483 or info@mcccricket.com.',
            'Due dates for the payment of coaching fees mentioned to you during the admission remain the same, no change will be done in the installment dates under any circumstances. (Subject to approval of Directors).',
            'I have read the rules and Regulations carefully and shall be binding on me.',
            'Enclosure: 1) Copy of Birth Certificate 2) Copy of Aadhar Card 3) 2 Photographs affix',
        ] as $requiredText) {
            self::assertStringContainsString($requiredText, $terms);
        }

        foreach ([
            'liability-heading',
            'rules-heading',
            'parent-notice-heading',
            'enclosure-heading',
        ] as $headingId) {
            self::assertStringContainsString($headingId, $terms);
        }
    }

    public function testAdmissionFormExposesTheReusableTermsModalAndPrintSection(): void
    {
        $root = dirname(__DIR__, 2);
        $new = (string) file_get_contents($root . '/app/Modules/Admissions/Views/new.php');
        $declarations = (string) file_get_contents($root . '/app/Modules/Admissions/Views/_declarations_signatures.php');
        $styles = (string) file_get_contents($root . '/public/assets/css/app.css');
        $javascript = (string) file_get_contents($root . '/public/assets/js/app.js');

        self::assertStringContainsString('Read Terms and Conditions', $declarations);
        self::assertStringContainsString('includeTerms', $declarations);
        self::assertStringContainsString('data-admission-terms-modal', $new);
        self::assertStringContainsString('data-admission-terms-close', $new);
        self::assertStringContainsString('mcc-admission-print-terms', $new);
        self::assertStringContainsString("_terms_and_conditions", $new);
        self::assertStringContainsString('max-height: min(70vh, 720px);', $styles);
        self::assertStringContainsString('@media print', $styles);
        self::assertStringContainsString('.mcc-admission-terms-modal', $styles);
        self::assertStringContainsString('.mcc-admission-print-terms', $styles);
        self::assertStringContainsString('function initAdmissionTermsModal()', $javascript);
        self::assertStringContainsString('event.key === \'Escape\'', $javascript);
    }

    public function testAdmissionFormTermsAreClosedInAnAccessibleDetailsSection(): void
    {
        $root = dirname(__DIR__, 2);
        $new = (string) file_get_contents($root . '/app/Modules/Admissions/Views/new.php');
        $terms = (string) file_get_contents($root . '/app/Modules/Admissions/Views/_terms_and_conditions.php');
        $pdf = (string) file_get_contents($root . '/app/Modules/Admissions/Views/pdf.php');

        self::assertStringContainsString('<details', $new);
        self::assertStringContainsString('class="mcc-admission-section mcc-admission-print-terms"', $new);
        self::assertStringContainsString('data-admission-terms-section', $new);
        self::assertStringContainsString('<summary class="mcc-admission-section-summary"', $new);
        self::assertStringContainsString('<span>Terms and Conditions</span>', $new);
        self::assertStringNotContainsString('data-admission-terms-section" open', $new);
        self::assertStringContainsString('mcc-admission-section-body', $new);
        self::assertStringContainsString('_terms_and_conditions', $new);
        self::assertStringContainsString(
            'I have read the rules and Regulations carefully and shall be binding on me.',
            $terms,
        );
        $declarations = (string) file_get_contents(
            $root . '/app/Modules/Admissions/Views/_declarations_signatures.php',
        );
        self::assertStringContainsString('name="declaration_accepted"', $declarations);
        self::assertStringContainsString('required>', $declarations);
        self::assertStringContainsString('_terms_and_conditions', $pdf);
    }
}
