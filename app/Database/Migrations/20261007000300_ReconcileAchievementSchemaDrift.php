<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/** Forward-only reconciliation for the canonical Achievement schema. */
final class ReconcileAchievementSchemaDrift extends Migration
{
    private const TABLE = 'achievement_awards';

    public function up(): void
    {
        if (! $this->db->tableExists(self::TABLE)) {
            return;
        }

        foreach (['is_archived', 'archived_by', 'archived_at', 'restored_by'] as $column) {
            if (! $this->db->fieldExists($column, self::TABLE)) {
                throw new RuntimeException(
                    'The canonical Achievement archival migration must run before schema reconciliation.',
                );
            }
        }

        $processIndexes = false;

        if (! $this->indexExists(self::TABLE, ['branch_id', 'is_archived', 'status'])) {
            $this->forge->addKey(
                ['branch_id', 'is_archived', 'status'],
                false,
                false,
                'achievement_awards_branch_archived_status',
            );
            $processIndexes = true;
        }

        if (! $this->indexExists(self::TABLE, ['is_archived', 'archived_at'])) {
            $this->forge->addKey(
                ['is_archived', 'archived_at'],
                false,
                false,
                'achievement_awards_archived_at',
            );
            $processIndexes = true;
        }

        if (! $this->foreignKeyExists(self::TABLE, 'archived_by', 'users', 'id')) {
            $this->forge->addForeignKey(
                'archived_by',
                'users',
                'id',
                'RESTRICT',
                'CASCADE',
                'achievement_awards_archived_by_fk',
            );
            $processIndexes = true;
        }

        if (! $this->foreignKeyExists(self::TABLE, 'restored_by', 'users', 'id')) {
            $this->forge->addForeignKey(
                'restored_by',
                'users',
                'id',
                'RESTRICT',
                'CASCADE',
                'achievement_awards_restored_by_fk',
            );
            $processIndexes = true;
        }

        if ($processIndexes) {
            $this->forge->processIndexes(self::TABLE);
        }
    }

    public function down(): void
    {
        // Forward-only: schema history and Achievement records are never removed.
    }

    /** @param list<string> $columns */
    private function indexExists(string $table, array $columns): bool
    {
        $rows = $this->db->query(
            'SELECT INDEX_NAME, SEQ_IN_INDEX, COLUMN_NAME '
            . 'FROM information_schema.STATISTICS '
            . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? '
            . 'ORDER BY INDEX_NAME, SEQ_IN_INDEX',
            [$this->db->getDatabase(), $table],
        )->getResultArray();

        $indexes = [];
        foreach ($rows as $row) {
            $indexes[$row['INDEX_NAME']][] = $row['COLUMN_NAME'];
        }

        foreach ($indexes as $indexColumns) {
            if ($indexColumns === $columns) {
                return true;
            }
        }

        return false;
    }

    private function foreignKeyExists(
        string $table,
        string $column,
        string $referencedTable,
        string $referencedColumn,
    ): bool {
        return $this->db->query(
            'SELECT CONSTRAINT_NAME '
            . 'FROM information_schema.KEY_COLUMN_USAGE '
            . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? '
            . 'AND REFERENCED_TABLE_NAME = ? AND REFERENCED_COLUMN_NAME = ? '
            . 'LIMIT 1',
            [
                $this->db->getDatabase(),
                $table,
                $column,
                $referencedTable,
                $referencedColumn,
            ],
        )->getRowArray() !== null;
    }
}
