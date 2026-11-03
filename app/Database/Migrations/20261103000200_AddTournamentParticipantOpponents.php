<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddTournamentParticipantOpponents extends Migration
{
    public function up(): void
    {
        if ($this->db->fieldExists('opponent_id', 'tournament_participants')) {
            return;
        }

        $this->forge->addColumn('tournament_participants', [
            'opponent_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'null' => true,
                'after' => 'team_name',
            ],
        ]);
        $this->db->query(
            'ALTER TABLE tournament_participants ADD INDEX tournament_participants_opponent_index (opponent_id)'
        );
        $this->db->query(
            'ALTER TABLE tournament_participants ADD CONSTRAINT tournament_participants_opponent_fk FOREIGN KEY (opponent_id) REFERENCES opponents(id) ON DELETE RESTRICT ON UPDATE CASCADE'
        );
    }

    public function down(): void
    {
        // Tournament schema is forward-only; production history must not be removed.
    }
}
