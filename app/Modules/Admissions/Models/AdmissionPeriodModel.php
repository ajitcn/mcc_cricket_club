<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Models;

use CodeIgniter\Model;

final class AdmissionPeriodModel extends Model
{
    protected $table = 'admission_periods';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'admission_id',
        'branch_id',
        'period_number',
        'start_date',
        'end_date',
        'age_group_id',
        'age_group_label',
        'coaching_plan_label',
        'fee_plan_label',
        'hostel_plan_label',
        'academic_plan_label',
        'renewal_values',
        'status',
        'created_by',
        'request_hash',
        'created_at',
        'updated_at',
    ];
}
