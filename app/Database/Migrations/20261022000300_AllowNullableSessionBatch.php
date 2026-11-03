<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AllowNullableSessionBatch extends Migration
{
    public function up(): void
    {
        foreach ($this->db->getFieldData('sessions') as $field) {
            if ($field->name !== 'batch_id' || strtoupper((string) $field->nullable) === 'YES') {
                continue;
            }

            $this->forge->modifyColumn('sessions', [
                'batch_id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                    'null' => true,
                ],
            ]);
            break;
        }
    }

    public function down(): void
    {
        // Forward-only development/test migration. Existing NULL values must not be rewritten.
    }
}
