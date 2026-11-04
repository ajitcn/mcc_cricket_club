<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddFitnessAssessmentBmi extends Migration
{
    public function up(): void
    {
        if (! $this->db->fieldExists('bmi', 'fitness_assessments')) {
            $this->forge->addColumn('fitness_assessments', [
                'bmi' => [
                    'type' => 'DECIMAL',
                    'constraint' => '5,2',
                    'null' => true,
                ],
            ]);
        }
    }

    public function down(): void
    {
        // Fitness assessment history is forward-only.
    }
}
