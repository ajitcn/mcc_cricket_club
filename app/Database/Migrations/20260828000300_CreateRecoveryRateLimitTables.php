<?php

declare (strict_types=1);
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
final class CreateRecoveryRateLimitTables extends Migration
{
    public function up(): void
    {
        $this->forge->addField(['scope' => ['type' => 'VARCHAR', 'constraint' => 10], 'key_hash' => ['type' => 'CHAR', 'constraint' => 64],
    'created_at' => ['type' => 'DATETIME'], 'updated_at' => ['type' => 'DATETIME']]);
        $this->forge->addKey(['scope', 'key_hash'], true);
        $this->forge->createTable('recovery_rate_limit_keys', true);
        $this->forge->addField(['id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
    'scope' => ['type' => 'VARCHAR', 'constraint' => 10], 'key_hash' => ['type' => 'CHAR', 'constraint' => 64], 'requested_at' => ['type' => 'DATETIME']]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['scope', 'key_hash', 'requested_at']);
        $this->forge->createTable('recovery_rate_limit_events', true);
    }
    public function down(): void
    {
        $this->forge->dropTable('recovery_rate_limit_events', true);
        $this->forge->dropTable('recovery_rate_limit_keys', true);
    }
}
