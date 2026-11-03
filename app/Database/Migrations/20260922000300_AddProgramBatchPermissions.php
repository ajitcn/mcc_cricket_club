<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddProgramBatchPermissions extends Migration
{
    private const PERMISSIONS = [
        'program.view',
        'program.create',
        'batch.view',
        'batch.create',
    ];

    private const AUTHORIZED_ROLES = [
        'Super Admin',
        'Branch Admin',
    ];

    public function up(): void
    {
        $roles = $this->db
            ->table('roles')
            ->select('id, name')
            ->get()
            ->getResultArray();

        foreach ($roles as $role) {
            if (! in_array($role['name'], self::AUTHORIZED_ROLES, true)) {
                continue;
            }

            foreach (self::PERMISSIONS as $permission) {
                $exists = $this->db->table('role_permissions')
                    ->where([
                        'role_id' => $role['id'],
                        'permission' => $permission,
                    ])
                    ->countAllResults() > 0;

                if ($exists) {
                    continue;
                }

                $this->db->table('role_permissions')->insert([
                    'role_id' => $role['id'],
                    'permission' => $permission,
                    'created_at' => gmdate('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Forward-only: permission history is retained.
    }
}
