<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddMatchCategory extends Migration
{
    public function up(): void
    {
        if ($this->db->fieldExists('match_category', 'matches')) {
            return;
        }

        $this->forge->addColumn('matches', [
            'match_category' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'regular',
                'after' => 'match_type',
            ],
        ]);
    }

    public function down(): void
    {
        // Match schema is forward-only; production history must not be removed.
    }
}
