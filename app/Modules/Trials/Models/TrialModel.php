<?php

declare(strict_types=1);

namespace App\Modules\Trials\Models;

use CodeIgniter\Model;

final class TrialModel extends Model
{
    protected $table = 'trials';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'lead_id', 'branch_id', 'trial_date', 'trial_time', 'location', 'coach_user_id', 'secondary_coach_user_id', 'status',
        'selection_outcome', 'remarks', 'cancellation_reason', 'rescheduled_from_id', 'created_by',
        'ability_overall', 'ability_batting', 'ability_bowling', 'ability_wicketkeeping',
        'ability_fitness', 'created_at', 'updated_at',
    ];
}
