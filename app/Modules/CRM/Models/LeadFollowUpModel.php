<?php

declare(strict_types=1);

namespace App\Modules\CRM\Models;

use CodeIgniter\Model;

final class LeadFollowUpModel extends Model
{
    protected $table = 'lead_follow_ups';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'lead_id', 'actor_user_id', 'follow_up_type', 'note', 'follow_up_at', 'status', 'outcome',
        'cancellation_reason', 'completed_at', 'cancelled_at', 'updated_by', 'updated_at', 'created_at',
    ];
}
