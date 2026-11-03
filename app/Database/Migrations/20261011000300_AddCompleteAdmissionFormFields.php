<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddCompleteAdmissionFormFields extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('admissions')) {
            return;
        }

        $fields = $this->db->getFieldNames('admissions');
        $add = [];
        $textFields = [
            'admission_number' => 60,
            'academic_year' => 30,
            'school_college' => 190,
            'previous_coaching_centre' => 190,
            'ability_overall' => 40,
            'ability_batting' => 40,
            'ability_bowling' => 40,
            'ability_wicketkeeping' => 40,
            'ability_fitness' => 40,
            'batting_order' => 80,
            'student_signature' => 190,
            'parent_signature' => 190,
            'authorized_signature' => 190,
            'year1_age_group' => 80,
            'year1_plan' => 190,
            'year2_age_group' => 80,
            'year2_plan' => 190,
            'year3_age_group' => 80,
            'year3_plan' => 190,
            'personal_coaching_days' => 40,
            'personal_coaching_duration' => 40,
            'group_coaching_days' => 40,
            'coaching_delivery' => 100,
            'fitness_plan' => 100,
            'match_program' => 100,
            'induction_plan' => 100,
            'review_frequency' => 40,
            'progress_report_frequency' => 40,
            'feedback_call_frequency' => 40,
            'meditation_sessions' => 40,
            'elite_camp' => 80,
            'student_diary' => 40,
            'welcome_kit' => 100,
        ];
        foreach ($textFields as $name => $length) {
            if (! in_array($name, $fields, true)) {
                $add[$name] = ['type' => 'VARCHAR', 'constraint' => $length, 'null' => true];
            }
        }

        foreach ([
            'age',
            'hostel_required',
            'declaration_accepted',
        ] as $name) {
            if (! in_array($name, $fields, true)) {
                $add[$name] = [
                    'type' => $name === 'age' ? 'SMALLINT' : 'TINYINT',
                    'constraint' => 3,
                    'unsigned' => true,
                    'null' => $name === 'age',
                    'default' => $name === 'age' ? null : 0,
                ];
            }
        }

        foreach (['admission_date', 'year1_start_date', 'year1_end_date', 'year2_start_date', 'year2_end_date', 'year3_start_date', 'year3_end_date'] as $name) {
            if (! in_array($name, $fields, true)) {
                $add[$name] = ['type' => 'DATE', 'null' => true];
            }
        }

        foreach (['admission_fee', 'training_fee', 'hostel_charges'] as $name) {
            if (! in_array($name, $fields, true)) {
                $add[$name] = ['type' => 'DECIMAL', 'constraint' => '12,2', 'unsigned' => true, 'null' => true];
            }
        }

        foreach (['current_address', 'permanent_address', 'correspondence_address', 'playing_experience', 'hobbies', 'achievements', 'bowling_options', 'coaching_plan', 'installment_plan', 'installment_due_dates', 'hostel_payment_plan', 'documents_submitted', 'confirmation_notes'] as $name) {
            if (! in_array($name, $fields, true)) {
                $add[$name] = ['type' => 'TEXT', 'null' => true];
            }
        }

        if (! in_array('coach_user_id', $fields, true)) {
            $add['coach_user_id'] = ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true];
        }

        if ($add !== []) {
            $this->forge->addColumn('admissions', $add);
        }

        $createTable = $this->db->query('SHOW CREATE TABLE admissions')->getRowArray();
        $createSql = is_array($createTable) ? implode(' ', array_map('strval', $createTable)) : '';
        if (str_contains($createSql, 'admissions_coach_user_fk') === false) {
            $this->db->query('ALTER TABLE admissions ADD CONSTRAINT admissions_coach_user_fk FOREIGN KEY (coach_user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE');
        }
    }

    public function down(): void
    {
        // Forward-only: admission intake data must not be removed by rollback.
    }
}
