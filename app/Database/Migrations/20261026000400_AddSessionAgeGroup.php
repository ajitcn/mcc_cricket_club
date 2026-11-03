<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddSessionAgeGroup extends Migration
{
    public function up(): void
    {
        if ($this->db->fieldExists('age_group_id', 'sessions')) {
            return;
        }

        $this->forge->addColumn('sessions', [
            'age_group_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'null' => true,
                'after' => 'batch_id',
            ],
        ]);
    }

    public function down(): void
    {
        // Forward-only migration. Existing Session category history is preserved.
    }
}
