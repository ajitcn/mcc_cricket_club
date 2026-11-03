<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddAdmissionCoachingSelections extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('admissions')) {
            return;
        }

        $fields = $this->db->getFieldNames('admissions');

        if (! in_array('gym_fitness_session', $fields, true)) {
            $this->forge->addColumn('admissions', [
                'gym_fitness_session' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
            ]);
        }

        $changes = [];
        foreach (['fitness_plan', 'match_program', 'welcome_kit'] as $field) {
            if (in_array($field, $fields, true)) {
                $changes[$field] = [
                    'type' => 'TEXT',
                    'null' => true,
                ];
            }
        }

        if ($changes !== []) {
            $this->forge->modifyColumn('admissions', $changes);
        }
    }

    public function down(): void
    {
        // Forward-only: Admission coaching selections and historical values are retained.
    }
}
