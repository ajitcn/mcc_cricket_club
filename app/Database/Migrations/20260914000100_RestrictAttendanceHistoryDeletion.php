<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class RestrictAttendanceHistoryDeletion extends Migration
{
    public function up(): void
    {
        $constraint = $this->db->query(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE "
            . "WHERE TABLE_SCHEMA = DATABASE() "
            . "AND TABLE_NAME = 'attendance_history' "
            . "AND COLUMN_NAME = 'attendance_id' "
            . "AND REFERENCED_TABLE_NAME = 'attendance' LIMIT 1"
        )->getRow('CONSTRAINT_NAME');

        if (is_string($constraint) && $constraint !== '') {
            $this->db->query(
                'ALTER TABLE attendance_history DROP FOREIGN KEY '
                . $this->db->escapeIdentifiers($constraint)
            );
        }

        $this->db->query(
            'ALTER TABLE attendance_history ADD CONSTRAINT attendance_history_attendance_fk '
            . 'FOREIGN KEY (attendance_id) REFERENCES attendance(id) '
            . 'ON DELETE RESTRICT ON UPDATE CASCADE'
        );
    }

    public function down(): void
    {
        // Attendance history retention is forward-only.
    }
}
