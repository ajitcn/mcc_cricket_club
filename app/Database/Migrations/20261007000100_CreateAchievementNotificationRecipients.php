<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL. A3-04 recipient boundary. */
final class CreateAchievementNotificationRecipients extends Migration
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
            'player_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'user_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'branch_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['player_id', 'user_id']);
        $this->forge->addKey(['user_id', 'branch_id']);
        $this->forge->addForeignKey('player_id', 'players', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('player_user_links', true);

        foreach (['Parent', 'Player'] as $roleName) {
            $role = $this->db->table('roles')->where('name', $roleName)->get()->getRowArray();
            if (! is_array($role)) {
                continue;
            }

            foreach (['notification.view', 'notification.read'] as $permission) {
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
        // Notification recipient ownership is forward-only.
    }
}
