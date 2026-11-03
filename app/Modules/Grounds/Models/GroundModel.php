<?php

declare(strict_types=1);

namespace App\Modules\Grounds\Models;

use CodeIgniter\Model;

final class GroundModel extends Model
{
    protected $table = 'grounds';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['branch_id', 'name', 'ground_type', 'description', 'is_active', 'created_by', 'created_at', 'updated_at'];
}
