<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdmissionEliteCampContractTest extends TestCase
{
    public function testEliteCampFieldsUseCoachingTypeCheckboxContract(): void
    {
        $root = dirname(__DIR__, 2);
        $partial = (string) file_get_contents($root . '/app/Modules/Admissions/Views/_complete_form_fields.php');
        $service = (string) file_get_contents($root . '/app/Services/AdmissionService.php');
        $script = (string) file_get_contents($root . '/public/assets/js/app.js');
        $component = (string) file_get_contents($root . '/app/Views/components/admission-select.php');
        $migration = (string) file_get_contents($root . '/app/Database/Migrations/20261013000200_AddAdmissionEliteCampDetails.php');
        $estimateMigration = (string) file_get_contents($root . '/app/Database/Migrations/20261026000500_AddAdmissionEstimatedCoachingSessions.php');

        foreach (['elite_camp_days', 'elite_camp_duration'] as $field) {
            self::assertStringContainsString($field, $partial);
            self::assertStringContainsString($field, $service);
            self::assertStringContainsString($field, $migration);
        }

        foreach (['estimated_personal_session', 'estimated_elite_session'] as $field) {
            self::assertStringContainsString($field, $partial);
            self::assertStringContainsString($field, $service);
            self::assertStringContainsString($field, $estimateMigration);
        }

        self::assertStringContainsString("'coaching_type',", $partial);
        self::assertStringContainsString("['Personal coaching', 'Group coaching', 'Elite']", $partial);
        self::assertStringContainsString('data-admission-coaching-dependent', $partial);
        self::assertStringNotContainsString('data-admission-elite-camp-control', $partial);
        self::assertStringNotContainsString('data-admission-elite-camp-dependent', $partial);
        self::assertStringContainsString("['1 Day', '2 Days', '3 Days', '4 Days', '5 Days']", $partial);
        self::assertStringContainsString("['1 hour', '1.5 hours', '2 hours']", $partial);
        self::assertStringContainsString('$out[\'elite_camp_days\'] = null', $service);
        self::assertStringContainsString('$out[\'elite_camp_duration\'] = null', $service);
        self::assertStringContainsString('Elite Camp details are required', $service);
        self::assertStringContainsString('initAdmissionCoachingFields', $script);
        self::assertStringContainsString('data-admission-coaching-dependent', $script);
        self::assertStringContainsString('field.disabled = !show', $script);
        self::assertStringContainsString('$useBtSelect = ! $forceNativeSelect && ($forceBtSelect || $multiple || $realOptionCount >= 10);', $component);
        self::assertStringContainsString('field.value =', $script);
        self::assertStringContainsString('Forward-only', $migration);
        self::assertStringNotContainsString('dropColumn', $migration);
        self::assertStringNotContainsString('truncate', strtolower($migration));
    }
}
