<?php

declare (strict_types=1);
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
final class AddApprovedFollowUpWorkflow extends Migration
{
    public function up(): void
    {
        // Forward-only schema evolution. Existing Follow-Up rows are retained;
        // nullable additions allow legacy rows to remain readable.
        $fields = [
            'follow_up_type' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'outcome' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'cancellation_reason' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'completed_at' => ['type' => 'DATETIME', 'null' => true],
            'cancelled_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ];
        $missingFields = [];

        foreach ($fields as $name => $definition) {
            if (! $this->db->fieldExists($name, 'lead_follow_ups')) {
                $missingFields[$name] = $definition;
            }
        }

        if ($missingFields !== []) {
            $this->forge->addColumn('lead_follow_ups', $missingFields);
        }

        if (! $this->indexExists(['lead_id', 'status', 'follow_up_at'])) {
            $this->forge->addKey(['lead_id', 'status', 'follow_up_at']);
        }

        if (! $this->foreignKeyExists('updated_by')) {
            $this->forge->addForeignKey('updated_by', 'users', 'id', 'SET NULL', 'CASCADE');
        }
    }
    public function down(): void
    {
        // Follow-Up history is retention-sensitive and migrations are forward-only.
    }

    private function indexExists(array $columns): bool
    {
        $indexes = $this->db->query('SHOW INDEX FROM lead_follow_ups')->getResultArray();
        $grouped = [];

        foreach ($indexes as $index) {
            $name = (string) ($index['Key_name'] ?? '');
            if ($name === 'PRIMARY') {
                continue;
            }

            $grouped[$name][(int) ($index['Seq_in_index'] ?? 0)] = (string) ($index['Column_name'] ?? '');
        }

        foreach ($grouped as $indexColumns) {
            ksort($indexColumns);
            if (array_values($indexColumns) === $columns) {
                return true;
            }
        }

        return false;
    }

    private function foreignKeyExists(string $column): bool
    {
        $database = (string) $this->db->getDatabase();
        $result = $this->db->query(
            'SELECT 1 FROM information_schema.KEY_COLUMN_USAGE '
            . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? '
            . 'AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1',
            [$database, 'lead_follow_ups', $column]
        );

        return $result->getNumRows() > 0;
    }
}
