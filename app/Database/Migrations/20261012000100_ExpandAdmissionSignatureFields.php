<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class ExpandAdmissionSignatureFields extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('admissions')) {
            return;
        }

        $fields = $this->db->getFieldNames('admissions');
        $changes = [];

        foreach (['student_signature', 'parent_signature', 'authorized_signature'] as $field) {
            if (in_array($field, $fields, true)) {
                $changes[$field] = [
                    'type' => 'TEXT',
                    'null' => true,
                ];
            }
        }

        if ($changes !== []) {
            $this->forge->modifyColumn('admissions', $changes);
        }
    }

    public function down(): void
    {
        // Forward-only: persisted admission signatures remain stored.
    }
}
