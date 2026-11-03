<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AllowNullableTrialLocation extends Migration
{
    public function up(): void
    {
        $this->forge->modifyColumn('trials', [
            'location' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
        ]);
    }

    public function down(): void
    {
        // Trial schema changes are forward-only.
    }
}
