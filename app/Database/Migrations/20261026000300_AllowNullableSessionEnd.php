<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AllowNullableSessionEnd extends Migration
{
    public function up(): void
    {
        if (! $this->db->fieldExists('ends_at', 'sessions')) {
            return;
        }

        $this->db->query('ALTER TABLE sessions MODIFY ends_at DATETIME NULL');
    }

    public function down(): void
    {
        // Forward-only migration. Historical end values and the column are preserved.
    }
}
