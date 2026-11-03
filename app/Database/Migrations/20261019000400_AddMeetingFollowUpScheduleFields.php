<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddMeetingFollowUpScheduleFields extends Migration
{
    public function up(): void
    {
        if (! $this->db->fieldExists('start_at', 'admission_test_followups')) {
            $this->forge->addColumn('admission_test_followups', [
                'start_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'after' => 'follow_up_at',
                ],
                'end_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'after' => 'start_at',
                ],
            ]);
            $this->db->query('UPDATE admission_test_followups SET start_at = follow_up_at WHERE start_at IS NULL');
        }
    }

    public function down(): void
    {
        // Forward-only migration; meeting history must remain available.
    }
}
