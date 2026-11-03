<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddAdmissionEliteCampDetails extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('admissions')) {
            return;
        }

        $fields = $this->db->getFieldNames('admissions');
        $columns = [];

        foreach (['elite_camp_days', 'elite_camp_duration'] as $field) {
            if (! in_array($field, $fields, true)) {
                $columns[$field] = [
                    'type' => 'VARCHAR',
                    'constraint' => 40,
                    'null' => true,
                ];
            }
        }

        if ($columns !== []) {
            $this->forge->addColumn('admissions', $columns);
        }
    }

    public function down(): void
    {
        // Forward-only: historical Elite Camp details must remain available.
    }
}
