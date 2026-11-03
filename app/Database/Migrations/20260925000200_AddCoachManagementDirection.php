<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddCoachManagementDirection extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('coach_unavailability')) {
            $fields = $this->db->getFieldNames('coach_unavailability');
            $add = [];
            if (! in_array('status', $fields, true)) {
                $add['status'] = ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'APPROVED'];
            }
            if (! in_array('created_by', $fields, true)) {
                $add['created_by'] = ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true];
            }
            if (! in_array('updated_at', $fields, true)) {
                $add['updated_at'] = ['type' => 'DATETIME', 'null' => true];
            }
            if (! in_array('cancelled_at', $fields, true)) {
                $add['cancelled_at'] = ['type' => 'DATETIME', 'null' => true];
            }
            if ($add !== []) {
                $this->forge->addColumn('coach_unavailability', $add);
            }
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'branch_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'coach_user_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'player_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'session_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'rating' => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true],
            'approved' => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'default' => 1],
            'created_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['coach_user_id', 'branch_id', 'session_id']);
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('coach_user_id', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('player_id', 'players', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('session_id', 'sessions', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('coach_feedback', true);
    }

    public function down(): void
    {
        // Forward-only: Coach history and ratings must not be removed by rollback.
    }
}
