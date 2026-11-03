<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddSessionViewPermission extends Migration
{
    public function up(): void
    {
        foreach ($this->db->table('roles')->select('id')->get()->getResultArray() as $role) {
            $hasUpdate = $this->db->table('role_permissions')
                ->where([
                    'role_id' => $role['id'],
                    'permission' => 'session.update',
                ])
                ->countAllResults() > 0;

            if ($hasUpdate && $this->db->table('role_permissions')->where([
                'role_id' => $role['id'],
                'permission' => 'session.view',
            ])->countAllResults() === 0) {
                $this->db->table('role_permissions')->insert([
                    'role_id' => $role['id'],
                    'permission' => 'session.view',
                    'created_at' => gmdate('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Permissions are append-only and are restored by AuthorizationRestoreSeeder.
    }
}
