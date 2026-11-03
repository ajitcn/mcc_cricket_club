<?php

declare(strict_types=1);

namespace App\Modules\AdmissionTests\Models;

use CodeIgniter\Model;

final class AdmissionTestModel extends Model
{
    protected $table = 'admission_tests';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'trial_id', 'lead_id', 'branch_id', 'applicant_full_name', 'applicant_email',
        'applicant_phone', 'applicant_date_of_birth', 'father_name', 'father_profession',
        'age', 'residence', 'contact_number', 'secondary_contact_number', 'email',
        'secondary_email', 'represented_as', 'coaching_start', 'coaching_type',
        'playing_role', 'nearest_branch', 'discovery_source', 'reference_details',
        'achievements', 'test_variant', 'status', 'evaluator_user_id', 'submitted_at',
        'score', 'result', 'assessment_remarks', 'agreement_confirmed_at', 'agreement_confirmed_by',
        'training_fee', 'installment_plan', 'hostel_charges', 'hostel_installment_plan',
        'batting_mark', 'wicketkeeping_mark', 'bowling_mark', 'fitness_mark',
        'written_test_mark', 'overall_mark',
        'version', 'created_by', 'updated_by', 'created_at', 'updated_at',
    ];
}
