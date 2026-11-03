<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateAdmissionFollowups extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('admission_followups')) {
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
            'branch_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'follow_up_at' => [
                'type' => 'DATETIME',
            ],
            'follow_up_type' => [
                'type' => 'VARCHAR',
                'constraint' => 40,
            ],
            'status' => [
                'type' => 'VARCHAR',
                'constraint' => 40,
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'outcome' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'next_follow_up_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'assigned_user_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'null' => true,
            ],
            'created_by' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'updated_by' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
            'updated_at' => [
                'type' => 'DATETIME',
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['admission_id', 'follow_up_at']);
        $this->forge->addKey(['branch_id', 'status']);
        $this->forge->addKey('follow_up_at');
        $this->forge->addKey('next_follow_up_at');
        $this->forge->addForeignKey('admission_id', 'admissions', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('assigned_user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('updated_by', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('admission_followups', true);
    }

    public function down(): void
    {
        // Forward-only: historical Admission follow-up records must not be removed.
    }
}
