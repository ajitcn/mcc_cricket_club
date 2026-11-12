<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreatePortalSupportRequests extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('portal_support_requests')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'player_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'branch_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'account_type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'category' => ['type' => 'VARCHAR', 'constraint' => 40],
            'subject' => ['type' => 'VARCHAR', 'constraint' => 180],
            'message' => ['type' => 'TEXT'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'open'],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['player_id', 'created_at']);
        $this->forge->addKey(['branch_id', 'status']);
        $this->forge->createTable('portal_support_requests', true);
    }

    public function down(): void
    {
        // Support history is forward-only.
    }
}
