<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdmissionCricketAbilityContractTest extends TestCase
{
    public function testHobbiesAndAchievementsUseExistingCricketFormFieldsAndDetailOutput(): void
    {
        $root = dirname(__DIR__, 2);
        $partial = (string) file_get_contents($root . '/app/Modules/Admissions/Views/_complete_form_fields.php');
        $new = (string) file_get_contents($root . '/app/Modules/Admissions/Views/new.php');
        $edit = (string) file_get_contents($root . '/app/Modules/Admissions/Views/edit.php');
        $detail = (string) file_get_contents($root . '/app/Modules/Admissions/Views/show.php');
        $service = (string) file_get_contents($root . '/app/Services/AdmissionService.php');

        foreach (['hobbies', 'achievements'] as $field) {
            self::assertSame(1, substr_count($partial, "textareaField('$field'"));
            self::assertStringContainsString('_complete_form_fields', $new);
            self::assertStringContainsString('_complete_form_fields', $edit);
            self::assertStringContainsString("value('$field')", $detail);
            self::assertStringContainsString("'$field'", $service);
        }

        self::assertStringContainsString('strlen((string) $out[$field]) > 5000', $service);
    }
}
