<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdmissionTrialSummaryContractTest extends TestCase
{
    public function testAdmissionSummaryProjectsExistingTrialAndConversionData(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/AdmissionService.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admissions/Views/show.php');

        self::assertIsString($service);
        self::assertIsString($view);
        foreach (['trial_location', 'trial_status', 'trial_selection_outcome', 'trial_remarks', 'trial_coach_name'] as $field) {
            self::assertStringContainsString($field, $service);
            self::assertStringContainsString($field, $view);
        }
        self::assertStringContainsString("\$admission['admission_date_display']", $service);
        self::assertStringContainsString("\$admission['admission_ratings']", $service);
        self::assertStringContainsString('Admission Date', $view);
        self::assertStringContainsString("mcc_user_date(\$a['admission_date_display'] ?? null, 'Not recorded')", $view);
        self::assertStringContainsString('Admission Start Date', $view);
        self::assertStringContainsString('Admission End Date', $view);
        self::assertStringContainsString("\$a['admission_ratings']", $view);
        foreach (['Trial summary', 'Admission information', 'Documents &amp; Photo', 'Linked Player'] as $section) {
            self::assertStringContainsString($section, $view);
        }
        self::assertStringNotContainsString('Readiness', $view);
        self::assertStringNotContainsString('Phase 1 checklist (AD-02)', $view);
        self::assertStringContainsString('mcc_user_date', $view);
        self::assertStringNotContainsString('json_encode', $view);
    }
}
