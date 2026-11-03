<?php

declare(strict_types=1);

namespace App\Modules\Players\Models;

use CodeIgniter\Model;

final class PlayerModel extends Model
{
    protected $table = 'players';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['person_id', 'lead_id', 'admission_id', 'branch_id', 'status', 'updated_at', 'profile_revision'];
}
