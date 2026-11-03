<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddPlayerSessionPlanViewPermission extends Migration
{
    public function up(): void
    {
        foreach ($this->db->table('roles')->select('id, name')->get()->getResultArray() as $role) {
            if (! in_array($role['name'], ['Super Admin', 'Branch Admin', 'Coach'], true)) {
                continue;
            }

            if ($this->db->table('role_permissions')->where([
                'role_id' => $role['id'],
                'permission' => 'player.session_plan.view',
            ])->countAllResults() === 0) {
                $this->db->table('role_permissions')->insert([
                    'role_id' => $role['id'],
                    'permission' => 'player.session_plan.view',
                    'created_at' => gmdate('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Permission grants are forward-only and must not revoke existing access.
    }
}
