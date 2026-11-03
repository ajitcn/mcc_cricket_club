<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddFitnessSoftDeletion extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('fitness_assessments', ['deleted_at' => ['type' => 'DATETIME', 'null' => true]]);
        $this->forge->addColumn('fitness_plans', ['deleted_at' => ['type' => 'DATETIME', 'null' => true]]);
        $permissions = ['fitness.delete', 'fitness.plan.delete'];
        foreach ($this->db->table('roles')->select('id, name')->get()->getResultArray() as $role) {
            if (!in_array($role['name'], ['Super Admin', 'Branch Admin', 'Fitness Coach'], true)) {
                continue;
            }
            foreach ($permissions as $permission) {
                if ($this->db->table('role_permissions')->where(['role_id' => $role['id'], 'permission' => $permission])->countAllResults() === 0) {
                    $this->db->table('role_permissions')->insert(['role_id' => $role['id'], 'permission' => $permission, 'created_at' => date('Y-m-d H:i:s')]);
                }
            }
        }
    }

    public function down(): void
    {
        // Forward-only: fitness history must survive rollback.
    }
}
