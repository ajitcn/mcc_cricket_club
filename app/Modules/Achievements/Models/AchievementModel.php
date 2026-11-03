<?php

declare(strict_types=1);

namespace App\Modules\Achievements\Models;

use CodeIgniter\Model;

final class AchievementModel extends Model
{
    protected $table = 'achievements';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'branch_id',
        'category',
        'title',
        'description',
        'is_active',
        'created_by',
        'created_at',
        'updated_at',
    ];
    protected $useTimestamps = false;
}
