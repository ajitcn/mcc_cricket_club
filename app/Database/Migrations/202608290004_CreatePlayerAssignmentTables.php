<?php

declare (strict_types=1);
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
final class CreatePlayerAssignmentTables extends Migration
{
    public function up(): void
    {
        $this->forge->addField(['id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
    'branch_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true], 'name' => ['type' => 'VARCHAR', 'constraint' => 150],
    'created_at' => ['type' => 'DATETIME'], 'updated_at' => ['type' => 'DATETIME']]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['branch_id', 'name']);
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('batches', true);
        $this->forge->addField(['id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
    'player_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true], 'branch_id' => ['type' => 'BIGINT', 'constraint' => 20,
    'unsigned' => true], 'batch_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true], 'assigned_by' => ['type' => 'BIGINT',
    'constraint' => 20, 'unsigned' => true], 'effective_from' => ['type' => 'DATETIME'], 'effective_to' => ['type' => 'DATETIME', 'null' => true],
    'created_at' => ['type' => 'DATETIME'], 'updated_at' => ['type' => 'DATETIME']]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['player_id', 'effective_from']);
        $this->forge->addKey(['player_id', 'effective_to']);
        $this->forge->addForeignKey('player_id', 'players', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('batch_id', 'batches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('assigned_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('player_batch_assignments', true);
        $this->forge->addField(['id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
    'player_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true], 'branch_id' => ['type' => 'BIGINT', 'constraint' => 20,
    'unsigned' => true], 'coach_user_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true], 'assigned_by' => ['type' => 'BIGINT',
    'constraint' => 20, 'unsigned' => true], 'effective_from' => ['type' => 'DATETIME'], 'effective_to' => ['type' => 'DATETIME', 'null' => true],
    'created_at' => ['type' => 'DATETIME'], 'updated_at' => ['type' => 'DATETIME']]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['player_id', 'effective_from']);
        $this->forge->addKey(['player_id', 'effective_to']);
        $this->forge->addForeignKey('player_id', 'players', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('coach_user_id', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('assigned_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('player_coach_assignments', true);
    }
    public function down(): void
    {
        foreach (['player_coach_assignments', 'player_batch_assignments', 'batches'] as $table) {
            $this->forge->dropTable($table, true);
        }
    }
}
