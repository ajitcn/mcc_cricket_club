<?php

declare(strict_types=1);

namespace App\Modules\Players\Models;

use CodeIgniter\Model;

final class BatchModel extends Model
{
    protected $table = 'batches';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'branch_id',
        'program_id',
        'name',
        'is_active',
        'created_at',
        'updated_at',
    ];
}
