<?php

declare(strict_types=1);

namespace App\Modules\Coaches\Models;

use CodeIgniter\Model;

final class CoachProfileModel extends Model
{
    protected $table = 'coach_profiles';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'user_id',
        'specialization',
        'experience_years',
        'qualification',
        'certifications',
        'availability',
        'is_active',
        'created_at',
        'updated_at',
    ];
}
