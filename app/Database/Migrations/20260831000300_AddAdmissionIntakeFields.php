<?php

declare (strict_types=1);
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
final class AddAdmissionIntakeFields extends Migration
{
    public function up(): void
    {
        $fields = ['gender' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true], 'age_group' => ['type' => 'VARCHAR', 'constraint' => 40,
    'null' => true], 'batch_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true], 'joining_date' => ['type' => 'DATE',
    'null' => true], 'blood_group' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true], 'medical_conditions' => ['type' => 'TEXT',
    'null' => true], 'jersey_size' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true], 'playing_role' => ['type' => 'VARCHAR',
    'constraint' => 40, 'null' => true], 'batting_style' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
    'bowling_style' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true], 'strong_hand' => ['type' => 'VARCHAR', 'constraint' => 20,
    'null' => true], 'father_name' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true], 'mother_name' => ['type' => 'VARCHAR',
    'constraint' => 150, 'null' => true], 'parent_mobile' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
    'parent_email' => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true], 'emergency_contact' => ['type' => 'VARCHAR', 'constraint' => 255,
    'null' => true], 'trial_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true]];
        $missing = [];
        foreach ($fields as $name => $definition) {
            if (!$this->db->fieldExists($name, 'admissions')) {
                $missing[$name] = $definition;
            }
        }
        if ($missing !== []) {
            $this->forge->addColumn('admissions', $missing);
        }
        if ($this->db->tableExists('batches') && ! $this->indexExists('admissions_batch_id_index')) {
            $this->db->query('ALTER TABLE admissions ADD INDEX admissions_batch_id_index (batch_id)');
        }
        if ($this->db->tableExists('trials') && ! $this->indexExists('admissions_trial_id_index')) {
            $this->db->query('ALTER TABLE admissions ADD INDEX admissions_trial_id_index (trial_id)');
        }
    }
    public function down(): void
    {
    }

    private function indexExists(string $indexName): bool
    {
        $escapedName = $this->db->escape($indexName);

        return $this->db->query(
            'SHOW INDEX FROM admissions WHERE Key_name = ' . $escapedName
        )->getNumRows() > 0;
    }
}
