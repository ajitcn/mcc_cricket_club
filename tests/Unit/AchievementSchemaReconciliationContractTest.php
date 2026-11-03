<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AchievementSchemaReconciliationContractTest extends TestCase
{
    public function testReconciliationIsCanonicalForwardOnlyAndIdempotentByInspection(): void
    {
        $migration = file_get_contents(
            ROOTPATH . 'app/Database/Migrations/20261007000300_ReconcileAchievementSchemaDrift.php',
        );

        self::assertIsString($migration);
        self::assertStringContainsString("const TABLE = 'achievement_awards'", $migration);
        self::assertStringContainsString('fieldExists', $migration);
        self::assertStringContainsString('information_schema.STATISTICS', $migration);
        self::assertStringContainsString('information_schema.KEY_COLUMN_USAGE', $migration);
        self::assertStringContainsString('processIndexes', $migration);
        self::assertStringContainsString('addForeignKey', $migration);
        self::assertStringContainsString('Forward-only', $migration);
        self::assertStringNotContainsString('addColumn', $migration);
        self::assertStringNotContainsString('dropTable', $migration);
        self::assertStringNotContainsString('dropColumn', $migration);
        self::assertStringNotContainsString('renameColumn', $migration);
        self::assertStringNotContainsString('migrations', $migration);
        self::assertStringNotContainsString('achievement_award_drafts', $migration);
        self::assertStringNotContainsString('achievement_notification_recipients', $migration);
    }
}
