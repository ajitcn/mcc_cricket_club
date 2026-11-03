<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Models;

use CodeIgniter\Model;

final class AdmissionModel extends Model
{
    protected $table = 'admissions';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'lead_id', 'applicant_full_name', 'applicant_email', 'applicant_phone', 'applicant_date_of_birth', 'gender', 'age_group', 'age_group_id', 'batch_id', 'joining_date', 'blood_group', 'medical_conditions', 'jersey_size', 'playing_role', 'batting_style', 'bowling_style', 'strong_hand', 'father_name', 'father_profession', 'mother_name', 'parent_mobile', 'secondary_contact_number', 'parent_email', 'secondary_email', 'emergency_contact', 'trial_id', 'admission_test_id', 'branch_id', 'status', 'checklist_complete', 'checklist_identity_complete',
        'checklist_contact_complete', 'checklist_admission_complete', 'checklist_branch_confirmed',
        'checklist_approval_recorded', 'approved_by', 'approved_at', 'converted_player_id',
        'created_at', 'updated_at', 'admission_number', 'admission_date', 'academic_year', 'age',
        'school_college', 'represented_as', 'nearest_branch', 'discovery_source', 'reference_details', 'residence', 'current_address', 'permanent_address', 'correspondence_address',
        'previous_coaching_centre', 'playing_experience', 'hobbies', 'achievements',
        'ability_overall', 'ability_batting', 'ability_bowling', 'ability_wicketkeeping',
        'ability_fitness', 'batting_order', 'bowling_options', 'coach_user_id', 'personal_coach_user_id', 'coaching_plan',
        'year1_age_group', 'year1_plan', 'year2_age_group', 'year2_plan', 'year3_age_group', 'year3_plan',
        'coaching_type', 'personal_coaching_days', 'personal_coaching_duration', 'estimated_personal_session', 'group_coaching_days', 'coaching_delivery',
        'fitness_plan', 'gym_fitness_session', 'match_program', 'induction_plan', 'review_frequency', 'progress_report_frequency',
        'feedback_call_frequency', 'meditation_sessions', 'elite_camp', 'elite_camp_days', 'elite_camp_duration', 'estimated_elite_session', 'student_diary', 'welcome_kit',
        'admission_fee', 'training_fee', 'installment_plan', 'installment_due_dates',
        'hostel_required', 'hostel_charges', 'hostel_payment_plan', 'hostel_installment_plan', 'documents_submitted',
        'total_taxable_fee', 'tax_rate', 'calculated_tax_amount', 'total_including_tax',
        'declaration_accepted', 'student_signature', 'parent_signature', 'authorized_signature',
        'confirmation_notes', 'year1_start_date', 'year1_end_date', 'year2_start_date',
        'year2_end_date', 'year3_start_date', 'year3_end_date',
        'coaching_plan_label', 'fee_plan_label', 'hostel_plan_label', 'academic_plan_label',
    ];
}
