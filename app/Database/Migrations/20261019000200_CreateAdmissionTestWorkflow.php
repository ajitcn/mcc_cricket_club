<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateAdmissionTestWorkflow extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('admission_tests')) {
            return;
        }

        $this->forge->addColumn('admissions', [
            'admission_test_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'null' => true,
                'after' => 'trial_id',
            ],
            'father_profession' => [
                'type' => 'VARCHAR',
                'constraint' => 150,
                'null' => true,
                'after' => 'father_name',
            ],
            'residence' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'after' => 'correspondence_address',
            ],
            'secondary_contact_number' => [
                'type' => 'VARCHAR',
                'constraint' => 40,
                'null' => true,
                'after' => 'parent_mobile',
            ],
            'secondary_email' => [
                'type' => 'VARCHAR',
                'constraint' => 190,
                'null' => true,
                'after' => 'parent_email',
            ],
            'represented_as' => [
                'type' => 'VARCHAR',
                'constraint' => 40,
                'null' => true,
                'after' => 'school_college',
            ],
            'nearest_branch' => [
                'type' => 'VARCHAR',
                'constraint' => 80,
                'null' => true,
                'after' => 'represented_as',
            ],
            'discovery_source' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'after' => 'nearest_branch',
            ],
            'reference_details' => [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'discovery_source',
            ],
        ]);

        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'trial_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'lead_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'null' => true,
            ],
            'branch_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'applicant_full_name' => [
                'type' => 'VARCHAR',
                'constraint' => 150,
                'null' => true,
            ],
            'applicant_email' => [
                'type' => 'VARCHAR',
                'constraint' => 190,
                'null' => true,
            ],
            'applicant_phone' => [
                'type' => 'VARCHAR',
                'constraint' => 40,
                'null' => true,
            ],
            'applicant_date_of_birth' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'father_name' => [
                'type' => 'VARCHAR',
                'constraint' => 150,
                'null' => true,
            ],
            'father_profession' => [
                'type' => 'VARCHAR',
                'constraint' => 150,
                'null' => true,
            ],
            'age' => [
                'type' => 'SMALLINT',
                'constraint' => 3,
                'null' => true,
            ],
            'residence' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'contact_number' => [
                'type' => 'VARCHAR',
                'constraint' => 40,
                'null' => true,
            ],
            'secondary_contact_number' => [
                'type' => 'VARCHAR',
                'constraint' => 40,
                'null' => true,
            ],
            'email' => [
                'type' => 'VARCHAR',
                'constraint' => 190,
                'null' => true,
            ],
            'secondary_email' => [
                'type' => 'VARCHAR',
                'constraint' => 190,
                'null' => true,
            ],
            'represented_as' => [
                'type' => 'VARCHAR',
                'constraint' => 40,
                'null' => true,
            ],
            'coaching_start' => [
                'type' => 'VARCHAR',
                'constraint' => 40,
                'null' => true,
            ],
            'coaching_type' => [
                'type' => 'VARCHAR',
                'constraint' => 80,
                'null' => true,
            ],
            'playing_role' => [
                'type' => 'VARCHAR',
                'constraint' => 80,
                'null' => true,
            ],
            'nearest_branch' => [
                'type' => 'VARCHAR',
                'constraint' => 80,
                'null' => true,
            ],
            'discovery_source' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'reference_details' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'achievements' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'test_variant' => [
                'type' => 'CHAR',
                'constraint' => 1,
                'null' => true,
            ],
            'status' => [
                'type' => 'VARCHAR',
                'constraint' => 40,
                'default' => 'DRAFT',
            ],
            'evaluator_user_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'null' => true,
            ],
            'submitted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'score' => [
                'type' => 'DECIMAL',
                'constraint' => '8,2',
                'null' => true,
            ],
            'result' => [
                'type' => 'VARCHAR',
                'constraint' => 80,
                'null' => true,
            ],
            'agreement_confirmed_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'agreement_confirmed_by' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'null' => true,
            ],
            'version' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'default' => 1,
            ],
            'created_by' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'updated_by' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
            'updated_at' => [
                'type' => 'DATETIME',
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('trial_id');
        $this->forge->addKey(['branch_id', 'status']);
        $this->forge->addForeignKey('trial_id', 'trials', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('lead_id', 'leads', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('evaluator_user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('agreement_confirmed_by', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('updated_by', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('admission_tests', true);
        $this->db->query(
            'ALTER TABLE admissions ADD CONSTRAINT admissions_admission_test_fk '
            . 'FOREIGN KEY (admission_test_id) REFERENCES admission_tests(id) '
            . 'ON DELETE SET NULL ON UPDATE CASCADE'
        );

        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'admission_test_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'variant' => [
                'type' => 'CHAR',
                'constraint' => 1,
            ],
            'question_number' => [
                'type' => 'TINYINT',
                'constraint' => 3,
                'unsigned' => true,
            ],
            'prompt' => [
                'type' => 'TEXT',
            ],
            'options_json' => [
                'type' => 'TEXT',
            ],
            'answer' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'marks' => [
                'type' => 'DECIMAL',
                'constraint' => '8,2',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
            'updated_at' => [
                'type' => 'DATETIME',
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['admission_test_id', 'variant', 'question_number']);
        $this->forge->addForeignKey('admission_test_id', 'admission_tests', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('admission_test_responses', true);

        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'admission_test_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'trial_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'branch_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'follow_up_at' => [
                'type' => 'DATETIME',
            ],
            'follow_up_type' => [
                'type' => 'VARCHAR',
                'constraint' => 30,
            ],
            'status' => [
                'type' => 'VARCHAR',
                'constraint' => 30,
            ],
            'assigned_user_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'null' => true,
            ],
            'notes' => [
                'type' => 'TEXT',
            ],
            'outcome' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'next_action' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'version' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'default' => 1,
            ],
            'created_by' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'updated_by' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
            'updated_at' => [
                'type' => 'DATETIME',
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['admission_test_id', 'follow_up_at']);
        $this->forge->addForeignKey('admission_test_id', 'admission_tests', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('trial_id', 'trials', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('assigned_user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('updated_by', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('admission_test_followups', true);
    }

    public function down(): void
    {
        // Forward-only migration; historical Admission Test data is retained.
    }
}
