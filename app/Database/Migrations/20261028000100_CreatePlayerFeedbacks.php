<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreatePlayerFeedbacks extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('player_feedbacks')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'player_id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                ],
                'coach_user_id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                ],
                'branch_id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                ],
                'feedback_year' => [
                    'type' => 'SMALLINT',
                    'constraint' => 4,
                    'unsigned' => true,
                ],
                'feedback_quarter' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'unsigned' => true,
                ],
                'student_parent_feedback' => ['type' => 'TEXT'],
                'coach_feedback' => ['type' => 'TEXT'],
                'next_focus_action' => ['type' => 'TEXT'],
                'version' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'default' => 1,
                ],
                'created_at' => ['type' => 'DATETIME'],
                'updated_at' => ['type' => 'DATETIME'],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['player_id', 'feedback_year', 'feedback_quarter']);
            $this->forge->addKey(['branch_id', 'feedback_year', 'feedback_quarter']);
            $this->forge->addKey(['coach_user_id', 'branch_id']);
            $this->forge->addForeignKey('player_id', 'players', 'id', 'RESTRICT', 'CASCADE');
            $this->forge->addForeignKey('coach_user_id', 'users', 'id', 'RESTRICT', 'CASCADE');
            $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
            $this->forge->createTable('player_feedbacks', true);
        }

        if (! $this->db->tableExists('player_feedback_history')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'feedback_id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                ],
                'version' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                ],
                'snapshot' => ['type' => 'LONGTEXT'],
                'correction_reason' => ['type' => 'VARCHAR', 'constraint' => 255],
                'created_by' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                ],
                'created_at' => ['type' => 'DATETIME'],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['feedback_id', 'version']);
            $this->forge->addForeignKey('feedback_id', 'player_feedbacks', 'id', 'RESTRICT', 'RESTRICT');
            $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'CASCADE');
            $this->forge->createTable('player_feedback_history', true);
        }

        foreach ($this->db->table('roles')->select('id, name')->get()->getResultArray() as $role) {
            if (! in_array($role['name'], ['Super Admin', 'Branch Admin', 'Coach'], true)) {
                continue;
            }

            foreach (['player.feedback.view', 'player.feedback.manage'] as $permission) {
                if ($this->db->table('role_permissions')->where([
                    'role_id' => $role['id'],
                    'permission' => $permission,
                ])->countAllResults() === 0) {
                    $this->db->table('role_permissions')->insert([
                        'role_id' => $role['id'],
                        'permission' => $permission,
                        'created_at' => gmdate('Y-m-d H:i:s'),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Feedback storage is forward-only to preserve historical records.
    }
}
