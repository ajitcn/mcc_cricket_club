<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AllowNullableInstallmentValues extends Migration
{
    private const TABLES = [
        'admission_coaching_installments',
        'admission_hostel_installments',
        'admission_test_coaching_installments',
        'admission_test_hostel_installments',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! $this->db->tableExists($table)) {
                continue;
            }

            $this->forge->modifyColumn($table, [
                'due_date' => [
                    'type' => 'DATE',
                    'null' => true,
                ],
                'amount' => [
                    'type' => 'DECIMAL',
                    'constraint' => '12,2',
                    'unsigned' => true,
                    'null' => true,
                ],
            ]);
        }
    }

    public function down(): void
    {
        // Forward-only: existing partial schedules must not be made invalid.
    }
}
