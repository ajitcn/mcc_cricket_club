<?php

declare (strict_types=1);
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
final class AddApprovedAdmissionLifecycle extends Migration
{
    public function up(): void
    {
        $fields = ['checklist_identity_complete' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'checklist_complete'],
    'checklist_contact_complete' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'checklist_identity_complete'],
    'checklist_admission_complete' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'checklist_contact_complete'],
    'checklist_branch_confirmed' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'checklist_admission_complete'],
    'checklist_approval_recorded' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'checklist_branch_confirmed'],
    'approved_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true, 'after' => 'converted_player_id'],
    'approved_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'approved_by']];
        $missing = [];
        foreach ($fields as $name => $definition) {
            if (!$this->db->fieldExists($name, 'admissions')) {
                $missing[$name] = $definition;
            }
        }
        if ($missing !== []) {
            $this->forge->addColumn('admissions', $missing);
        }
        if (!$this->db->fieldExists('admission_id', 'players')) {
            $this->forge->addColumn('players', ['admission_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true,
    'after' => 'lead_id']]);
            $this->db->query('ALTER TABLE players ADD UNIQUE KEY players_admission_id_unique (admission_id)');
            $this->db->query('ALTER TABLE players ADD CONSTRAINT players_admission_id_foreign FOREIGN KEY (admission_id) REFERENCES admissions(id) ON
    DELETE RESTRICT ON UPDATE CASCADE');
        }
    }
    public function down(): void
    {
        if ($this->db->fieldExists('admission_id', 'players')) {
            $this->db->query('ALTER TABLE players DROP FOREIGN KEY players_admission_id_foreign');
            $this->db->query('ALTER TABLE players DROP INDEX players_admission_id_unique');
            $this->forge->dropColumn('players', 'admission_id');
        }
        foreach (['checklist_identity_complete', 'checklist_contact_complete', 'checklist_admission_complete', 'checklist_branch_confirmed',
    'checklist_approval_recorded', 'approved_by', 'approved_at'] as $field) {
            if ($this->db->fieldExists($field, 'admissions')) {
                $this->forge->dropColumn('admissions', $field);
            }
        }
    }
}
