<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddAdmissionTestFees extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('admission_tests')) {
            return;
        }

        $fields = $this->db->getFieldNames('admission_tests');
        $add = [];
        foreach (['training_fee', 'hostel_charges'] as $field) {
            if (! in_array($field, $fields, true)) {
                $add[$field] = [
                    'type' => 'DECIMAL',
                    'constraint' => '12,2',
                    'unsigned' => true,
                    'null' => true,
                ];
            }
        }
        foreach (['installment_plan', 'hostel_installment_plan'] as $field) {
            if (! in_array($field, $fields, true)) {
                $add[$field] = [
                    'type' => 'VARCHAR',
                    'constraint' => 30,
                    'null' => true,
                ];
            }
        }
        if ($add !== []) {
            $this->forge->addColumn('admission_tests', $add);
        }

        $this->createScheduleTable('admission_test_coaching_installments');
        $this->createScheduleTable('admission_test_hostel_installments');
    }

    public function down(): void
    {
        // Forward-only: submitted Admission Test fee history must not be removed.
    }

    private function createScheduleTable(string $table): void
    {
        if ($this->db->tableExists($table)) {
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
            'branch_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'year_number' => [
                'type' => 'TINYINT',
                'constraint' => 3,
                'unsigned' => true,
            ],
            'year_label' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true,
            ],
            'installment_number' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'unsigned' => true,
            ],
            'due_date' => [
                'type' => 'DATE',
            ],
            'amount' => [
                'type' => 'DECIMAL',
                'constraint' => '12,2',
                'unsigned' => true,
            ],
            'created_by' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['admission_test_id', 'year_number', 'installment_number'], false, true);
        $this->forge->addKey(['branch_id', 'due_date']);
        $this->forge->addForeignKey('admission_test_id', 'admission_tests', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable($table, true);
    }
}
