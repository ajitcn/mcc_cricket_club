<?php

declare(strict_types=1);

namespace App\Modules\Matches\Models;

use CodeIgniter\Model;

final class OpponentModel extends Model
{
    protected $table = 'opponents';
    protected $returnType = 'array';
    protected $allowedFields = ['branch_id', 'name', 'is_active', 'created_at', 'updated_at'];
}
