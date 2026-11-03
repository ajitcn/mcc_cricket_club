<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddAdmissionNumberUniqueness extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('admissions')) {
            return;
        }

        $fields = $this->db->getFieldNames('admissions');
        if (! in_array('admission_number', $fields, true)) {
            return;
        }

        $indexes = array_map(
            static fn (object $index): string => (string) ($index->name ?? ''),
            $this->db->getIndexData('admissions'),
        );
        if (in_array('admissions_admission_number_unique', $indexes, true)) {
            return;
        }

        $missing = $this->db->query(
            "SELECT COUNT(*) AS total\n"
            . "FROM admissions\n"
            . "WHERE admission_number IS NULL OR admission_number = ''"
        )->getRowArray();
        $missingCount = (int) ($missing['total'] ?? 0);
        if ($missingCount > 0) {
            log_message(
                'warning',
                'Admission-number migration preserved ' . $missingCount
                . ' existing rows without references; no synthetic references were generated.'
            );
        }

        $duplicate = $this->db->query(
            "SELECT admission_number\n"
            . "FROM admissions\n"
            . "WHERE admission_number IS NOT NULL\n"
            . "  AND admission_number <> ''\n"
            . "GROUP BY admission_number\n"
            . "HAVING COUNT(*) > 1\n"
            . "LIMIT 1"
        )->getRowArray();
        if (is_array($duplicate)) {
            log_message(
                'warning',
                'Admission-number uniqueness index was not added because existing duplicate references require review.'
            );

            return;
        }

        $this->db->query(
            'ALTER TABLE admissions ADD UNIQUE INDEX admissions_admission_number_unique (admission_number)'
        );
    }

    public function down(): void
    {
        // Forward-only: admission references must not lose their integrity constraint.
    }
}
