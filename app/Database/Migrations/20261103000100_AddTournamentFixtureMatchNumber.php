<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddTournamentFixtureMatchNumber extends Migration
{
    public function up(): void
    {
        if ($this->db->fieldExists('match_number', 'tournament_fixtures')) {
            return;
        }

        $this->forge->addColumn('tournament_fixtures', [
            'match_number' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'null' => true,
                'after' => 'round',
            ],
        ]);
    }

    public function down(): void
    {
        // Tournament schema is forward-only; production history must not be removed.
    }
}
