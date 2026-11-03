<?php

declare (strict_types=1);
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
final class AddPlayerIdentityConstraint extends Migration
{
    public function up(): void
    {
        if (!$this->db->fieldExists('profile_revision', 'players')) {
            $this->forge->addColumn('players', ['profile_revision' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 1,
    'after' => 'updated_at']]);
        }
        if (!$this->indexExists()) {
            $this->db->query('ALTER TABLE players ADD UNIQUE KEY players_person_id_unique (person_id)');
        }
    }
    public function down(): void
    {
        if ($this->indexExists()) {
            $this->db->query('ALTER TABLE players DROP INDEX players_person_id_unique');
        }
        if ($this->db->fieldExists('profile_revision', 'players')) {
            $this->forge->dropColumn('players', 'profile_revision');
        }
    }
    private function indexExists(): bool
    {
        return $this->db->query("SHOW INDEX FROM players WHERE Key_name = 'players_person_id_unique'")->getNumRows() > 0;
    }
}
