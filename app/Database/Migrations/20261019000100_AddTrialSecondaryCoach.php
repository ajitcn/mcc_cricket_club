<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddTrialSecondaryCoach extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('trials')) {
            return;
        }

        if (! $this->db->fieldExists('secondary_coach_user_id', 'trials')) {
            $this->db->query(
                'ALTER TABLE trials ADD COLUMN secondary_coach_user_id BIGINT UNSIGNED NULL AFTER coach_user_id'
            );
        }

        $indexes = $this->db->query('SHOW INDEX FROM trials')->getResultArray();
        $hasIndex = false;
        foreach ($indexes as $index) {
            if ((string) ($index['Key_name'] ?? '') === 'trials_secondary_coach_idx') {
                $hasIndex = true;
                break;
            }
        }

        if (! $hasIndex) {
            $this->db->query(
                'ALTER TABLE trials ADD INDEX trials_secondary_coach_idx (secondary_coach_user_id)'
            );
        }

        $createTable = $this->db->query('SHOW CREATE TABLE trials')->getRowArray();
        $createSql = is_array($createTable)
            ? implode(' ', array_map('strval', $createTable))
            : '';

        if (! str_contains($createSql, 'trials_secondary_coach_user_fk')) {
            $this->db->query(
                'ALTER TABLE trials ADD CONSTRAINT trials_secondary_coach_user_fk '
                . 'FOREIGN KEY (secondary_coach_user_id) REFERENCES users(id) '
                . 'ON DELETE SET NULL ON UPDATE CASCADE'
            );
        }
    }

    public function down(): void
    {
        // Forward-only: Trial assignment history must remain readable.
    }
}
