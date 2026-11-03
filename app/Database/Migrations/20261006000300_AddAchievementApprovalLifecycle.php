<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL. Achievement lifecycle only. */
final class AddAchievementApprovalLifecycle extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('achievement_awards', [
            'version' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'default' => 1,
            ],
            'approved_by' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'null' => true,
            ],
            'approved_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'published_by' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'null' => true,
            ],
            'published_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'revoked_by' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'null' => true,
            ],
            'revoked_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'lifecycle_reason' => [
                'type' => 'VARCHAR',
                'constraint' => 1000,
                'null' => true,
            ],
        ]);

        $this->forge->addKey(['status', 'branch_id']);
        $this->forge->addKey(['status', 'published_at']);
        $this->forge->addForeignKey('approved_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('published_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('revoked_by', 'users', 'id', 'RESTRICT', 'CASCADE');

        foreach ([
            ['name' => 'Super Admin', 'permission' => 'achievement.approve'],
            ['name' => 'Branch Admin', 'permission' => 'achievement.approve'],
            ['name' => 'Super Admin', 'permission' => 'achievement.publish'],
            ['name' => 'Branch Admin', 'permission' => 'achievement.publish'],
            ['name' => 'Super Admin', 'permission' => 'achievement.revoke'],
            ['name' => 'Branch Admin', 'permission' => 'achievement.revoke'],
        ] as $mapping) {
            $role = $this->db->table('roles')->where('name', $mapping['name'])->get()->getRowArray();
            if (! is_array($role)) {
                continue;
            }

            $exists = $this->db->table('role_permissions')
                ->where([
                    'role_id' => $role['id'],
                    'permission' => $mapping['permission'],
                ])
                ->countAllResults();
            if ($exists === 0) {
                $this->db->table('role_permissions')->insert([
                    'role_id' => $role['id'],
                    'permission' => $mapping['permission'],
                    'created_at' => gmdate('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Forward-only: lifecycle and audit history must not be removed by rollback.
    }
}
