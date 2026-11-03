<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddAdmissionPersonalCoach extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('admissions')) {
            return;
        }

        $fields = $this->db->getFieldNames('admissions');
        if (! in_array('personal_coach_user_id', $fields, true)) {
            $this->forge->addColumn('admissions', [
                'personal_coach_user_id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                    'null' => true,
                ],
            ]);
        }

        $indexes = $this->db->query('SHOW INDEX FROM admissions')->getResultArray();
        $hasIndex = false;
        foreach ($indexes as $index) {
            if ((string) ($index['Key_name'] ?? '') === 'admissions_personal_coach_idx') {
                $hasIndex = true;
                break;
            }
        }

        if (! $hasIndex) {
            $this->db->query(
                'ALTER TABLE admissions ADD INDEX admissions_personal_coach_idx (personal_coach_user_id)'
            );
        }

        $createTable = $this->db->query('SHOW CREATE TABLE admissions')->getRowArray();
        $createSql = is_array($createTable)
            ? implode(' ', array_map('strval', $createTable))
            : '';
        if (! str_contains($createSql, 'admissions_personal_coach_user_fk')) {
            $this->db->query(
                'ALTER TABLE admissions ADD CONSTRAINT admissions_personal_coach_user_fk '
                . 'FOREIGN KEY (personal_coach_user_id) REFERENCES users(id) '
                . 'ON DELETE SET NULL ON UPDATE CASCADE'
            );
        }
    }

    public function down(): void
    {
        // Forward-only: admission intake data must not be removed by rollback.
    }
}
