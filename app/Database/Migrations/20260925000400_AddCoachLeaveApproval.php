<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddCoachLeaveApproval extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('coach_unavailability')) {
            return;
        }

        $fields = $this->db->getFieldNames('coach_unavailability');
        $add = [];

        if (! in_array('status_changed_by', $fields, true)) {
            $add['status_changed_by'] = [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'null' => true,
            ];
        }

        if (! in_array('status_changed_at', $fields, true)) {
            $add['status_changed_at'] = [
                'type' => 'DATETIME',
                'null' => true,
            ];
        }

        if (! in_array('status_reason', $fields, true)) {
            $add['status_reason'] = [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ];
        }

        if (! in_array('cancelled_by', $fields, true)) {
            $add['cancelled_by'] = [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'null' => true,
            ];
        }

        if ($add !== []) {
            $this->forge->addColumn('coach_unavailability', $add);
        }
    }

    public function down(): void
    {
        // Forward-only: leave history and approval metadata must be retained.
    }
}
