<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddAdmissionTestStudentLinkExpiry extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('admission_test_student_links')
            || $this->db->fieldExists('expires_at', 'admission_test_student_links')) {
            return;
        }

        $this->forge->addColumn('admission_test_student_links', [
            'expires_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'created_at',
            ],
        ]);
    }

    public function down(): void
    {
        // Forward-only: issued student-link expiry history must remain available.
    }
}
