<?php

declare(strict_types=1);

namespace App\Modules\Admin\Models;

use CodeIgniter\Model;

final class BranchModel extends Model
{
    protected $table = 'branches';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['name', 'code', 'address', 'is_active', 'created_at', 'updated_at'];
}
