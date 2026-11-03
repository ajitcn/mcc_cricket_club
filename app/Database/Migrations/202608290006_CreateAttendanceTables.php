<?php

declare (strict_types=1);
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
final class CreateAttendanceTables extends Migration
{
    public function up(): void
    {
        $this->forge->addField(['id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
    'session_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true], 'player_id' => ['type' => 'BIGINT', 'constraint' => 20,
    'unsigned' => true], 'branch_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true], 'status' => ['type' => 'VARCHAR',
    'constraint' => 20], 'recorded_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true], 'recorded_at' => ['type' => 'DATETIME'],
    'version' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 1], 'created_at' => ['type' => 'DATETIME'],
    'updated_at' => ['type' => 'DATETIME']]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['session_id', 'player_id']);
        $this->forge->addKey(['branch_id', 'session_id']);
        $this->forge->addForeignKey('session_id', 'sessions', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('player_id', 'players', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('recorded_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('attendance', true);
        $this->forge->addField(['id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
    'attendance_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true], 'prior_status' => ['type' => 'VARCHAR', 'constraint' => 20,
    'null' => true], 'new_status' => ['type' => 'VARCHAR', 'constraint' => 20], 'actor_user_id' => ['type' => 'BIGINT', 'constraint' => 20,
    'unsigned' => true], 'reason' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true], 'changed_at' => ['type' => 'DATETIME'],
    'prior_version' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'new_version' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true]]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['attendance_id', 'changed_at']);
        $this->forge->addForeignKey('attendance_id', 'attendance', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('actor_user_id', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('attendance_history', true);
    }
    public function down(): void
    {
        $this->forge->dropTable('attendance_history', true);
        $this->forge->dropTable('attendance', true);
    }
}
