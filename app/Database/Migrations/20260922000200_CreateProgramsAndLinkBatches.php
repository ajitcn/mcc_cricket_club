<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateProgramsAndLinkBatches extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'name' => [
                'type' => 'VARCHAR',
                'constraint' => 150,
            ],
            'created_by' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
            'updated_at' => [
                'type' => 'DATETIME',
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('name');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('programs', true);

        if (! $this->db->fieldExists('program_id', 'batches')) {
            $this->db->query(
                'ALTER TABLE batches ADD COLUMN program_id BIGINT UNSIGNED NULL AFTER branch_id'
            );
            $this->db->query(
                'ALTER TABLE batches ADD INDEX batches_program_id_index (program_id)'
            );
            $this->db->query(
                'ALTER TABLE batches ADD CONSTRAINT batches_program_id_foreign '
                . 'FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE RESTRICT ON UPDATE CASCADE'
            );
        }
    }

    public function down(): void
    {
        // Forward-only: existing Programs and Batch relationships must be preserved.
    }
}
