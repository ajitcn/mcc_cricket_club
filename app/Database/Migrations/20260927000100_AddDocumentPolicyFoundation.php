<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddDocumentPolicyFoundation extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('documents')) {
            return;
        }

        $fields = $this->db->getFieldNames('documents');
        $add = [];

        if (! in_array('verification_status', $fields, true)) {
            $add['verification_status'] = [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'PENDING',
            ];
        }

        if (! in_array('verified_by', $fields, true)) {
            $add['verified_by'] = [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'null' => true,
            ];
        }

        if (! in_array('verified_at', $fields, true)) {
            $add['verified_at'] = [
                'type' => 'DATETIME',
                'null' => true,
            ];
        }

        if (! in_array('verification_reason', $fields, true)) {
            $add['verification_reason'] = [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ];
        }

        if (! in_array('scanner_status', $fields, true)) {
            $add['scanner_status'] = [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'QUARANTINED',
            ];
        }

        if (! in_array('scanner_checked_at', $fields, true)) {
            $add['scanner_checked_at'] = [
                'type' => 'DATETIME',
                'null' => true,
            ];
        }

        if (! in_array('scanner_reason', $fields, true)) {
            $add['scanner_reason'] = [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ];
        }

        if (! in_array('version_number', $fields, true)) {
            $add['version_number'] = [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'default' => 1,
            ];
        }

        if (! in_array('previous_version_id', $fields, true)) {
            $add['previous_version_id'] = [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'null' => true,
            ];
        }

        if ($add !== []) {
            $this->forge->addColumn('documents', $add);
        }

        $this->db->query(
            'ALTER TABLE documents ADD INDEX documents_policy_status_index '
            . '(status, verification_status, scanner_status, expires_on)'
        );
        $this->db->query(
            'ALTER TABLE documents ADD INDEX documents_version_index '
            . '(previous_version_id, version_number)'
        );
    }

    public function down(): void
    {
        // Forward-only: verification, scan, and version history must be retained.
    }
}
