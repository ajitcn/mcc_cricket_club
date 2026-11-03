<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddAdmissionDocumentRelationship extends Migration
{
    public function up(): void
    {
        if (! $this->db->fieldExists('admission_id', 'documents')) {
            $this->forge->addColumn('documents', [
                'admission_id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'player_id',
                ],
            ]);

            $this->db->query(
                'ALTER TABLE documents ADD INDEX documents_admission_id_index (admission_id)'
            );
            $this->db->query(
                'ALTER TABLE documents ADD CONSTRAINT documents_admission_id_foreign '
                . 'FOREIGN KEY (admission_id) REFERENCES admissions(id) '
                . 'ON DELETE RESTRICT ON UPDATE CASCADE'
            );
        }

        $this->db->query(
            'ALTER TABLE documents MODIFY player_id BIGINT UNSIGNED NULL'
        );
    }

    public function down(): void
    {
        // Document relationships are forward-only to protect uploaded records.
    }
}
