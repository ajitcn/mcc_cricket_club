<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateFitnessManagement extends Migration
{
    private const PERMISSIONS = [
        'fitness.view',
        'fitness.create',
        'fitness.update',
        'fitness.plan.view',
        'fitness.plan.create',
        'fitness.plan.update',
        'fitness.kpi.view',
    ];

    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'player_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'branch_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'assessment_date' => ['type' => 'DATE'],
            'height_cm' => ['type' => 'DECIMAL', 'constraint' => '6,2', 'null' => true],
            'weight_kg' => ['type' => 'DECIMAL', 'constraint' => '6,2', 'null' => true],
            'body_fat_percent' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
            'vertical_jump_cm' => ['type' => 'DECIMAL', 'constraint' => '6,2', 'null' => true],
            'sprint_time_seconds' => ['type' => 'DECIMAL', 'constraint' => '6,2', 'null' => true],
            'agility_seconds' => ['type' => 'DECIMAL', 'constraint' => '6,2', 'null' => true],
            'flexibility_cm' => ['type' => 'DECIMAL', 'constraint' => '6,2', 'null' => true],
            'yoyo_level' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
            'endurance_minutes' => ['type' => 'DECIMAL', 'constraint' => '7,2', 'null' => true],
            'coach_notes' => ['type' => 'TEXT', 'null' => true],
            'recorded_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['branch_id', 'assessment_date']);
        $this->forge->addKey(['player_id', 'assessment_date']);
        $this->forge->addForeignKey('player_id', 'players', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('recorded_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('fitness_assessments', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'player_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'branch_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'coach_user_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'goals' => ['type' => 'TEXT', 'null' => true],
            'starts_on' => ['type' => 'DATE'],
            'ends_on' => ['type' => 'DATE', 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'active'],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['branch_id', 'status']);
        $this->forge->addKey(['player_id', 'starts_on']);
        $this->forge->addForeignKey('player_id', 'players', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('coach_user_id', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('fitness_plans', true);

        $db = $this->db;
        $now = date('Y-m-d H:i:s');
        $roles = $db->table('roles')->select('id, name')->get()->getResultArray();
        $rolePermissions = [
            'Super Admin' => self::PERMISSIONS,
            'Branch Admin' => self::PERMISSIONS,
            'Fitness Coach' => self::PERMISSIONS,
            'Coach' => [
                'fitness.view', 'fitness.create', 'fitness.update', 'fitness.plan.view',
                'fitness.plan.create', 'fitness.plan.update', 'fitness.kpi.view',
            ],
        ];
        foreach ($roles as $role) {
            foreach ($rolePermissions[$role['name']] ?? [] as $permission) {
                $exists = $db->table('role_permissions')->where(['role_id' => $role['id'], 'permission' => $permission])->countAllResults();
                if ($exists === 0) {
                    $db->table('role_permissions')->insert(['role_id' => $role['id'], 'permission' => $permission, 'created_at' => $now]);
                }
            }
        }
    }

    public function down(): void
    {
        // Fitness schema is forward-only; production data must not be removed by rollback.
    }
}
