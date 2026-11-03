<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL. Draft awards only. */
final class CreateAchievementAwardDrafts extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'achievement_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'branch_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'recipient_type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'player_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'coach_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'draft'],
            'nomination_note' => ['type' => 'VARCHAR', 'constraint' => 2000, 'null' => true],
            'created_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'updated_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'correction_reason' => ['type' => 'VARCHAR', 'constraint' => 1000, 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['branch_id', 'status']);
        $this->forge->addKey(['achievement_id', 'status']);
        $this->forge->addKey(['player_id', 'status']);
        $this->forge->addKey(['coach_id', 'status']);
        $this->forge->addUniqueKey(['achievement_id', 'recipient_type', 'player_id', 'coach_id']);
        $this->forge->addForeignKey('achievement_id', 'achievements', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('player_id', 'players', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('coach_id', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('updated_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('achievement_awards', true);
        $this->db->query(
            "ALTER TABLE achievement_awards ADD CONSTRAINT chk_achievement_award_recipient "
            . "CHECK ((recipient_type = 'player' AND player_id IS NOT NULL AND coach_id IS NULL) "
            . "OR (recipient_type = 'coach' AND player_id IS NULL AND coach_id IS NOT NULL))",
        );

        foreach ($this->db->table('roles')->select('id, name')->get()->getResultArray() as $role) {
            if (! in_array($role['name'], ['Super Admin', 'Branch Admin'], true)) {
                continue;
            }
            if ($this->db->table('role_permissions')->where(['role_id' => $role['id'], 'permission' => 'achievement.update'])->countAllResults() === 0) {
                $this->db->table('role_permissions')->insert([
                    'role_id' => $role['id'],
                    'permission' => 'achievement.update',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Forward-only: draft award history must not be deleted by rollback.
    }
}
