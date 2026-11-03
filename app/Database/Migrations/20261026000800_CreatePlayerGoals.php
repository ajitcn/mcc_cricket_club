<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreatePlayerGoals extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('player_goals')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'player_id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                ],
                'branch_id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                ],
                'coach_user_id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                ],
                'title' => [
                    'type' => 'VARCHAR',
                    'constraint' => 150,
                ],
                'description' => [
                    'type' => 'TEXT',
                ],
                'version' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'default' => 1,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('player_id');
            $this->forge->addKey(['branch_id', 'coach_user_id']);
            $this->forge->addForeignKey('player_id', 'players', 'id', 'RESTRICT', 'CASCADE');
            $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
            $this->forge->addForeignKey('coach_user_id', 'users', 'id', 'RESTRICT', 'CASCADE');
            $this->forge->createTable('player_goals', true);
        }

        foreach ($this->db->table('roles')->select('id, name')->get()->getResultArray() as $role) {
            if (! in_array($role['name'], ['Super Admin', 'Branch Admin', 'Coach'], true)) {
                continue;
            }

            if ($this->db->table('role_permissions')->where([
                'role_id' => $role['id'],
                'permission' => 'player.goal.set',
            ])->countAllResults() === 0) {
                $this->db->table('role_permissions')->insert([
                    'role_id' => $role['id'],
                    'permission' => 'player.goal.set',
                    'created_at' => gmdate('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Player goal storage is forward-only to preserve historical goal data.
    }
}
