<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Models;

use CodeIgniter\Model;

final class AdmissionFollowUpModel extends Model
{
    protected $table = 'admission_followups';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'admission_id',
        'branch_id',
        'follow_up_at',
        'follow_up_type',
        'status',
        'notes',
        'outcome',
        'next_follow_up_at',
        'assigned_user_id',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
    ];
}
