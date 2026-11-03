<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateGroundManagement extends Migration
{
    private const PERMISSIONS = [
        'ground.view',
        'ground.create',
        'ground.update',
        'ground.book',
        'ground.maintenance',
    ];

    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'branch_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'ground_type' => ['type' => 'VARCHAR', 'constraint' => 30],
            'description' => ['type' => 'TEXT', 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['branch_id', 'name']);
        $this->forge->addKey(['branch_id', 'is_active']);
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('grounds', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'ground_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'branch_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'starts_at' => ['type' => 'DATETIME'],
            'ends_at' => ['type' => 'DATETIME'],
            'reason' => ['type' => 'VARCHAR', 'constraint' => 255],
            'created_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['ground_id', 'starts_at', 'ends_at']);
        $this->forge->addForeignKey('ground_id', 'grounds', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('ground_unavailability', true);

        if (!$this->db->fieldExists('ground_id', 'sessions')) {
            $this->forge->addColumn('sessions', [
                'ground_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            ]);
            $this->db->query('ALTER TABLE sessions ADD INDEX sessions_ground_id_index (ground_id), ADD CONSTRAINT sessions_ground_id_foreign FOREIGN KEY (ground_id) REFERENCES grounds(id) ON UPDATE CASCADE ON DELETE RESTRICT');
        }

        $rolePermissions = [
            'Super Admin' => self::PERMISSIONS,
            'Branch Admin' => self::PERMISSIONS,
            'Coach' => ['ground.view', 'ground.book'],
            'Fitness Coach' => ['ground.view'],
        ];
        foreach ($this->db->table('roles')->select('id, name')->get()->getResultArray() as $role) {
            foreach ($rolePermissions[$role['name']] ?? [] as $permission) {
                if ($this->db->table('role_permissions')->where(['role_id' => $role['id'], 'permission' => $permission])->countAllResults() === 0) {
                    $this->db->table('role_permissions')->insert([
                        'role_id' => $role['id'],
                        'permission' => $permission,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Ground schema is forward-only; production records must not be removed by rollback.
    }
}
