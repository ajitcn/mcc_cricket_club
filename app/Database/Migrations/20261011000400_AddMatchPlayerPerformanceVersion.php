<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Forward-only, idempotent optimistic concurrency versioning. */
final class AddMatchPlayerPerformanceVersion extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('match_player_performances')
            && ! $this->db->fieldExists('version', 'match_player_performances')) {
            $this->forge->addColumn('match_player_performances', [
                'version' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'default' => 1,
                    'after' => 'correction_reason',
                ],
            ]);
        }
    }

    public function down(): void
    {
        // Forward-only: performance history and version state are never removed.
    }
}
