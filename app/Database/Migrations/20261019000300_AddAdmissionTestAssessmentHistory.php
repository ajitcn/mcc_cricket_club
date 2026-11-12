<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddAdmissionTestAssessmentHistory extends Migration
{
    public function up(): void
    {
        $fields = [
            'batting_mark' => [
                'type' => 'TINYINT',
                'constraint' => 2,
                'unsigned' => true,
                'null' => true,
            ],
            'wicketkeeping_mark' => [
                'type' => 'TINYINT',
                'constraint' => 2,
                'unsigned' => true,
                'null' => true,
            ],
            'bowling_mark' => [
                'type' => 'TINYINT',
                'constraint' => 2,
                'unsigned' => true,
                'null' => true,
            ],
            'fitness_mark' => [
                'type' => 'TINYINT',
                'constraint' => 2,
                'unsigned' => true,
                'null' => true,
            ],
            'written_test_mark' => [
                'type' => 'TINYINT',
                'constraint' => 2,
                'unsigned' => true,
                'null' => true,
            ],
            'overall_mark' => [
                'type' => 'TINYINT',
                'constraint' => 2,
                'unsigned' => true,
                'null' => true,
            ],
        ];
        $missingFields = [];

        foreach ($fields as $name => $definition) {
            if (! $this->db->fieldExists($name, 'admission_tests')) {
                $missingFields[$name] = $definition;
            }
        }

        if ($missingFields !== []) {
            $this->forge->addColumn('admission_tests', $missingFields);
        }

        if ($this->db->tableExists('admission_test_submission_history')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'admission_test_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'version' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
            ],
            'variant' => [
                'type' => 'CHAR',
                'constraint' => 1,
            ],
            'responses_json' => [
                'type' => 'LONGTEXT',
            ],
            'assessment_json' => [
                'type' => 'TEXT',
            ],
            'score' => [
                'type' => 'DECIMAL',
                'constraint' => '8,2',
                'null' => true,
            ],
            'result' => [
                'type' => 'VARCHAR',
                'constraint' => 80,
                'null' => true,
            ],
            'evaluator_user_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'null' => true,
            ],
            'submitted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['admission_test_id', 'version']);
        $this->forge->addForeignKey('admission_test_id', 'admission_tests', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('evaluator_user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('admission_test_submission_history', true);
    }

    public function down(): void
    {
        // Forward-only migration; historical Admission Test data is retained.
    }
}
