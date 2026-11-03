<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class ReconcileCoachAttendanceScope extends Migration
{
    public function up(): void
    {
        $this->forge->modifyColumn('coach_profiles', [
            'experience_years' => [
                'type' => 'VARCHAR',
                'constraint' => 150,
                'null' => true,
            ],
        ]);

        $this->db->table('attendance')
            ->where('status', 'EXCUSED')
            ->update(['status' => 'LEAVE']);

        $this->db->table('attendance_history')
            ->where('prior_status', 'EXCUSED')
            ->update(['prior_status' => 'LEAVE']);

        $this->db->table('attendance_history')
            ->where('new_status', 'EXCUSED')
            ->update(['new_status' => 'LEAVE']);
    }

    public function down(): void
    {
        // Historical status mapping is forward-only and must not rewrite history.
    }
}
