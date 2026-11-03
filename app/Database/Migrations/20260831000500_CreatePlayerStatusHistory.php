<?php

declare (strict_types=1);
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
final class CreatePlayerStatusHistory extends Migration
{
    public function up(): void
    {
        $this->forge->addField(['id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true], 'player_id' => ['type' => 'BIGINT',
    'unsigned' => true], 'previous_status' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true], 'new_status' => ['type' => 'VARCHAR',
    'constraint' => 40], 'reason' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true], 'actor_user_id' => ['type' => 'BIGINT',
    'unsigned' => true], 'created_at' => ['type' => 'DATETIME']]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['player_id', 'created_at']);
        $this->forge->addForeignKey('player_id', 'players', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('actor_user_id', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('player_status_history', true);
    }
    public function down(): void
    {
        // Permanent project rules prohibit destructive rollback operations.
    }
}
