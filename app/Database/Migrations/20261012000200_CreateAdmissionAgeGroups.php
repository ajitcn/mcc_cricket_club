<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateAdmissionAgeGroups extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('admission_age_groups')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
                'name' => ['type' => 'VARCHAR', 'constraint' => 100],
                'branch_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
                'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'display_order' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'created_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
                'created_at' => ['type' => 'DATETIME'],
                'updated_at' => ['type' => 'DATETIME'],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['branch_id', 'is_active', 'display_order']);
            $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
            $this->forge->createTable('admission_age_groups', true);
        }

        if (! $this->db->fieldExists('age_group_id', 'admissions')) {
            $this->forge->addColumn('admissions', [
                'age_group_id' => [
                    'type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true, 'after' => 'age_group',
                ],
            ]);
        }
    }

    public function down(): void
    {
        // Forward-only: preserve Age Group history and Admission references.
    }
}
