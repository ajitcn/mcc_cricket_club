<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateDocumentManagement extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'branch_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'player_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'document_type' => ['type' => 'VARCHAR', 'constraint' => 40],
            'original_name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'storage_key' => ['type' => 'VARCHAR', 'constraint' => 255],
            'mime_type' => ['type' => 'VARCHAR', 'constraint' => 100],
            'file_size' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'expires_on' => ['type' => 'DATE', 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'active'],
            'uploaded_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME'], 'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true); $this->forge->addKey(['branch_id', 'player_id', 'document_type']);
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('player_id', 'players', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('uploaded_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('documents', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'document_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'action' => ['type' => 'VARCHAR', 'constraint' => 30],
            'actor_user_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'metadata' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true); $this->forge->addKey(['document_id', 'created_at']);
        $this->forge->addForeignKey('document_id', 'documents', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('actor_user_id', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('document_history', true);

        foreach ($this->db->table('roles')->select('id,name')->get()->getResultArray() as $role) {
            if (in_array($role['name'], ['Super Admin', 'Branch Admin', 'Coach', 'Fitness Coach'], true)) {
                foreach (['document.view', 'document.upload', 'document.download'] as $permission) {
                    if ($this->db->table('role_permissions')->where(['role_id' => $role['id'], 'permission' => $permission])->countAllResults() === 0) {
                        $this->db->table('role_permissions')->insert(['role_id' => $role['id'], 'permission' => $permission, 'created_at' => date('Y-m-d H:i:s')]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        // Document schema is forward-only.
    }
}
