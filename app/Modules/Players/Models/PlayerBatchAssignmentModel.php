<?php

declare(strict_types=1);

namespace App\Modules\Players\Models;

use CodeIgniter\Model;

final class PlayerBatchAssignmentModel extends Model
{
    protected $table = 'player_batch_assignments';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['player_id', 'branch_id', 'batch_id', 'assigned_by', 'effective_from', 'effective_to', 'created_at', 'updated_at'];
}
