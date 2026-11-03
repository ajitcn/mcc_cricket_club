<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreatePerformanceRecords extends Migration
{
    private const PERMISSIONS = [
        'performance.view',
        'performance.create',
        'performance.update',
        'performance.delete',
    ];

    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'branch_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'player_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'session_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'record_type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'recorded_on' => ['type' => 'DATE'],
            'runs' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'balls_faced' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'dismissals' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'fours' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'sixes' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'wickets' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'runs_conceded' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'legal_balls' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'catches' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'stumpings' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'run_outs' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'recorded_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['branch_id', 'recorded_on']);
        $this->forge->addKey(['player_id', 'recorded_on']);
        $this->forge->addKey(['session_id', 'player_id']);
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('player_id', 'players', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('session_id', 'sessions', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('recorded_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('performance_records', true);

        $now = date('Y-m-d H:i:s');
        foreach ($this->db->table('roles')->select('id, name')->get()->getResultArray() as $role) {
            if (!in_array($role['name'], ['Super Admin', 'Branch Admin', 'Coach'], true)) {
                continue;
            }
            foreach (self::PERMISSIONS as $permission) {
                if ($this->db->table('role_permissions')->where([
                    'role_id' => (int) $role['id'],
                    'permission' => $permission,
                ])->countAllResults() === 0) {
                    $this->db->table('role_permissions')->insert([
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
        // Forward-only: performance history must not be removed by rollback.
    }
}
