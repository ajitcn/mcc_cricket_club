<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddCrmLeadCreator extends Migration
{
    public function up(): void
    {
        if ($this->db->fieldExists('created_by_user_id', 'leads')) {
            return;
        }

        $this->forge->addColumn('leads', [
            'created_by_user_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'null' => true,
                'after' => 'branch_id',
            ],
        ]);

        $this->db->query(
            'UPDATE leads SET created_by_user_id = owner_user_id WHERE created_by_user_id IS NULL'
        );
        $this->db->query(
            'ALTER TABLE leads ADD CONSTRAINT fk_leads_created_by_user '
            . 'FOREIGN KEY (created_by_user_id) REFERENCES users(id) '
            . 'ON DELETE SET NULL ON UPDATE CASCADE'
        );
        $this->db->query('CREATE INDEX idx_leads_created_by_user ON leads (created_by_user_id)');
    }

    public function down(): void
    {
        // Forward-only: creator visibility data must not be removed by rollback.
    }
}
