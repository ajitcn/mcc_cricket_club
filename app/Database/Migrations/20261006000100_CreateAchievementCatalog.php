<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.
 * Bounded Achievement catalog only; award workflows remain deferred.
 */
final class CreateAchievementCatalog extends Migration
{
    private const PERMISSIONS = [
        'achievement.view',
        'achievement.create',
        'achievement.update',
    ];

    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'branch_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'category' => ['type' => 'VARCHAR', 'constraint' => 100],
            'title' => ['type' => 'VARCHAR', 'constraint' => 150],
            'description' => ['type' => 'TEXT', 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['branch_id', 'title']);
        $this->forge->addKey(['branch_id', 'is_active']);
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('achievements', true);

        foreach ($this->db->table('roles')->select('id, name')->get()->getResultArray() as $role) {
            if (! in_array($role['name'], ['Super Admin', 'Branch Admin'], true)) {
                continue;
            }

            foreach (self::PERMISSIONS as $permission) {
                if ($this->db->table('role_permissions')->where([
                    'role_id' => $role['id'],
                    'permission' => $permission,
                ])->countAllResults() === 0) {
                    $this->db->table('role_permissions')->insert([
                        'role_id' => $role['id'],
                        'permission' => $permission,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Forward-only: catalog and audit history must not be removed by rollback.
    }
}
