<?php

declare (strict_types=1);
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
final class CreateTrialTables extends Migration
{
    public function up(): void
    {
        $this->forge->addField(['id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
    'lead_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true], 'branch_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
    'trial_date' => ['type' => 'DATE'], 'trial_time' => ['type' => 'TIME'], 'location' => ['type' => 'VARCHAR', 'constraint' => 255],
    'coach_user_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true], 'status' => ['type' => 'VARCHAR', 'constraint' => 20],
    'selection_outcome' => ['type' => 'VARCHAR', 'constraint' => 20], 'remarks' => ['type' => 'TEXT', 'null' => true],
    'cancellation_reason' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true], 'rescheduled_from_id' => ['type' => 'BIGINT', 'constraint' => 20,
    'unsigned' => true, 'null' => true], 'created_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
    'created_at' => ['type' => 'DATETIME'], 'updated_at' => ['type' => 'DATETIME']]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['lead_id', 'trial_date', 'trial_time']);
        $this->forge->addKey(['branch_id', 'status', 'trial_date']);
        $this->forge->addForeignKey('lead_id', 'leads', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('coach_user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('rescheduled_from_id', 'trials', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('trials', true);
        $this->forge->addField(['id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
    'trial_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true], 'lead_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
    'actor_user_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true], 'event_type' => ['type' => 'VARCHAR', 'constraint' => 40],
    'previous_date' => ['type' => 'DATE', 'null' => true], 'previous_time' => ['type' => 'TIME', 'null' => true], 'new_date' => ['type' => 'DATE',
    'null' => true], 'new_time' => ['type' => 'TIME', 'null' => true], 'previous_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
    'new_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true], 'selection_outcome' => ['type' => 'VARCHAR', 'constraint' => 20,
    'null' => true], 'reason' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true], 'created_at' => ['type' => 'DATETIME']]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['trial_id', 'created_at']);
        $this->forge->addForeignKey('trial_id', 'trials', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('lead_id', 'leads', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('actor_user_id', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('trial_history', true);
        $now = date('Y-m-d H:i:s');
        foreach (['trial booked', 'trial attended', 'selected', 'admission pending'] as $name) {
            $this->db->query('INSERT INTO lead_statuses (name, is_active, sort_order, created_at, updated_at) SELECT ?, 1, 0, ?, ? WHERE NOT EXISTS
    (SELECT 1 FROM lead_statuses WHERE name = ?)', [$name, $now, $now, $name]);
        }
    }
    public function down(): void
    {
        // Trial migrations are forward-only; rollback is intentionally unavailable.
    }
}
