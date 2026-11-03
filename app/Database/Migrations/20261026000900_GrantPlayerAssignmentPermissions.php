<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class GrantPlayerAssignmentPermissions extends Migration
{
    public function up(): void
    {
        $permissions = [
            'player.batch.assign',
            'player.coach.assign',
        ];

        foreach ($this->db->table('roles')->select('id, name')->get()->getResultArray() as $role) {
            if (! in_array($role['name'], [
                'Super Admin',
                'Branch Admin',
                'Admission Team',
                'Coach',
            ], true)) {
                continue;
            }

            foreach ($permissions as $permission) {
                if ($this->db->table('role_permissions')->where([
                    'role_id' => $role['id'],
                    'permission' => $permission,
                ])->countAllResults() > 0) {
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
        // Forward-only authorization correction.
    }
}
