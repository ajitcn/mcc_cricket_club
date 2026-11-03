<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateOpponents extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'branch_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['branch_id', 'name']);
        $this->forge->addKey(['branch_id', 'is_active']);
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('opponents', true);

        if (! $this->db->fieldExists('opponent_id', 'matches')) {
            $this->forge->addColumn('matches', [
                'opponent_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true, 'after' => 'opponent'],
            ]);
        }

        if ($this->db->query("SHOW INDEX FROM matches WHERE Key_name = 'matches_opponent_id_index'")->getNumRows() === 0) {
            $this->db->query('ALTER TABLE matches ADD INDEX matches_opponent_id_index (opponent_id)');
        }

        if ($this->db->query(
            "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
             AND TABLE_NAME = 'matches'
             AND CONSTRAINT_NAME = 'matches_opponent_id_foreign'
             AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
        )->getNumRows() === 0) {
            $this->db->query('ALTER TABLE matches ADD CONSTRAINT matches_opponent_id_foreign FOREIGN KEY (opponent_id) REFERENCES opponents(id) ON DELETE RESTRICT ON UPDATE CASCADE');
        }

        $this->db->query(
            'INSERT INTO opponents (branch_id, name, is_active, created_at, updated_at)
             SELECT DISTINCT branch_id, opponent, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()
             FROM matches
             WHERE opponent IS NOT NULL AND TRIM(opponent) <> ""
             ON DUPLICATE KEY UPDATE name = VALUES(name)'
        );
        $this->db->query(
            'UPDATE matches m JOIN opponents o ON o.branch_id = m.branch_id AND o.name = m.opponent
             SET m.opponent_id = o.id WHERE m.opponent_id IS NULL'
        );

        $permissions = [
            'Super Admin' => ['opponent.view', 'opponent.create', 'opponent.update'],
            'Branch Admin' => ['opponent.view', 'opponent.create', 'opponent.update'],
            'Coach' => ['opponent.view'],
        ];
        foreach ($this->db->table('roles')->select('id, name')->get()->getResultArray() as $role) {
            foreach ($permissions[$role['name']] ?? [] as $permission) {
                if ($this->db->table('role_permissions')->where(['role_id' => $role['id'], 'permission' => $permission])->countAllResults() === 0) {
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
        // Forward-only catalog migration.
    }
}
