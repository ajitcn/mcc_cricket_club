<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateAdmissionInstallmentRows extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('admission_installment_rows')) {
            return;
        }

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
            'year_number' => [
                'type' => 'TINYINT',
                'constraint' => 3,
                'unsigned' => true,
            ],
            'installment_1' => [
                'type' => 'DECIMAL',
                'constraint' => '12,2',
                'unsigned' => true,
                'null' => true,
            ],
            'installment_2' => [
                'type' => 'DECIMAL',
                'constraint' => '12,2',
                'unsigned' => true,
                'null' => true,
            ],
            'installment_3' => [
                'type' => 'DECIMAL',
                'constraint' => '12,2',
                'unsigned' => true,
                'null' => true,
            ],
            'installment_4' => [
                'type' => 'DECIMAL',
                'constraint' => '12,2',
                'unsigned' => true,
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['admission_id', 'year_number'], false, true);
        $this->forge->addForeignKey('admission_id', 'admissions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('admission_installment_rows', true);
    }

    public function down(): void
    {
        // Forward-only: persisted admission payment-plan data must not be removed by rollback.
    }
}
