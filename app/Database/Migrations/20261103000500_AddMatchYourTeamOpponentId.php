<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddMatchYourTeamOpponentId extends Migration
{
    public function up(): void
    {
        if ($this->db->fieldExists('your_team_opponent_id', 'matches')) {
            return;
        }

        $this->forge->addColumn('matches', [
            'your_team_opponent_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'null' => true,
                'after' => 'your_team',
            ],
        ]);
        $this->db->query('ALTER TABLE matches ADD INDEX matches_your_team_opponent_index (your_team_opponent_id)');
        $this->db->query('ALTER TABLE matches ADD CONSTRAINT matches_your_team_opponent_fk FOREIGN KEY (your_team_opponent_id) REFERENCES opponents(id) ON DELETE RESTRICT ON UPDATE CASCADE');
    }

    public function down(): void
    {
        // Match schema changes are forward-only.
    }
}
