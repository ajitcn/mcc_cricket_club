<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

final class ConvertAdmissionNumbersToMccSeries extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('admissions')) {
            return;
        }

        $this->ensureSequenceTable();
        $rows = $this->db->query(
            "SELECT id, admission_number\n"
            . "FROM admissions\n"
            . "ORDER BY created_at IS NULL, created_at, id"
        )->getResultArray();

        if (count($rows) > 999999) {
            throw new RuntimeException('The MCC Admission-number series is exhausted.');
        }

        $requiresConversion = false;
        foreach ($rows as $index => $row) {
            $expected = 'MCC-' . str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT);
            if ((string) ($row['admission_number'] ?? '') !== $expected) {
                $requiresConversion = true;
                break;
            }
        }

        $this->db->transStart();
        if ($requiresConversion && $rows !== []) {
            foreach ($rows as $row) {
                $temporary = 'MCC-TEMP-' . str_pad((string) $row['id'], 20, '0', STR_PAD_LEFT);
                $updated = $this->db->table('admissions')
                    ->where('id', (int) $row['id'])
                    ->update(['admission_number' => $temporary]);
                if (! $updated) {
                    throw new RuntimeException('Unable to stage Admission-number conversion.');
                }
            }

            foreach ($rows as $index => $row) {
                $number = 'MCC-' . str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT);
                $updated = $this->db->table('admissions')
                    ->where('id', (int) $row['id'])
                    ->update(['admission_number' => $number]);
                if (! $updated) {
                    throw new RuntimeException('Unable to complete Admission-number conversion.');
                }
            }
        }

        if ($requiresConversion) {
            $this->setNextNumber(count($rows) + 1);
        } else {
            $this->initializeSequenceIfMissing($rows);
        }
        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            throw new RuntimeException('Admission-number conversion rolled back.');
        }

        $this->ensureUniqueIndex();
    }

    public function down(): void
    {
        // Forward-only: Admission references and the sequence must not be reverted.
    }

    private function ensureSequenceTable(): void
    {
        if ($this->db->tableExists('admission_number_sequences')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'TINYINT',
                'constraint' => 3,
                'unsigned' => true,
            ],
            'next_number' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'default' => 1,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('admission_number_sequences', true);
    }

    private function setNextNumber(int $nextNumber): void
    {
        $row = $this->db->table('admission_number_sequences')->where('id', 1)->get()->getRowArray();
        if (is_array($row)) {
            $this->db->table('admission_number_sequences')
                ->where('id', 1)
                ->update(['next_number' => $nextNumber]);

            return;
        }

        $this->db->table('admission_number_sequences')->insert([
            'id' => 1,
            'next_number' => $nextNumber,
        ]);
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function initializeSequenceIfMissing(array $rows): void
    {
        $sequence = $this->db->table('admission_number_sequences')->where('id', 1)->get()->getRowArray();
        if (is_array($sequence)) {
            return;
        }

        $max = 0;
        foreach ($rows as $row) {
            if (preg_match('/^MCC-(\d{6})$/', (string) ($row['admission_number'] ?? ''), $matches) === 1) {
                $max = max($max, (int) $matches[1]);
            }
        }

        $this->setNextNumber($max + 1);
    }

    private function ensureUniqueIndex(): void
    {
        $indexes = array_map(
            static fn (object $index): string => (string) ($index->name ?? ''),
            $this->db->getIndexData('admissions'),
        );
        if (! in_array('admissions_admission_number_unique', $indexes, true)) {
            $this->db->query(
                'ALTER TABLE admissions ADD UNIQUE INDEX admissions_admission_number_unique (admission_number)'
            );
        }
    }
}
