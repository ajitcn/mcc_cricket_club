<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddTrialRatingsAndAdmissionRatingSnapshots extends Migration
{
    private const RATING_FIELDS = [
        'ability_overall',
        'ability_batting',
        'ability_bowling',
        'ability_wicketkeeping',
        'ability_fitness',
    ];

    public function up(): void
    {
        $columns = [];

        foreach (self::RATING_FIELDS as $field) {
            $columns[$field] = [
                'type' => 'TINYINT',
                'constraint' => 2,
                'unsigned' => true,
                'null' => true,
            ];
        }

        if ($this->db->tableExists('trials')) {
            foreach ($columns as $field => $definition) {
                if (! $this->db->fieldExists($field, 'trials')) {
                    $this->forge->addColumn('trials', [$field => $definition]);
                }
            }
        }

        if ($this->db->tableExists('admission_periods')) {
            foreach ($columns as $field => $definition) {
                if (! $this->db->fieldExists($field, 'admission_periods')) {
                    $this->forge->addColumn('admission_periods', [$field => $definition]);
                }
            }
        }
    }

    public function down(): void
    {
        // Forward-only: Trial ratings and Admission period snapshots are historical records.
    }
}
