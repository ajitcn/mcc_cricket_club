<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddTrialBookPermission extends Migration
{
    public function up(): void
    {
        $roles = $this->db
            ->table('role_permissions')
            ->select('role_id')
            ->where('permission', 'lead.update')
            ->groupBy('role_id')
            ->get()
            ->getResultArray();

        foreach ($roles as $role) {
            $roleId = (int) $role['role_id'];
            $exists = $this->db
                ->table('role_permissions')
                ->where([
                    'role_id' => $roleId,
                    'permission' => 'trial.book',
                ])
                ->countAllResults() > 0;

            if ($exists) {
                continue;
            }

            $this->db->table('role_permissions')->insert([
                'role_id' => $roleId,
                'permission' => 'trial.book',
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        }
    }

    public function down(): void
    {
        // Forward-only to preserve role-permission history.
    }
}
