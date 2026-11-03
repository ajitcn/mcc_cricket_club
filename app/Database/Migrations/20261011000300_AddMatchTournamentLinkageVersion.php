<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Forward-only, idempotent versioning and Fixture-to-Match cardinality. */
final class AddMatchTournamentLinkageVersion extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('matches') && ! $this->db->fieldExists('version', 'matches')) {
            $this->forge->addColumn('matches', [
                'version' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'default' => 1,
                    'after' => 'status',
                ],
            ]);
        }

        if ($this->db->tableExists('tournament_fixtures')
            && ! $this->db->fieldExists('version', 'tournament_fixtures')) {
            $this->forge->addColumn('tournament_fixtures', [
                'version' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'default' => 1,
                    'after' => 'status',
                ],
            ]);
        }

        if ($this->db->tableExists('tournament_fixtures')
            && ! $this->indexExists('tournament_fixtures', ['match_id'])) {
            $this->forge->addUniqueKey('match_id', 'uq_tournament_fixtures_match_id');
            $this->forge->processIndexes('tournament_fixtures');
        }
    }

    public function down(): void
    {
        // Forward-only: linkage history and data are never removed by rollback.
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
}
