<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;

final class AchievementSchemaReconciliationFeatureTest extends CIUnitTestCase
{
    protected $DBGroup = 'tests';

    public function testCanonicalAchievementSchemaHasNoDuplicateTablesOrMigrationDefinition(): void
    {
        $db = db_connect('tests');
        $migrationFiles = glob(ROOTPATH . 'app/Database/Migrations/*Achievement*.php');

        self::assertIsArray($migrationFiles);
        self::assertNotFalse(array_search(
            ROOTPATH . 'app/Database/Migrations/20261007000200_AddAchievementArchival.php',
            $migrationFiles,
            true,
        ));
        self::assertNotFalse(array_search(
            ROOTPATH . 'app/Database/Migrations/20261007000300_ReconcileAchievementSchemaDrift.php',
            $migrationFiles,
            true,
        ));
        self::assertSame(1, $this->tableCount($db, 'achievement_awards'));
        self::assertSame(1, $this->tableCount($db, 'notification_recipients'));
        self::assertSame(0, $this->tableCount($db, 'achievement_award_drafts'));
        self::assertSame(0, $this->tableCount($db, 'achievement_notification_recipients'));
    }

    private function tableCount(object $db, string $table): int
    {
        return (int) $db->query(
            'SELECT COUNT(*) AS total FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$db->getDatabase(), $table],
        )->getRow('total');
    }
}
