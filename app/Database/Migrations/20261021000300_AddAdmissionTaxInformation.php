<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddAdmissionTaxInformation extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('admissions')) {
            return;
        }

        $fields = $this->db->getFieldNames('admissions');
        $add = [];

        foreach (['total_taxable_fee', 'calculated_tax_amount', 'total_including_tax'] as $field) {
            if (! in_array($field, $fields, true)) {
                $add[$field] = [
                    'type' => 'DECIMAL',
                    'constraint' => '12,2',
                    'unsigned' => true,
                    'null' => true,
                ];
            }
        }

        if (! in_array('tax_rate', $fields, true)) {
            $add['tax_rate'] = [
                'type' => 'DECIMAL',
                'constraint' => '5,2',
                'unsigned' => true,
                'null' => true,
            ];
        }

        if ($add !== []) {
            $this->forge->addColumn('admissions', $add);
        }
    }

    public function down(): void
    {
        // Forward-only: Admission tax history must not be removed by rollback.
    }
}
