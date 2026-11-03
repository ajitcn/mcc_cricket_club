<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class MakeBatchesGlobal extends Migration
{
    public function up(): void
    {
        if ($this->db->fieldExists('is_active', 'batches') === false) {
            $this->db->query(
                'ALTER TABLE batches ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER name'
            );
        }

        if ($this->db->fieldExists('branch_id', 'batches')) {
            $this->db->query(
                'ALTER TABLE batches MODIFY branch_id BIGINT UNSIGNED NULL'
            );
        }
    }

    public function down(): void
    {
        // Forward-only compatibility migration. Existing Batch data is preserved.
    }
}
