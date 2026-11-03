<?php

declare (strict_types=1);
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
final class AddApprovedFollowUpWorkflow extends Migration
{
    public function up(): void
    {
        // Forward-only schema evolution. Existing Follow-Up rows are retained;
        // nullable additions allow legacy rows to remain readable.
        $this->forge->addColumn('lead_follow_ups', ['follow_up_type' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
    'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true], 'outcome' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
    'cancellation_reason' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true], 'completed_at' => ['type' => 'DATETIME', 'null' => true],
    'cancelled_at' => ['type' => 'DATETIME', 'null' => true], 'updated_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
    'updated_at' => ['type' => 'DATETIME', 'null' => true]]);
        $this->forge->addKey(['lead_id', 'status', 'follow_up_at']);
        $this->forge->addForeignKey('updated_by', 'users', 'id', 'SET NULL', 'CASCADE');
    }
    public function down(): void
    {
        // Follow-Up history is retention-sensitive and migrations are forward-only.
    }
}
