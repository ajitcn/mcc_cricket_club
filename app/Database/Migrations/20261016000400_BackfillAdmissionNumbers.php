<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use DateTimeImmutable;
use RuntimeException;

final class BackfillAdmissionNumbers extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('admissions')) {
            return;
        }

        $rows = $this->db->query(
            "SELECT id, branch_id, created_at\n"
            . "FROM admissions\n"
            . "WHERE admission_number IS NULL OR admission_number = ''\n"
            . "ORDER BY created_at, id"
        )->getResultArray();

        if ($rows === []) {
            return;
        }

        $existing = $this->db->query(
            "SELECT admission_number\n"
            . "FROM admissions\n"
            . "WHERE admission_number IS NOT NULL AND admission_number <> ''"
        )->getResultArray();
        $used = [];
        foreach ($existing as $row) {
            $number = (string) ($row['admission_number'] ?? '');
            if (isset($used[$number])) {
                throw new RuntimeException('Existing duplicate Admission numbers require review.');
            }
            $used[$number] = true;
        }

        $numbers = [];
        foreach ($rows as $row) {
            $number = $this->deterministicNumber($row);
            if (isset($used[$number]) || isset($numbers[$number])) {
                throw new RuntimeException('Deterministic Admission-number collision detected.');
            }
            $numbers[$number] = (int) $row['id'];
        }

        $this->db->transStart();
        foreach ($numbers as $number => $id) {
            $updated = $this->db->table('admissions')
                ->where('id', $id)
                ->groupStart()
                ->where('admission_number IS NULL', null, false)
                ->orWhere('admission_number', '')
                ->groupEnd()
                ->update(['admission_number' => $number]);
            if (! $updated) {
                throw new RuntimeException('Unable to backfill Admission number.');
            }
        }
        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            throw new RuntimeException('Admission-number backfill rolled back.');
        }
    }

    public function down(): void
    {
        // Forward-only: generated historical references must not be removed.
    }

    /** @param array<string, mixed> $row */
    private function deterministicNumber(array $row): string
    {
        $createdAt = (string) ($row['created_at'] ?? '');
        $timestamp = strtotime($createdAt);
        if ($timestamp === false) {
            $timestamp = 0;
        }

        $stamp = (new DateTimeImmutable('@' . $timestamp))->setTimezone(new \DateTimeZone('UTC'))
            ->format('YmdHis');
        $stableInput = (string) ($row['id'] ?? '') . '|' . $createdAt . '|' . (string) ($row['branch_id'] ?? '');
        $suffix = strtoupper(substr(hash('sha256', $stableInput), 0, 8));

        return 'ADM-' . $stamp . '-' . $suffix;
    }
}
