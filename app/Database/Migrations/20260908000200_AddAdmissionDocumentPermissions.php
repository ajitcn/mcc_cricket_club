<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddAdmissionDocumentPermissions extends Migration
{
    private const PERMISSIONS = [
        'document.view',
        'document.upload',
        'document.download',
    ];

    public function up(): void
    {
        $role = $this->db->table('roles')
            ->select('id')
            ->where('name', 'Admission Team')
            ->get()
            ->getRowArray();

        if (! is_array($role)) {
            return;
        }

        foreach (self::PERMISSIONS as $permission) {
            if ($this->db->table('role_permissions')->where([
                'role_id' => (int) $role['id'],
                'permission' => $permission,
            ])->countAllResults() === 0) {
                $this->db->table('role_permissions')->insert([
                    'role_id' => (int) $role['id'],
                    'permission' => $permission,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    public function down(): void
    {
        $role = $this->db->table('roles')
            ->select('id')
            ->where('name', 'Admission Team')
            ->get()
            ->getRowArray();

        if (is_array($role)) {
            $this->db->table('role_permissions')
                ->where('role_id', (int) $role['id'])
                ->whereIn('permission', self::PERMISSIONS)
                ->delete();
        }
    }
}
