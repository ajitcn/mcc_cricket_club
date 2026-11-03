<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateNotifications extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'branch_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'title' => ['type' => 'VARCHAR', 'constraint' => 180],
            'body' => ['type' => 'TEXT'],
            'category' => ['type' => 'VARCHAR', 'constraint' => 50],
            'source_type' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'source_id' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'created_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['branch_id', 'created_at']);
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('notifications', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'notification_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'recipient_user_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'read_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['notification_id', 'recipient_user_id']);
        $this->forge->addKey(['recipient_user_id', 'read_at']);
        $this->forge->addForeignKey('notification_id', 'notifications', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('recipient_user_id', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('notification_recipients', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'notification_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'action' => ['type' => 'VARCHAR', 'constraint' => 30],
            'actor_user_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'metadata' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['notification_id', 'created_at']);
        $this->forge->addForeignKey('notification_id', 'notifications', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('actor_user_id', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('notification_history', true);

        foreach ($this->db->table('roles')->select('id,name')->get()->getResultArray() as $role) {
            if (in_array($role['name'], ['Super Admin', 'Branch Admin', 'Coach'], true)) {
                foreach (['notification.view', 'notification.create', 'notification.read'] as $permission) {
                    if ($this->db->table('role_permissions')->where(['role_id' => $role['id'], 'permission' => $permission])->countAllResults() === 0) {
                        $this->db->table('role_permissions')->insert([
                            'role_id' => $role['id'],
                            'permission' => $permission,
                            'created_at' => date('Y-m-d H:i:s'),
                        ]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        // Notification schema is forward-only.
    }
}
