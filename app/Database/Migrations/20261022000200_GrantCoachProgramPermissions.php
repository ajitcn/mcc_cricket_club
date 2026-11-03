<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class GrantCoachProgramPermissions extends Migration
{
    private const PERMISSIONS = [
        'program.view',
        'program.create',
    ];

    public function up(): void
    {
        $coach = $this->db
            ->table('roles')
            ->select('id')
            ->where('name', 'Coach')
            ->get()
            ->getRowArray();

        if (! is_array($coach) || ! isset($coach['id'])) {
            return;
        }

        foreach (self::PERMISSIONS as $permission) {
            $exists = $this->db
                ->table('role_permissions')
                ->where([
                    'role_id' => (int) $coach['id'],
                    'permission' => $permission,
                ])
                ->countAllResults() > 0;

            if ($exists) {
                continue;
            }

            $this->db->table('role_permissions')->insert([
                'role_id' => (int) $coach['id'],
                'permission' => $permission,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        }
    }

    public function down(): void
    {
        // Forward-only: permission history is retained.
    }
}
