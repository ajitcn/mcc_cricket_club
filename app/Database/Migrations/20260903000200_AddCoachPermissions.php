<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddCoachPermissions extends Migration
{
    private const ROLE_PERMISSIONS = [
        'Super Admin' => [
            'coach.view',
            'coach.create',
            'coach.update',
            'coach.assign',
            'coach.kpi.view',
        ],
        'Branch Admin' => [
            'coach.view',
            'coach.create',
            'coach.update',
            'coach.assign',
            'coach.kpi.view',
        ],
        'Coach' => [
            'coach.view',
            'coach.kpi.view',
        ],
    ];

    public function up(): void
    {
        $db = $this->db;
        $now = date('Y-m-d H:i:s');

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = $db->table('roles')
                ->select('id')
                ->where('name', $roleName)
                ->get()
                ->getRowArray();

            if (!is_array($role)) {
                continue;
            }

            foreach ($permissions as $permission) {
                $exists = $db->table('role_permissions')
                    ->where([
                        'role_id' => (int) $role['id'],
                        'permission' => $permission,
                    ])
                    ->countAllResults() > 0;

                if (!$exists) {
                    $db->table('role_permissions')->insert([
                        'role_id' => (int) $role['id'],
                        'permission' => $permission,
                        'created_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = $this->db->table('roles')
                ->select('id')
                ->where('name', $roleName)
                ->get()
                ->getRowArray();

            if (!is_array($role)) {
                continue;
            }

            $this->db->table('role_permissions')
                ->where('role_id', (int) $role['id'])
                ->whereIn('permission', $permissions)
                ->delete();
        }
    }
}
