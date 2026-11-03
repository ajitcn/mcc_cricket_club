<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL. A3-05 archival only. */
final class AddAchievementArchival extends Migration
{
    private const TABLE = 'achievement_awards';

    public function up(): void
    {
        if (! $this->db->tableExists(self::TABLE)) {
            return;
        }

        $columns = [
            'is_archived' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'unsigned' => true,
                'default' => 0,
            ],
            'archived_by' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'null' => true,
            ],
            'archived_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'archive_reason' => [
                'type' => 'VARCHAR',
                'constraint' => 1000,
                'null' => true,
            ],
            'restored_by' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'null' => true,
            ],
            'restored_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'restore_reason' => [
                'type' => 'VARCHAR',
                'constraint' => 1000,
                'null' => true,
            ],
        ];

        foreach ($columns as $column => $definition) {
            if (! $this->db->fieldExists($column, self::TABLE)) {
                $this->forge->addColumn(self::TABLE, [$column => $definition]);
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

        foreach (['achievement.archive', 'achievement.restore'] as $permission) {
            foreach (['Super Admin', 'Branch Admin'] as $roleName) {
                $role = $this->db->table('roles')
                    ->where('name', $roleName)
                    ->get()
                    ->getRowArray();

                if (! is_array($role)) {
                    continue;
                }

                $permissionExists = $this->db->table('role_permissions')
                    ->where([
                        'role_id' => $role['id'],
                        'permission' => $permission,
                    ])
                    ->countAllResults() > 0;

                if (! $permissionExists) {
                    $this->db->table('role_permissions')->insert([
                        'role_id' => $role['id'],
                        'permission' => $permission,
                        'created_at' => gmdate('Y-m-d H:i:s'),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Forward-only: Achievement history must not be removed by rollback.
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
