<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use App\Database\Migrations\ReconcileAchievementSchemaDrift;

/** Insert-only verification of forward-only Achievement schema reconciliation. */
final class AchievementSchemaReconciliationSafeTest extends CIUnitTestCase
{
    protected $DBGroup = 'tests';

    public function testReconciliationPreservesDataRepairsIndexesAndForeignKeysSafely(): void
    {
        require_once ROOTPATH . 'app/Database/Migrations/20261007000300_ReconcileAchievementSchemaDrift.php';
        $db = Database::connect('tests');
        self::assertSame('mcc_cricket_club_test', strtolower((string) $db->getDatabase()));

        $beforeCount = (int) $db->table('achievement_awards')->countAllResults();
        $beforeRows = $db->table('achievement_awards')
            ->select('id, status, is_archived')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();
        $beforeHistory = $this->migrationCount($db, '20261007000100');

        self::assertSame(1, $this->indexCount($db, ['branch_id', 'is_archived', 'status']));
        self::assertSame(1, $this->indexCount($db, ['is_archived', 'archived_at']));
        self::assertTrue($this->foreignKeyExists($db, 'archived_by'));
        self::assertTrue($this->foreignKeyExists($db, 'restored_by'));
        self::assertSame(1, $this->columnCount($db, 'is_archived'));
        self::assertSame(1, $this->columnCount($db, 'archived_by'));
        self::assertSame(1, $this->columnCount($db, 'restored_by'));

        $migration = new ReconcileAchievementSchemaDrift(Database::forge('tests'));
        $migration->up();
        $migration->up();

        self::assertSame($beforeCount, (int) $db->table('achievement_awards')->countAllResults());
        self::assertSame($beforeRows, $db->table('achievement_awards')
            ->select('id, status, is_archived')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray());
        self::assertSame($beforeHistory, $this->migrationCount($db, '20261007000100'));
        self::assertSame(1, $this->migrationCount($db, '20261007000300'));
    }

    private function indexCount(object $db, array $columns): int
    {
        $rows = $db->query(
            'SELECT INDEX_NAME, COLUMN_NAME FROM information_schema.STATISTICS '
            . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? '
            . 'ORDER BY INDEX_NAME, SEQ_IN_INDEX',
            [$db->getDatabase(), 'achievement_awards'],
        )->getResultArray();
        $indexes = [];
        foreach ($rows as $row) {
            $indexes[$row['INDEX_NAME']][] = $row['COLUMN_NAME'];
        }

        return count(array_filter($indexes, static fn (array $value): bool => $value === $columns));
    }

    private function foreignKeyExists(object $db, string $column): bool
    {
        return $db->query(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE '
            . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? '
            . 'AND REFERENCED_TABLE_NAME = ? AND REFERENCED_COLUMN_NAME = ?',
            [$db->getDatabase(), 'achievement_awards', $column, 'users', 'id'],
        )->getRowArray() !== null;
    }

    private function columnCount(object $db, string $column): int
    {
        return (int) $db->query(
            'SELECT COUNT(*) AS total FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$db->getDatabase(), 'achievement_awards', $column],
        )->getRow('total');
    }

    private function migrationCount(object $db, string $version): int
    {
        return (int) $db->table('migrations')->where('version', $version)->countAllResults();
    }
}
