<?php

declare (strict_types=1);
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
/** Adds nullable direct-intake identity while preserving all existing rows. */
final class AddDirectAdmissionIdentity extends Migration
{
    public function up(): void
    {
        $fields = ['applicant_full_name' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true, 'after' => 'lead_id'],
    'applicant_email' => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true, 'after' => 'applicant_full_name'],
    'applicant_phone' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true, 'after' => 'applicant_email'],
    'applicant_date_of_birth' => ['type' => 'DATE', 'null' => true, 'after' => 'applicant_phone']];
        $missing = [];
        foreach ($fields as $name => $definition) {
            if (!$this->db->fieldExists($name, 'admissions')) {
                $missing[$name] = $definition;
            }
        }
        if ($missing !== []) {
            $this->forge->addColumn('admissions', $missing);
        }
        // Nullable unique keys allow any number of direct admissions (NULL lead_id).
        $this->db->query('ALTER TABLE admissions MODIFY lead_id BIGINT UNSIGNED NULL');
        $this->db->query('ALTER TABLE players MODIFY lead_id BIGINT UNSIGNED NULL');
    }
    public function down(): void
    {
        // Forward-only: existing data must never be made invalid by rollback.
    }
}
