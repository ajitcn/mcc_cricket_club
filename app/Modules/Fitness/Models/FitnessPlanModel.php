<?php

declare(strict_types=1);

namespace App\Modules\Fitness\Models;

use CodeIgniter\Model;

final class FitnessPlanModel extends Model
{
    protected $table = 'fitness_plans';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'player_id', 'branch_id', 'coach_user_id', 'name', 'goals', 'starts_on',
        'ends_on', 'status', 'notes', 'created_by', 'created_at', 'updated_at', 'deleted_at',
    ];
}
