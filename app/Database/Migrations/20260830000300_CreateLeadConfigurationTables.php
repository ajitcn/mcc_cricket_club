<?php

declare (strict_types=1);
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
final class CreateLeadConfigurationTables extends Migration
{
    public function up(): void
    {
        $this->forge->addField(['id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
    'name' => ['type' => 'VARCHAR', 'constraint' => 100], 'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
    'sort_order' => ['type' => 'INT', 'constraint' => 11, 'default' => 0], 'created_at' => ['type' => 'DATETIME'], 'updated_at' => ['type' => 'DATETIME']]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('name', 'uq_lead_sources_name');
        $this->forge->createTable('lead_sources', true);
        $this->forge->addField(['id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
    'name' => ['type' => 'VARCHAR', 'constraint' => 40], 'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
    'sort_order' => ['type' => 'INT', 'constraint' => 11, 'default' => 0], 'created_at' => ['type' => 'DATETIME'], 'updated_at' => ['type' => 'DATETIME']]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('name', 'uq_lead_statuses_name');
        $this->forge->createTable('lead_statuses', true);
        $now = date('Y-m-d H:i:s');
        $this->db->query(
            "INSERT INTO lead_sources (name, is_active, sort_order, created_at, updated_at)
             SELECT DISTINCT l.source, 1, 0, ?, ?
             FROM leads l
             WHERE l.source IS NOT NULL
               AND l.source <> ''
               AND NOT EXISTS (
                   SELECT 1 FROM lead_sources existing_source
                   WHERE existing_source.name = l.source
               )",
            [$now, $now]
        );
        $this->db->query(
            "INSERT INTO lead_statuses (name, is_active, sort_order, created_at, updated_at)
             SELECT DISTINCT l.status, 1, 0, ?, ?
             FROM leads l
             WHERE l.status IS NOT NULL
               AND l.status <> ''
               AND NOT EXISTS (
                   SELECT 1 FROM lead_statuses existing_status
                   WHERE existing_status.name = l.status
               )",
            [$now, $now]
        );
    }
    public function down(): void
    {
        $this->forge->dropTable('lead_statuses', true);
        $this->forge->dropTable('lead_sources', true);
    }
}
