<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddSessionDescription extends Migration
{
    public function up(): void
    {
        if ($this->db->fieldExists('description', 'sessions')) {
            return;
        }

        $this->forge->addColumn('sessions', [
            'description' => [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'session_type',
            ],
        ]);
    }

    public function down(): void
    {
        // Forward-only migration. Existing Session notes are not removed.
    }
}
