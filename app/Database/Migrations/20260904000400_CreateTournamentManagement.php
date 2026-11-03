<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateTournamentManagement extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'branch_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'tournament_type' => ['type' => 'VARCHAR', 'constraint' => 30],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'planned'],
            'starts_on' => ['type' => 'DATE'],
            'ends_on' => ['type' => 'DATE'],
            'ground_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'description' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['branch_id', 'starts_on']);
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('ground_id', 'grounds', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('tournaments', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'tournament_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'participant_type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'team_name' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'player_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['tournament_id', 'participant_type']);
        $this->forge->addForeignKey('tournament_id', 'tournaments', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('player_id', 'players', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('tournament_participants', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'tournament_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'fixture_number' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'home_team' => ['type' => 'VARCHAR', 'constraint' => 150],
            'away_team' => ['type' => 'VARCHAR', 'constraint' => 150],
            'match_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'ground_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'scheduled_at' => ['type' => 'DATETIME', 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'planned'],
            'home_points' => ['type' => 'DECIMAL', 'constraint' => '8,2', 'null' => true],
            'away_points' => ['type' => 'DECIMAL', 'constraint' => '8,2', 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['tournament_id', 'fixture_number']);
        $this->forge->addKey(['tournament_id', 'scheduled_at']);
        $this->forge->addForeignKey('tournament_id', 'tournaments', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('match_id', 'matches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('ground_id', 'grounds', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('tournament_fixtures', true);

        $permissions = [
            'Super Admin' => ['tournament.view', 'tournament.create', 'tournament.update'],
            'Branch Admin' => ['tournament.view', 'tournament.create', 'tournament.update'],
            'Coach' => ['tournament.view', 'tournament.create', 'tournament.update'],
        ];
        foreach ($this->db->table('roles')->select('id, name')->get()->getResultArray() as $role) {
            foreach ($permissions[$role['name']] ?? [] as $permission) {
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
        // Tournament schema is forward-only; production history must not be removed.
    }
}
