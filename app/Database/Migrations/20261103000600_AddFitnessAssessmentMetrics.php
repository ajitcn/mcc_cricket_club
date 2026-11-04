<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddFitnessAssessmentMetrics extends Migration
{
    public function up(): void
    {
        $fields = [
            'age_group' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'squats' => ['type' => 'DECIMAL', 'constraint' => '8,2', 'null' => true],
            'broad_jump' => ['type' => 'DECIMAL', 'constraint' => '8,2', 'null' => true],
            'shoulder_tap' => ['type' => 'DECIMAL', 'constraint' => '8,2', 'null' => true],
            'bronco_test' => ['type' => 'DECIMAL', 'constraint' => '8,2', 'null' => true],
            'running_between_wickets' => ['type' => 'DECIMAL', 'constraint' => '8,2', 'null' => true],
            'plank' => ['type' => 'DECIMAL', 'constraint' => '8,2', 'null' => true],
            'crunches' => ['type' => 'DECIMAL', 'constraint' => '8,2', 'null' => true],
            'thirty_meter_timing' => ['type' => 'DECIMAL', 'constraint' => '8,2', 'null' => true],
        ];

        foreach ($fields as $name => $definition) {
            if (! $this->db->fieldExists($name, 'fitness_assessments')) {
                $this->forge->addColumn('fitness_assessments', [$name => $definition]);
            }
        }
    }

    public function down(): void
    {
        // Fitness assessment history is forward-only.
    }
}
