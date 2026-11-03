<?php

declare(strict_types=1);

namespace App\Modules\Trials\Models;

use CodeIgniter\Model;

final class TrialHistoryModel extends Model
{
    protected $table = 'trial_history';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['trial_id', 'lead_id', 'actor_user_id', 'event_type', 'previous_date', 'previous_time', 'new_date', 'new_time', 'previous_status', 'new_status', 'selection_outcome', 'reason', 'created_at'];
}
