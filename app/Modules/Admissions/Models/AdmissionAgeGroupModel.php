<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Models;

use CodeIgniter\Model;

final class AdmissionAgeGroupModel extends Model
{
    protected $table = 'admission_age_groups';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'name', 'branch_id', 'is_active', 'display_order', 'created_by', 'created_at', 'updated_at',
    ];
}
