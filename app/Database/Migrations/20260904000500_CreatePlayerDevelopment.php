<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreatePlayerDevelopment extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'branch_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'player_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'coach_user_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'title' => ['type' => 'VARCHAR', 'constraint' => 150],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'active'],
            'target_date' => ['type' => 'DATE', 'null' => true],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME'], 'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['branch_id', 'player_id', 'status']);
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('player_id', 'players', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('coach_user_id', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('development_plans', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'plan_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'goal' => ['type' => 'VARCHAR', 'constraint' => 255],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'open'],
            'target_date' => ['type' => 'DATE', 'null' => true],
            'progress_value' => ['type' => 'DECIMAL', 'constraint' => '8,2', 'null' => true],
            'created_at' => ['type' => 'DATETIME'], 'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['plan_id', 'status']);
        $this->forge->addForeignKey('plan_id', 'development_plans', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('development_goals', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'goal_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'progress_value' => ['type' => 'DECIMAL', 'constraint' => '8,2', 'null' => true],
            'note' => ['type' => 'TEXT', 'null' => true],
            'recorded_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'recorded_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['goal_id', 'recorded_at']);
        $this->forge->addForeignKey('goal_id', 'development_goals', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('recorded_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('development_progress_history', true);

        $permissions = ['development.view', 'development.create', 'development.update', 'development.progress'];
        foreach ($this->db->table('roles')->select('id, name')->get()->getResultArray() as $role) {
            if (in_array($role['name'], ['Super Admin', 'Branch Admin', 'Coach'], true)) {
                foreach ($permissions as $permission) {
                    if ($this->db->table('role_permissions')->where(['role_id' => $role['id'], 'permission' => $permission])->countAllResults() === 0) {
                        $this->db->table('role_permissions')->insert(['role_id' => $role['id'], 'permission' => $permission, 'created_at' => date('Y-m-d H:i:s')]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        // Development schema is forward-only.
    }
}
