<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddMarketingDashboardPermission extends Migration
{
    public function up(): void
    {
        foreach ($this->db->table('roles')->select('id, name')->get()->getResultArray() as $role) {
            if (! in_array($role['name'], ['Super Admin', 'Branch Admin', 'Admission Team'], true)) {
                continue;
            }

            $exists = $this->db->table('role_permissions')
                ->where(['role_id' => $role['id'], 'permission' => 'marketing.view'])
                ->countAllResults();

            if ($exists === 0) {
                $this->db->table('role_permissions')->insert([
                    'role_id' => $role['id'],
                    'permission' => 'marketing.view',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Permissions are forward-only for this deployment.
    }
}
