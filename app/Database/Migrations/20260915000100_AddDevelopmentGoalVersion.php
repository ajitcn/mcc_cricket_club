<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddDevelopmentGoalVersion extends Migration
{
    public function up(): void
    {
        if (! $this->db->fieldExists('version', 'development_goals')) {
            $this->forge->addColumn('development_goals', [
                'version' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'default' => 1,
                    'after' => 'progress_value',
                ],
            ]);
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('version', 'development_goals')) {
            $this->forge->dropColumn('development_goals', 'version');
        }
    }
}
