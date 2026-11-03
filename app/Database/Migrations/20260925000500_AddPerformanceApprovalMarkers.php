<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddPerformanceApprovalMarkers extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('performance_records')) {
            return;
        }

        $fields = $this->db->getFieldNames('performance_records');
        $add = [];

        if (! in_array('approved', $fields, true)) {
            $add['approved'] = [
                'type' => 'TINYINT',
                'constraint' => 1,
                'unsigned' => true,
                'default' => 0,
            ];
        }

        if (! in_array('approved_by', $fields, true)) {
            $add['approved_by'] = [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'null' => true,
            ];
        }

        if (! in_array('approved_at', $fields, true)) {
            $add['approved_at'] = [
                'type' => 'DATETIME',
                'null' => true,
            ];
        }

        if ($add !== []) {
            $this->forge->addColumn('performance_records', $add);
        }
    }

    public function down(): void
    {
        // Forward-only: approval history and source records must be retained.
    }
}
