<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddEquipmentUpdateDelete extends Migration
{
    private const PERMISSIONS = [
        'equipment.update',
        'equipment.delete',
    ];

    private const AUTHORIZED_ROLES = [
        'Super Admin',
        'Branch Admin',
    ];

    public function up(): void
    {
        if ($this->db->tableExists('equipment')
            && ! $this->db->fieldExists('deleted_at', 'equipment')) {
            $this->forge->addColumn('equipment', [
                'deleted_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
        }

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
        // Forward-only so soft-deleted inventory and audit history are preserved.
    }
}
