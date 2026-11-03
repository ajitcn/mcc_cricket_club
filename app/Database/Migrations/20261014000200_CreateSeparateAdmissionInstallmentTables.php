<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateSeparateAdmissionInstallmentTables extends Migration
{
    public function up(): void
    {
        $this->createScheduleTable('admission_coaching_installments');
        $this->createScheduleTable('admission_hostel_installments');
        $this->migrateLegacyRows();
    }

    public function down(): void
    {
        // Forward-only: admission schedule history must not be removed by rollback.
    }

    private function createScheduleTable(string $table): void
    {
        if ($this->db->tableExists($table)) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'admission_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'admission_period_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'null' => true,
            ],
            'year_number' => [
                'type' => 'TINYINT',
                'constraint' => 3,
                'unsigned' => true,
            ],
            'year_label' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true,
            ],
            'installment_number' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'unsigned' => true,
            ],
            'due_date' => [
                'type' => 'DATE',
            ],
            'amount' => [
                'type' => 'DECIMAL',
                'constraint' => '12,2',
                'unsigned' => true,
            ],
            'created_by' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'null' => true,
            ],
            'version' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'default' => 1,
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['admission_id', 'admission_period_id', 'year_number', 'installment_number'], false, true);
        $this->forge->addKey(['admission_period_id', 'due_date']);
        $this->forge->addForeignKey('admission_id', 'admissions', 'id', 'RESTRICT', 'CASCADE');

        if ($this->db->tableExists('admission_periods')) {
            $this->forge->addForeignKey('admission_period_id', 'admission_periods', 'id', 'RESTRICT', 'CASCADE');
        }

        if ($this->db->tableExists('users')) {
            $this->forge->addForeignKey('created_by', 'users', 'id', 'SET NULL', 'CASCADE');
        }

        $this->forge->createTable($table, true);
    }

    private function migrateLegacyRows(): void
    {
        if (! $this->db->tableExists('admission_installment_rows')) {
            return;
        }

        $legacyRows = $this->db->table('admission_installment_rows')
            ->get()
            ->getResultArray();

        foreach ($legacyRows as $legacyRow) {
            $type = (string) ($legacyRow['schedule_type'] ?? 'coaching');
            $table = $type === 'hostel'
                ? 'admission_hostel_installments'
                : 'admission_coaching_installments';
            $admissionId = (int) ($legacyRow['admission_id'] ?? 0);

            if ($admissionId < 1) {
                continue;
            }

            $period = null;
            if ($this->db->tableExists('admission_periods')) {
                $period = $this->db->table('admission_periods')
                    ->where(['admission_id' => $admissionId, 'status' => 'current'])
                    ->orderBy('period_number', 'DESC')
                    ->get()
                    ->getRowArray();
            }
            $periodId = is_array($period) ? (int) $period['id'] : null;
            $now = date('Y-m-d H:i:s');

            foreach (range(1, 4) as $number) {
                $date = trim((string) ($legacyRow['installment_' . $number . '_date'] ?? ''));
                $amount = trim((string) ($legacyRow['installment_' . $number] ?? ''));

                if ($date === '' || $amount === '') {
                    continue;
                }

                $this->db->query(
                    'INSERT IGNORE INTO ' . $table
                    . ' (admission_id, admission_period_id, year_number, year_label, installment_number, due_date, amount, created_by, version, created_at, updated_at)'
                    . ' VALUES (?, ?, ?, ?, ?, ?, ?, NULL, 1, ?, ?)',
                    [
                        $admissionId,
                        $periodId,
                        (int) ($legacyRow['year_number'] ?? 0),
                        'Year ' . (int) ($legacyRow['year_number'] ?? 0),
                        $number,
                        $date,
                        number_format((float) $amount, 2, '.', ''),
                        $now,
                        $now,
                    ],
                );
            }
        }
    }
}
