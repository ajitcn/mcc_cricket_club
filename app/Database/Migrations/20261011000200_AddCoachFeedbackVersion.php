<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddCoachFeedbackVersion extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('coach_feedback')) {
            return;
        }

        $fields = $this->db->getFieldNames('coach_feedback');
        if (in_array('version', $fields, true)) {
            return;
        }

        $this->forge->addColumn('coach_feedback', [
            'version' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'default' => 1,
            ],
        ]);
        $this->db->query(
            'CREATE INDEX coach_feedback_version_idx ON coach_feedback (id, version)',
        );
    }

    public function down(): void
    {
        // Forward-only: feedback history must not be removed by rollback.
    }
}
