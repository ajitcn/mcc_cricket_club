<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateAdmissionInstallmentPayments extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('admission_installment_payments')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'installment_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
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
            'payment_type' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
            ],
            'amount_received' => [
                'type' => 'DECIMAL',
                'constraint' => '12,2',
                'unsigned' => true,
            ],
            'payment_date' => [
                'type' => 'DATE',
            ],
            'payment_method' => [
                'type' => 'VARCHAR',
                'constraint' => 30,
            ],
            'receipt_number' => [
                'type' => 'VARCHAR',
                'constraint' => 80,
            ],
            'reference_number' => [
                'type' => 'VARCHAR',
                'constraint' => 120,
                'null' => true,
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'received_by' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'entry_type' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'payment',
            ],
            'correction_of_payment_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'null' => true,
            ],
            'reason' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
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
        $this->forge->addKey(['branch_id', 'receipt_number'], false, true);
        $this->forge->addKey(['installment_id', 'payment_type']);
        $this->forge->addKey(['admission_id', 'branch_id']);
        $this->forge->addForeignKey('admission_id', 'admissions', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('received_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey(
            'correction_of_payment_id',
            'admission_installment_payments',
            'id',
            'RESTRICT',
            'CASCADE',
        );
        $this->forge->createTable('admission_installment_payments', true);
    }

    public function down(): void
    {
        // Forward-only: posted payment history must never be removed by rollback.
    }
}
