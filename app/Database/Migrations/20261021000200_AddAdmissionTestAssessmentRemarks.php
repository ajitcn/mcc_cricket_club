<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddAdmissionTestAssessmentRemarks extends Migration
{
    public function up(): void
    {
        if ($this->db->fieldExists('assessment_remarks', 'admission_tests')) {
            return;
        }

        $this->forge->addColumn('admission_tests', [
            'assessment_remarks' => [
                'type' => 'TEXT',
                'null' => true,
            ],
        ]);
    }

    public function down(): void
    {
        // Forward-only migration; existing assessment remarks are retained.
    }
}
