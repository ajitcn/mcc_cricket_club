<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdmissionRenewalContractTest extends TestCase
{
    public function testRenewalContractUsesForwardOnlyHistoryAndProtectedRoutes(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/20261013000600_CreateAdmissionPeriods.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/AdmissionService.php');
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Admissions/Config/Routes.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admissions/Views/renew.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Admissions/Controllers/Browser.php');
        $snapshotMigration = file_get_contents(ROOTPATH . 'app/Database/Migrations/20261018000100_AddAdmissionRenewalSelectionSnapshot.php');

        self::assertIsString($migration);
        self::assertIsString($service);
        self::assertIsString($routes);
        self::assertIsString($view);
        self::assertIsString($controller);
        self::assertIsString($snapshotMigration);
        self::assertStringContainsString("createTable('admission_periods'", $migration);
        self::assertStringContainsString('request_hash', $migration);
        self::assertStringContainsString('renewal_version', $migration);
        self::assertStringContainsString('admission_renewed', $service);
        self::assertStringContainsString('The renewal start date must be after', $service);
        self::assertStringContainsString('This Admission was renewed by another user', $service);
        self::assertStringContainsString("'(:num)/renew'", $routes);
        self::assertStringContainsString("permission:admission.update", $routes);
        self::assertStringContainsString('method="post"', $view);
        self::assertStringContainsString("'/renew') ?>", $view);
        self::assertStringContainsString('->with(\'field_errors\', $this->admissionFieldErrors($e, $this->request->getPost()))', $controller);
        self::assertStringContainsString('->with(\'error\', $this->admissionError($e))', $controller);
        self::assertStringContainsString('$data[\'suppressGlobalAlerts\'] = false;', $controller);
        self::assertStringContainsString("'suppressGlobalAlerts' => false", $view);
        self::assertStringContainsString('Admission History', $view);
        self::assertStringContainsString('data-mcc-date-picker', $view);
        self::assertStringContainsString('renewal_error', $view);
        self::assertStringContainsString('Please enter the current Admission period end date before renewing.', $service);
        self::assertStringContainsString('The renewal start date must be after the current Admission period end date.', $service);
        self::assertStringContainsString('currentActivePeriod', $service);
        self::assertStringContainsString('admissionStartDate', $service);
        self::assertStringContainsString('admissionEndDate', $service);
        self::assertStringContainsString("'!Y-m-d H:i:s'", $service);
        self::assertStringContainsString("'!d-m-Y H:i:s'", $service);
        self::assertStringContainsString("config('App')->appTimezone", $service);
        self::assertStringContainsString('logRenewalDateComparison', $service);
        self::assertStringContainsString('periodEndDate', $service);
        self::assertStringContainsString('admission_number', $view);
        self::assertStringContainsString('Training Selection and Coaching Options', $view);
        self::assertStringContainsString("'section' => 'coaching'", $view);
        $admissionSectionStart = strpos($view, 'id="new-period-heading"');
        $coachingSectionStart = strpos($view, 'id="renewal-coaching-heading"');
        self::assertNotFalse($admissionSectionStart);
        self::assertNotFalse($coachingSectionStart);
        $admissionSection = substr($view, $admissionSectionStart, $coachingSectionStart - $admissionSectionStart);
        self::assertStringNotContainsString('name\' => \'fee_plan_label', $admissionSection);
        self::assertStringNotContainsString('id="renewal-fee-plan-label"', $admissionSection);
        self::assertStringContainsString('Views\\\\_fees_fields', $view);
        self::assertStringContainsString("'section' => 'coaching-installments'", $view);
        self::assertStringContainsString("'section' => 'hostel-installments'", $view);
        foreach ([
            'age_group_id',
            'fee_plan_label',
            'coaching_type',
            'personal_coaching_days',
            'personal_coaching_duration',
            'group_coaching_days',
            'coaching_delivery',
            'fitness_plan',
            'gym_fitness_session',
            'match_program',
            'induction_plan',
            'review_frequency',
            'progress_report_frequency',
            'feedback_call_frequency',
            'meditation_sessions',
            'elite_camp_days',
            'elite_camp_duration',
            'student_diary',
            'welcome_kit',
        ] as $field) {
            self::assertStringContainsString($field, $view . $service);
        }
        self::assertStringContainsString('renewal_values', $snapshotMigration);
        self::assertStringContainsString('normalizeRenewalValues', $service);
        self::assertStringContainsString('renewalAdmissionUpdates', $service);
    }
}
