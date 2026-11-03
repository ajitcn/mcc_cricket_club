<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Models;

use CodeIgniter\Model;

final class PlayerConversionModel extends Model
{
    protected $table = 'players';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['person_id', 'lead_id', 'admission_id', 'branch_id', 'status', 'created_at', 'updated_at'];
}
