<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

final class RemoveAdmissionPlanMasters extends Migration
{
    private const PLAN_TABLES = [
        'admission_coaching_plans',
        'admission_fee_plans',
        'admission_hostel_plans',
        'admission_academic_plans',
    ];

    private const PLAN_REFERENCES = [
        'admissions' => [
            'coaching_plan_id' => 'coaching_plan_label',
            'fee_plan_id' => 'fee_plan_label',
            'hostel_plan_id' => 'hostel_plan_label',
            'academic_plan_id' => 'academic_plan_label',
        ],
        'admission_periods' => [
            'coaching_plan_id' => 'coaching_plan_label',
            'fee_plan_id' => 'fee_plan_label',
            'hostel_plan_id' => 'hostel_plan_label',
            'academic_plan_id' => 'academic_plan_label',
        ],
    ];

    public function up(): void
    {
        $this->snapshotHistoricalLabels();
        $this->assertNoUnexpectedForeignKeys();
        $this->removePlanReferences();
        $this->removePlanTables();
    }

    public function down(): void
    {
        // Forward-only: plan master data and ID references are intentionally removed.
    }

    private function snapshotHistoricalLabels(): void
    {
        foreach (self::PLAN_REFERENCES as $referenceTable => $references) {
            if (! $this->db->tableExists($referenceTable)) {
                continue;
            }

            foreach ($references as $idColumn => $labelColumn) {
                if (! $this->db->fieldExists($idColumn, $referenceTable)
                    || ! $this->db->fieldExists($labelColumn, $referenceTable)) {
                    continue;
                }

                $masterTable = $this->masterTableFor($idColumn);
                $masterLabel = $idColumn === 'academic_plan_id' ? 'academic_year' : 'plan_name';
                if (! $this->db->tableExists($masterTable)) {
                    continue;
                }

                $this->db->query(
                    'UPDATE `' . $referenceTable . '` AS reference_row '
                    . 'INNER JOIN `' . $masterTable . '` AS master_row '
                    . 'ON master_row.id = reference_row.`' . $idColumn . '` '
                    . 'SET reference_row.`' . $labelColumn . '` = master_row.`' . $masterLabel . '` '
                    . 'WHERE reference_row.`' . $idColumn . '` IS NOT NULL '
                    . 'AND (reference_row.`' . $labelColumn . '` IS NULL '
                    . 'OR reference_row.`' . $labelColumn . '` = \'\')',
                );
            }
        }
    }

    private function assertNoUnexpectedForeignKeys(): void
    {
        foreach (self::PLAN_TABLES as $table) {
            if (! $this->db->tableExists($table)) {
                continue;
            }

            $references = $this->db->query(
                'SELECT TABLE_NAME, CONSTRAINT_NAME '
                . 'FROM information_schema.KEY_COLUMN_USAGE '
                . 'WHERE TABLE_SCHEMA = DATABASE() '
                . 'AND REFERENCED_TABLE_NAME = ? '
                . 'AND TABLE_NAME <> ? '
                . 'GROUP BY TABLE_NAME, CONSTRAINT_NAME',
                [$table, $table],
            )->getResultArray();

            if ($references !== []) {
                throw new RuntimeException(
                    'Unexpected foreign-key dependency on ' . $table . '.',
                );
            }
        }
    }

    private function removePlanReferences(): void
    {
        foreach (array_keys(self::PLAN_REFERENCES) as $table) {
            if (! $this->db->tableExists($table)) {
                continue;
            }

            foreach (self::PLAN_REFERENCES[$table] as $idColumn => $labelColumn) {
                if ($this->db->fieldExists($idColumn, $table)) {
                    $this->forge->dropColumn($table, $idColumn);
                }
            }
        }
    }

    private function removePlanTables(): void
    {
        foreach (self::PLAN_TABLES as $table) {
            if ($this->db->tableExists($table)) {
                $this->forge->dropTable($table, true);
            }
        }
    }

    private function masterTableFor(string $idColumn): string
    {
        return match ($idColumn) {
            'coaching_plan_id' => 'admission_coaching_plans',
            'fee_plan_id' => 'admission_fee_plans',
            'hostel_plan_id' => 'admission_hostel_plans',
            'academic_plan_id' => 'admission_academic_plans',
            default => throw new RuntimeException('Unknown Admission plan reference.'),
        };
    }
}
