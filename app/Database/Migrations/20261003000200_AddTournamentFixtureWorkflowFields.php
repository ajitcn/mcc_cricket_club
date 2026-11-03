<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddTournamentFixtureWorkflowFields extends Migration
{
    public function up(): void
    {
        if (! $this->db->fieldExists('round', 'tournament_fixtures')) {
            $this->forge->addColumn('tournament_fixtures', [
                'round' => [
                    'type' => 'VARCHAR',
                    'constraint' => 100,
                    'null' => true,
                    'after' => 'fixture_number',
                ],
            ]);
        }

        $this->db->query(
            "UPDATE tournament_fixtures SET status = 'scheduled' WHERE status = 'planned'"
        );
        $this->db->query(
            "ALTER TABLE tournament_fixtures MODIFY status VARCHAR(30) NOT NULL DEFAULT 'scheduled'"
        );
    }

    public function down(): void
    {
        // Tournament schema is forward-only; production history must not be removed.
    }
}
