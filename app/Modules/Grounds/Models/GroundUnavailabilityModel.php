<?php

declare(strict_types=1);

namespace App\Modules\Grounds\Models;

use CodeIgniter\Model;

final class GroundUnavailabilityModel extends Model
{
    protected $table = 'ground_unavailability';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['ground_id', 'branch_id', 'starts_at', 'ends_at', 'reason', 'created_by', 'created_at'];
}
