<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateEquipmentAssignments extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('equipment_assignments')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
                'branch_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
                'coach_user_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
                'assigned_date' => ['type' => 'DATE'],
                'returned_date' => ['type' => 'DATE', 'null' => true],
                'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'assigned'],
                'notes' => ['type' => 'TEXT', 'null' => true],
                'version' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 1],
                'created_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
                'created_at' => ['type' => 'DATETIME'],
                'updated_at' => ['type' => 'DATETIME'],
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['branch_id', 'status']);
            $this->forge->addKey('coach_user_id');
            $this->forge->createTable('equipment_assignments', true);
        }

        if (! $this->db->tableExists('equipment_assignment_items')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
                'assignment_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
                'equipment_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
                'quantity_assigned' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
                'quantity_returned' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
                'created_at' => ['type' => 'DATETIME'],
                'updated_at' => ['type' => 'DATETIME'],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('assignment_id');
            $this->forge->addKey('equipment_id');
            $this->forge->createTable('equipment_assignment_items', true);
        }
    }

    public function down(): void
    {
        // Assignment history is forward-only.
    }
}
