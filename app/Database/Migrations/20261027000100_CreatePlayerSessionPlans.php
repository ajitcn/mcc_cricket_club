<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreatePlayerSessionPlans extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('player_session_plans')) {
            $fields = [
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
                'coach_user_id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                ],
                'branch_id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                ],
                'problem' => ['type' => 'TEXT'],
                'outcome' => ['type' => 'TEXT'],
                'method' => ['type' => 'TEXT'],
                'players' => ['type' => 'TEXT'],
                'stage' => ['type' => 'TEXT'],
                'area' => ['type' => 'TEXT'],
                'safety_considerations' => ['type' => 'TEXT'],
                'technical' => ['type' => 'TEXT'],
                'tactical' => ['type' => 'TEXT'],
                'mental' => ['type' => 'TEXT'],
                'physical' => ['type' => 'TEXT'],
                'level_1_fixed_variable' => ['type' => 'TEXT'],
                'level_1_coaching_tools' => ['type' => 'TEXT'],
                'level_1_equipment' => ['type' => 'TEXT'],
                'level_1_easier' => ['type' => 'TEXT'],
                'level_1_harder' => ['type' => 'TEXT'],
                'level_2_fixed_variable' => ['type' => 'TEXT'],
                'level_2_coaching_tools' => ['type' => 'TEXT'],
                'level_2_equipment' => ['type' => 'TEXT'],
                'level_2_easier' => ['type' => 'TEXT'],
                'level_2_harder' => ['type' => 'TEXT'],
                'debrief_fixed_variable' => ['type' => 'TEXT'],
                'debrief_coaching_tools' => ['type' => 'TEXT'],
                'debrief_equipment' => ['type' => 'TEXT'],
                'debrief_easier' => ['type' => 'TEXT'],
                'debrief_harder' => ['type' => 'TEXT'],
                'version' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'default' => 1,
                ],
                'created_at' => ['type' => 'DATETIME'],
                'updated_at' => ['type' => 'DATETIME'],
            ];

            $this->forge->addField($fields);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['player_id', 'branch_id']);
            $this->forge->addKey(['coach_user_id', 'branch_id']);
            $this->forge->addKey(['branch_id', 'created_at']);
            $this->forge->addForeignKey('player_id', 'players', 'id', 'RESTRICT', 'CASCADE');
            $this->forge->addForeignKey('coach_user_id', 'users', 'id', 'RESTRICT', 'CASCADE');
            $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
            $this->forge->createTable('player_session_plans', true);
        }

        if (! $this->db->tableExists('player_session_plan_history')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'plan_id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                ],
                'version' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                ],
                'snapshot' => ['type' => 'LONGTEXT'],
                'created_by' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                ],
                'created_at' => ['type' => 'DATETIME'],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['plan_id', 'version']);
            $this->forge->addForeignKey('plan_id', 'player_session_plans', 'id', 'RESTRICT', 'RESTRICT');
            $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'CASCADE');
            $this->forge->createTable('player_session_plan_history', true);
        }

        foreach ($this->db->table('roles')->select('id, name')->get()->getResultArray() as $role) {
            if (! in_array($role['name'], ['Super Admin', 'Branch Admin', 'Coach'], true)) {
                continue;
            }

            foreach (['player.session_plan.view', 'player.session_plan.manage'] as $permission) {
                if ($this->db->table('role_permissions')->where([
                    'role_id' => $role['id'],
                    'permission' => $permission,
                ])->countAllResults() === 0) {
                    $this->db->table('role_permissions')->insert([
                        'role_id' => $role['id'],
                        'permission' => $permission,
                        'created_at' => gmdate('Y-m-d H:i:s'),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Session Plan storage is forward-only so plan history cannot be lost.
    }
}
