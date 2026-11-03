<?php

declare(strict_types=1);

namespace Tests\database;

use PHPUnit\Framework\TestCase;

final class PlayerFeedbackWorkflowTest extends TestCase
{
    public function testQuarterlyFeedbackPreservationContractUsesUniquePeriodAndHistory(): void
    {
        $migration = file_get_contents(APPPATH . 'Database/Migrations/20261028000100_CreatePlayerFeedbacks.php');
        $service = file_get_contents(APPPATH . 'Services/PlayerFeedbackService.php');

        self::assertStringContainsString("addUniqueKey(['player_id', 'feedback_year', 'feedback_quarter'])", $migration);
        self::assertStringContainsString("player_feedback_history", $service);
        self::assertStringContainsString('json_encode($existing', $service);
        self::assertStringContainsString("transBegin", $service);
        self::assertStringContainsString("transRollback", $service);
        self::assertStringContainsString("periodLabel", $service);
        self::assertStringContainsString('searchCreateablePlayers', $service);
        self::assertStringContainsString("orLike('p.id'", $service);
        self::assertStringContainsString('player_coach_assignments', $service);
        self::assertStringContainsString("No feedback recorded", file_get_contents(APPPATH . 'Modules/Players/Views/feedback_annual.php'));
    }
}
