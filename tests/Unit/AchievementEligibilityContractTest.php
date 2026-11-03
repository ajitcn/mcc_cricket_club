<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AchievementEligibilityContractTest extends TestCase
{
    public function testRulesAreExplicitDraftOnlyAndSourceVersioned(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/AchievementEligibilityService.php');
        $migration = file_get_contents(
            ROOTPATH . 'app/Database/Migrations/20261007000400_AddAchievementEligibilityAutomation.php',
        );

        self::assertIsString($service);
        self::assertIsString($migration);
        foreach (['attendance.v1', 'performance.v1', 'coach.v1'] as $rule) {
            self::assertStringContainsString($rule, $service);
        }
        self::assertStringContainsString("'status' => 'draft'", $service);
        self::assertStringContainsString('eligibility_evaluation_id', $service);
        self::assertStringContainsString('eligibility_source_event', $service);
        self::assertStringContainsString('achievement_eligibility_evaluations', $migration);
        self::assertStringContainsString('attempt_number', $migration);
        self::assertStringContainsString('retry_of_id', $migration);
        self::assertStringContainsString('achievement.eligibility.evaluate', $migration);
        self::assertStringNotContainsString('publishAward', $service);
        self::assertStringNotContainsString('approveAward', $service);
        self::assertStringContainsString("'nomination_note' => null", $service);
        self::assertStringNotContainsString('dropTable', $migration);
        self::assertStringNotContainsString('truncate', strtolower($migration));
    }
}
