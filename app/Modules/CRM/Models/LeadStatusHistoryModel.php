<?php

declare(strict_types=1);

namespace App\Modules\CRM\Models;

use CodeIgniter\Model;

final class LeadStatusHistoryModel extends Model
{
    protected $table = 'lead_status_history';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['lead_id', 'actor_user_id', 'previous_status', 'new_status', 'reason', 'created_at'];
}
