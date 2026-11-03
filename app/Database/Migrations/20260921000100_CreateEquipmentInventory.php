<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateEquipmentInventory extends Migration
{
    private const PERMISSIONS = [
        'equipment.view',
        'equipment.create',
    ];

    private const AUTHORIZED_ROLES = [
        'Super Admin',
        'Branch Admin',
    ];

    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'name' => [
                'type' => 'VARCHAR',
                'constraint' => 150,
            ],
            'quantity' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
            ],
            'branch_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'created_by' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
            'updated_at' => [
                'type' => 'DATETIME',
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['branch_id', 'name']);
        $this->forge->addForeignKey(
            'branch_id',
            'branches',
            'id',
            'RESTRICT',
            'CASCADE'
        );
        $this->forge->addForeignKey(
            'created_by',
            'users',
            'id',
            'RESTRICT',
            'CASCADE'
        );
        $this->forge->createTable('equipment', true);

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

                if (! $exists) {
                    $this->db->table('role_permissions')->insert([
                        'role_id' => $role['id'],
                        'permission' => $permission,
                        'created_at' => gmdate('Y-m-d H:i:s'),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Equipment inventory is forward-only so deployed records are preserved.
    }
}
