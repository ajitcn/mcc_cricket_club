<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddHostelInstallmentSchedule extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('admissions')) {
            $admissionFields = $this->db->getFieldNames('admissions');
            if (! in_array('hostel_installment_plan', $admissionFields, true)) {
                $this->forge->addColumn('admissions', [
                    'hostel_installment_plan' => [
                        'type' => 'VARCHAR',
                        'constraint' => 30,
                        'null' => true,
                    ],
                ]);
            }
        }

        if (! $this->db->tableExists('admission_installment_rows')) {
            return;
        }

        $rowFields = $this->db->getFieldNames('admission_installment_rows');
        if (! in_array('schedule_type', $rowFields, true)) {
            $this->forge->addColumn('admission_installment_rows', [
                'schedule_type' => [
                    'type' => 'VARCHAR',
                    'constraint' => 20,
                    'default' => 'coaching',
                ],
            ]);
        }

        $indexes = $this->db->query('SHOW INDEX FROM admission_installment_rows')->getResultArray();
        $hasScheduleIndex = false;
        $legacyIndexNames = [];
        foreach ($indexes as $index) {
            $keyName = (string) ($index['Key_name'] ?? '');
            if ($keyName === 'admission_installment_schedule_uq') {
                $hasScheduleIndex = true;
            } elseif ((int) ($index['Non_unique'] ?? 1) === 0 && $keyName !== 'PRIMARY') {
                $legacyIndexNames[$keyName] = true;
            }
        }

        if (! $hasScheduleIndex) {
            $this->db->query(
                'ALTER TABLE admission_installment_rows ADD UNIQUE KEY admission_installment_schedule_uq '
                . '(admission_id, schedule_type, year_number)'
            );
        }

        foreach (array_keys($legacyIndexNames) as $legacyIndexName) {
            $this->db->query(
                'ALTER TABLE admission_installment_rows DROP INDEX '
                . $this->db->escapeIdentifiers($legacyIndexName)
            );
        }
    }

    public function down(): void
    {
        // Forward-only: saved hostel installment schedules must not be removed by rollback.
    }
}
