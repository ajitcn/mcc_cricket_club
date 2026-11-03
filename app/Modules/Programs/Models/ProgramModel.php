<?php

declare(strict_types=1);

namespace App\Modules\Programs\Models;

use CodeIgniter\Model;

final class ProgramModel extends Model
{
    protected $table = 'programs';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $allowedFields = [
        'name',
        'description',
        'created_by',
        'created_at',
        'updated_at',
    ];
}
