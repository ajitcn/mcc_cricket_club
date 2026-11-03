<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddAdmissionRenewalSelectionSnapshot extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('admission_periods')
            || $this->db->fieldExists('renewal_values', 'admission_periods')) {
            return;
        }

        $this->forge->addColumn('admission_periods', [
            'renewal_values' => [
                'type' => 'TEXT',
                'null' => true,
                'comment' => 'Immutable JSON snapshot of the renewed period selections.',
            ],
        ]);
    }

    public function down(): void
    {
        // Forward-only: renewal history is an immutable audit record.
    }
}
