<?php

declare(strict_types=1);

namespace App\Modules\CRM\Models;

use CodeIgniter\Model;

final class LeadModel extends Model
{
    protected $table = 'leads';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'branch_id', 'created_by_user_id', 'owner_user_id', 'source', 'full_name', 'email', 'phone', 'date_of_birth', 'status',
        'remarks', 'lost_reason', 'closed_at', 'created_at', 'updated_at',
    ];
}
