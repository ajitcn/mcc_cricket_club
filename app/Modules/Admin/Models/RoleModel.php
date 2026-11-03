<?php

declare(strict_types=1);

namespace App\Modules\Admin\Models;

use CodeIgniter\Model;

final class RoleModel extends Model
{
    protected $table = 'roles';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['name', 'created_at', 'updated_at'];
}
