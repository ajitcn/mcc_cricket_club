<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddCrmLeadRemarks extends Migration
{
    public function up(): void
    {
        if (!$this->db->fieldExists('remarks', 'leads')) {
            $this->forge->addColumn('leads', [
                'remarks' => [
                    'type' => 'TEXT',
                    'null' => true,
                    'after' => 'date_of_birth',
                ],
            ]);
        }
    }

    public function down(): void
    {
        // Forward-only: existing Lead remarks must not be removed.
    }
}
