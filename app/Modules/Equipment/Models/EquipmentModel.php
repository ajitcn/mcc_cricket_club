<?php

declare(strict_types=1);

namespace App\Modules\Equipment\Models;

use CodeIgniter\Model;

final class EquipmentModel extends Model
{
    protected $table = 'equipment';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $deletedField = 'deleted_at';
    protected $allowedFields = [
        'name',
        'quantity',
        'branch_id',
        'created_by',
        'created_at',
        'updated_at',
        'deleted_at',
    ];
}
