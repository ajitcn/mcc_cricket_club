<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateMatchPlayerPerformances extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'match_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'player_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'branch_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'innings_number' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 1],
            'team_side' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'match'],
            'batting_status' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'runs' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'balls_faced' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'fours' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'sixes' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'dismissal_type' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'dismissed_by_player_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'fielder_player_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'batting_position' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'bowling_status' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'legal_balls' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'maidens' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'runs_conceded' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'wickets' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'wides' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'no_balls' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'byes' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'leg_byes' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'correction_reason' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'updated_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['match_id', 'player_id', 'innings_number', 'team_side'], false, true);
        $this->forge->addKey(['branch_id', 'match_id']);
        $this->forge->addForeignKey('match_id', 'matches', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('player_id', 'players', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('updated_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('match_player_performances', true);
    }

    public function down(): void
    {
        // Forward-only: Match performance history must not be removed by rollback.
    }
}
