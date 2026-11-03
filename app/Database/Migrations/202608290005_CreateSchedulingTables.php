<?php

declare (strict_types=1);
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
final class CreateSchedulingTables extends Migration
{
    public function up(): void
    {
        $this->forge->addField(['id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
    'branch_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true], 'batch_id' => ['type' => 'BIGINT', 'constraint' => 20,
    'unsigned' => true], 'coach_user_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true], 'starts_at' => ['type' => 'DATETIME'],
    'ends_at' => ['type' => 'DATETIME'], 'status' => ['type' => 'VARCHAR', 'constraint' => 20], 'cancellation_reason' => ['type' => 'VARCHAR',
    'constraint' => 255, 'null' => true], 'cancelled_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
    'cancelled_at' => ['type' => 'DATETIME', 'null' => true], 'rescheduled_from_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true,
    'null' => true], 'created_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true], 'created_at' => ['type' => 'DATETIME'],
    'updated_at' => ['type' => 'DATETIME']]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['branch_id', 'starts_at']);
        $this->forge->addKey(['coach_user_id', 'starts_at', 'ends_at']);
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('batch_id', 'batches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('coach_user_id', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('cancelled_by', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('rescheduled_from_id', 'sessions', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('sessions', true);
        $this->forge->addField(['session_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true], 'player_id' => ['type' => 'BIGINT',
    'constraint' => 20, 'unsigned' => true], 'created_at' => ['type' => 'DATETIME']]);
        $this->forge->addKey(['session_id', 'player_id'], true);
        $this->forge->addForeignKey('session_id', 'sessions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('player_id', 'players', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('session_players', true);
        $this->forge->addField(['id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
    'coach_user_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true], 'branch_id' => ['type' => 'BIGINT', 'constraint' => 20,
    'unsigned' => true], 'unavailable_from' => ['type' => 'DATETIME'], 'unavailable_to' => ['type' => 'DATETIME'], 'reason' => ['type' => 'VARCHAR',
    'constraint' => 255, 'null' => true], 'created_at' => ['type' => 'DATETIME']]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['coach_user_id', 'unavailable_from', 'unavailable_to']);
        $this->forge->addForeignKey('coach_user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('coach_unavailability', true);
    }
    public function down(): void
    {
        foreach (['coach_unavailability', 'session_players', 'sessions'] as $table) {
            $this->forge->dropTable($table, true);
        }
    }
}
