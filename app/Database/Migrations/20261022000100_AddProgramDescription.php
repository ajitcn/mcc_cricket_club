<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddProgramDescription extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('programs')) {
            return;
        }

        if (! $this->db->fieldExists('description', 'programs')) {
            $this->forge->addColumn('programs', [
                'description' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
            ]);
        }
    }

    public function down(): void
    {
        // Forward-only: existing Program descriptions must not be removed.
    }
}
