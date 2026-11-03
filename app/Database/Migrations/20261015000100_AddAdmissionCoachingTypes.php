<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddAdmissionCoachingTypes extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('admissions')) {
            return;
        }

        if (! in_array('coaching_type', $this->db->getFieldNames('admissions'), true)) {
            $this->forge->addColumn('admissions', [
                'coaching_type' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
            ]);
        }
    }

    public function down(): void
    {
        // Forward-only: coaching selections must remain available in existing admissions.
    }
}
