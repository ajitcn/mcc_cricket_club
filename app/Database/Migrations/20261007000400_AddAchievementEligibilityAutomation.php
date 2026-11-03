<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** USER-PROVIDED DIRECTION - NOT CLIENT APPROVAL. Draft-only eligibility. */
final class AddAchievementEligibilityAutomation extends Migration
{
    public function up(): void
    {
        $this->createEvaluationTable();
        $this->addAwardMetadata();
        $this->addPermissions();
    }

    public function down(): void
    {
        // Forward-only: evaluation history and generated drafts are retained.
    }

    private function createEvaluationTable(): void
    {
        if ($this->db->tableExists('achievement_eligibility_evaluations')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'achievement_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'branch_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'rule_version' => ['type' => 'VARCHAR', 'constraint' => 50],
            'source_event_type' => ['type' => 'VARCHAR', 'constraint' => 50],
            'source_event_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20],
            'attempt_number' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 1],
            'retry_of_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'candidate_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'candidate_player_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'candidate_coach_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'award_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'failure_reason' => ['type' => 'VARCHAR', 'constraint' => 1000, 'null' => true],
            'evaluated_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'evaluated_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['branch_id', 'status']);
        $this->forge->addKey(['achievement_id', 'source_event_type', 'source_event_id']);
        $this->forge->addKey('retry_of_id');
        $this->forge->addForeignKey('achievement_id', 'achievements', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('retry_of_id', 'achievement_eligibility_evaluations', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('candidate_player_id', 'players', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('candidate_coach_id', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('award_id', 'achievement_awards', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('evaluated_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('achievement_eligibility_evaluations', true);
    }

    private function addAwardMetadata(): void
    {
        if (! $this->db->tableExists('achievement_awards')) {
            return;
        }

        $fields = $this->db->getFieldNames('achievement_awards');
        $columns = [];
        if (! in_array('eligibility_evaluation_id', $fields, true)) {
            $columns['eligibility_evaluation_id'] = [
                'type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true,
            ];
        }
        if (! in_array('eligibility_rule_version', $fields, true)) {
            $columns['eligibility_rule_version'] = [
                'type' => 'VARCHAR', 'constraint' => 50, 'null' => true,
            ];
        }
        if (! in_array('eligibility_source_event', $fields, true)) {
            $columns['eligibility_source_event'] = [
                'type' => 'VARCHAR', 'constraint' => 100, 'null' => true,
            ];
        }
        if (! in_array('eligibility_evaluated_at', $fields, true)) {
            $columns['eligibility_evaluated_at'] = [
                'type' => 'DATETIME', 'null' => true,
            ];
        }

        if ($columns !== []) {
            $this->forge->addColumn('achievement_awards', $columns);
        }

        if (! $this->foreignKeyExists('achievement_awards', 'eligibility_evaluation_id')) {
            $this->forge->addKey('eligibility_evaluation_id');
            $this->forge->addForeignKey(
                'eligibility_evaluation_id',
                'achievement_eligibility_evaluations',
                'id',
                'RESTRICT',
                'CASCADE',
            );
            $this->forge->processIndexes('achievement_awards');
        }
    }

    private function addPermissions(): void
    {
        foreach (['Super Admin', 'Branch Admin'] as $roleName) {
            $role = $this->db->table('roles')->where('name', $roleName)->get()->getRowArray();
            if (! is_array($role)) {
                continue;
            }

            if ($this->db->table('role_permissions')->where([
                'role_id' => $role['id'],
                'permission' => 'achievement.eligibility.evaluate',
            ])->countAllResults() === 0) {
                $this->db->table('role_permissions')->insert([
                    'role_id' => $role['id'],
                    'permission' => 'achievement.eligibility.evaluate',
                    'created_at' => gmdate('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    private function foreignKeyExists(string $table, string $column): bool
    {
        return $this->db->query(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE '
            . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? '
            . 'AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1',
            [$this->db->getDatabase(), $table, $column],
        )->getRowArray() !== null;
    }
}
