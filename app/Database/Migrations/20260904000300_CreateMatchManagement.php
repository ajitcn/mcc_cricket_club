<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateMatchManagement extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'branch_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'ground_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'coach_user_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'opponent' => ['type' => 'VARCHAR', 'constraint' => 150],
            'match_type' => ['type' => 'VARCHAR', 'constraint' => 30],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'scheduled'],
            'starts_at' => ['type' => 'DATETIME'],
            'ends_at' => ['type' => 'DATETIME'],
            'result' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'score_summary' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'coach_remarks' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['branch_id', 'starts_at']);
        $this->forge->addKey(['ground_id', 'starts_at', 'ends_at']);
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('ground_id', 'grounds', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('coach_user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('matches', true);

        $this->forge->addField([
            'match_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'player_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'selection_type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey(['match_id', 'player_id'], true);
        $this->forge->addKey('player_id');
        $this->forge->addForeignKey('match_id', 'matches', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('player_id', 'players', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('match_players', true);

        $permissions = [
            'Super Admin' => ['match.view', 'match.create', 'match.update'],
            'Branch Admin' => ['match.view', 'match.create', 'match.update'],
            'Coach' => ['match.view', 'match.create', 'match.update'],
        ];
        foreach ($this->db->table('roles')->select('id, name')->get()->getResultArray() as $role) {
            foreach ($permissions[$role['name']] ?? [] as $permission) {
                if ($this->db->table('role_permissions')->where(['role_id' => $role['id'], 'permission' => $permission])->countAllResults() === 0) {
                    $this->db->table('role_permissions')->insert(['role_id' => $role['id'], 'permission' => $permission, 'created_at' => date('Y-m-d H:i:s')]);
                }
            }
        }
    }

    public function down(): void
    {
        // Match schema is forward-only; production history must not be removed by rollback.
    }
}
