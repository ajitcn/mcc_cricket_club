<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateAdmissionMasterData extends Migration
{
    public function up(): void
    {
        $this->createCoachingPlans();
        $this->createFeePlans();
        $this->createHostelPlans();
        $this->createAcademicPlans();
        $this->addAdmissionReferences();
    }

    public function down(): void
    {
        // Forward-only: master history and Admission snapshots are retained.
    }

    private function createCoachingPlans(): void
    {
        if ($this->db->tableExists('admission_coaching_plans')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'plan_name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'program_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'plan_type' => ['type' => 'VARCHAR', 'constraint' => 50],
            'description' => ['type' => 'TEXT', 'null' => true],
            'branch_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'display_order' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'created_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['branch_id', 'is_active', 'display_order']);
        $this->forge->addForeignKey('program_id', 'programs', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('admission_coaching_plans', true);
    }

    private function createFeePlans(): void
    {
        if ($this->db->tableExists('admission_fee_plans')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'plan_name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'admission_fee' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true],
            'training_fee' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true],
            'installment_count' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'installment_frequency' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'due_date_rule' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'branch_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'effective_from' => ['type' => 'DATE', 'null' => true],
            'effective_to' => ['type' => 'DATE', 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['branch_id', 'is_active', 'effective_from', 'effective_to']);
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('admission_fee_plans', true);
    }

    private function createHostelPlans(): void
    {
        if ($this->db->tableExists('admission_hostel_plans')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'plan_name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'hostel_available' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'monthly_charge' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true],
            'mess_charge' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true],
            'payment_frequency' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'branch_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'effective_from' => ['type' => 'DATE', 'null' => true],
            'effective_to' => ['type' => 'DATE', 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['branch_id', 'is_active', 'effective_from', 'effective_to']);
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('admission_hostel_plans', true);
    }

    private function createAcademicPlans(): void
    {
        if ($this->db->tableExists('admission_academic_plans')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'academic_year' => ['type' => 'VARCHAR', 'constraint' => 30],
            'program_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'batch_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'start_date' => ['type' => 'DATE', 'null' => true],
            'end_date' => ['type' => 'DATE', 'null' => true],
            'branch_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['branch_id', 'is_active', 'academic_year']);
        $this->forge->addForeignKey('program_id', 'programs', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('batch_id', 'batches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('admission_academic_plans', true);
    }

    private function addAdmissionReferences(): void
    {
        $fields = [
            'coaching_plan_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'coaching_plan_label' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'fee_plan_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'fee_plan_label' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'hostel_plan_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'hostel_plan_label' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'academic_plan_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'academic_plan_label' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
        ];
        $missing = [];
        foreach ($fields as $name => $definition) {
            if (! $this->db->fieldExists($name, 'admissions')) {
                $missing[$name] = $definition;
            }
        }
        if ($missing !== []) {
            $this->forge->addColumn('admissions', $missing);
        }
    }
}
