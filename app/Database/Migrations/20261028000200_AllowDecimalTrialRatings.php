<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AllowDecimalTrialRatings extends Migration
{
    private const TABLES = [
        'trials',
        'admission_periods',
    ];

    private const FIELDS = [
        'ability_overall',
        'ability_batting',
        'ability_bowling',
        'ability_wicketkeeping',
        'ability_fitness',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! $this->db->tableExists($table)) {
                continue;
            }

            foreach (self::FIELDS as $field) {
                if ($this->db->fieldExists($field, $table)) {
                    $this->forge->modifyColumn($table, [
                        $field => [
                            'type' => 'DECIMAL',
                            'constraint' => '4,2',
                            'unsigned' => true,
                            'null' => true,
                        ],
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Forward-only: decimal ratings preserve historical Trial and Admission values.
    }
}
