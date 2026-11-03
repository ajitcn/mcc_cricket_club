<?php

declare(strict_types=1);

namespace App\Modules\CRM\Models;

use CodeIgniter\Model;

final class LeadSourceModel extends Model
{
    protected $table = 'lead_sources';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['name', 'is_active', 'sort_order', 'created_at', 'updated_at'];
}
