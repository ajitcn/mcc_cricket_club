<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddMatchYourTeam extends Migration
{
    public function up(): void
    {
        if ($this->db->fieldExists('your_team', 'matches')) {
            return;
        }

        $this->forge->addColumn('matches', [
            'your_team' => [
                'type' => 'VARCHAR',
                'constraint' => 150,
                'null' => true,
                'after' => 'opponent',
            ],
        ]);
    }

    public function down(): void
    {
        // Match schema changes are forward-only.
    }
}
