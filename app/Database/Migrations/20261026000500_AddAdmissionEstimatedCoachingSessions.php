<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddAdmissionEstimatedCoachingSessions extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('admissions')) {
            return;
        }

        $fields = $this->db->getFieldNames('admissions');
        $columns = [];

        foreach (['estimated_personal_session', 'estimated_elite_session'] as $field) {
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
        // Forward-only: historical coaching estimates must remain available.
    }
}
