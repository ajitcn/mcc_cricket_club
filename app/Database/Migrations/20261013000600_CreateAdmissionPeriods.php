<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateAdmissionPeriods extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('admission_periods')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'admission_id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                ],
                'branch_id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                ],
                'period_number' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                ],
                'start_date' => [
                    'type' => 'DATE',
                    'null' => true,
                ],
                'end_date' => [
                    'type' => 'DATE',
                    'null' => true,
                ],
                'age_group_id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                    'null' => true,
                ],
                'age_group_label' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => true,
                ],
                'coaching_plan_id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                    'null' => true,
                ],
                'coaching_plan_label' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => true,
                ],
                'fee_plan_id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                    'null' => true,
                ],
                'fee_plan_label' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => true,
                ],
                'hostel_plan_id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                    'null' => true,
                ],
                'hostel_plan_label' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => true,
                ],
                'academic_plan_id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                    'null' => true,
                ],
                'academic_plan_label' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => true,
                ],
                'status' => [
                    'type' => 'VARCHAR',
                    'constraint' => 30,
                    'default' => 'current',
                ],
                'created_by' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                    'null' => true,
                ],
                'request_hash' => [
                    'type' => 'CHAR',
                    'constraint' => 64,
                    'null' => true,
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
            $this->forge->addKey(['admission_id', 'period_number'], false, true);
            $this->forge->addKey(['admission_id', 'request_hash'], false, true);
            $this->forge->addKey(['branch_id', 'status']);
            $this->forge->addForeignKey('admission_id', 'admissions', 'id', 'RESTRICT', 'CASCADE');
            $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
            $this->forge->createTable('admission_periods', true);
        }

        if ($this->db->tableExists('admissions') && ! $this->db->fieldExists('renewal_version', 'admissions')) {
            $this->forge->addColumn('admissions', [
                'renewal_version' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'default' => 0,
                ],
            ]);
        }
    }

    public function down(): void
    {
        // Forward-only: Admission period history must never be removed by rollback.
    }
}
