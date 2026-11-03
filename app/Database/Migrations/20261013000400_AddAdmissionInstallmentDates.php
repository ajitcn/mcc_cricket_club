<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddAdmissionInstallmentDates extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('admission_installment_rows')) {
            return;
        }

        $fields = $this->db->getFieldNames('admission_installment_rows');
        $add = [];
        foreach (range(1, 4) as $number) {
            $name = 'installment_' . $number . '_date';
            if (! in_array($name, $fields, true)) {
                $add[$name] = [
                    'type' => 'DATE',
                    'null' => true,
                ];
            }
        }

        if ($add !== []) {
            $this->forge->addColumn('admission_installment_rows', $add);
        }
    }

    public function down(): void
    {
        // Forward-only: persisted admission instalment dates must not be removed by rollback.
    }
}
