<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddSessionType extends Migration
{
    public function up(): void
    {
        if ($this->db->fieldExists('session_type', 'sessions')) {
            return;
        }

        $this->forge->addColumn('sessions', [
            'session_type' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => true,
                'after' => 'status',
            ],
        ]);
    }

    public function down(): void
    {
        // Forward-only development/test migration. Existing session records are not rewritten.
    }
}
