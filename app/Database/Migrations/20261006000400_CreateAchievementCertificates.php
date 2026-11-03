<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL. Published certificates only. */
final class CreateAchievementCertificates extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'award_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'branch_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'storage_key' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'mime_type' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'default' => 'text/html',
            ],
            'file_size' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
            ],
            'generated_by' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'generated_at' => [
                'type' => 'DATETIME',
            ],
            'updated_at' => [
                'type' => 'DATETIME',
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('award_id');
        $this->forge->addKey(['branch_id', 'generated_at']);
        $this->forge->addForeignKey('award_id', 'achievement_awards', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('generated_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('achievement_certificates', true);

        foreach (['Super Admin', 'Branch Admin'] as $roleName) {
            $role = $this->db->table('roles')->where('name', $roleName)->get()->getRowArray();
            if (! is_array($role)) {
                continue;
            }

            foreach (['achievement.certificate.generate', 'achievement.certificate.download'] as $permission) {
                $exists = $this->db->table('role_permissions')->where([
                    'role_id' => $role['id'],
                    'permission' => $permission,
                ])->countAllResults();
                if ($exists === 0) {
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
        // Forward-only: issued certificate history must not be deleted by rollback.
    }
}
