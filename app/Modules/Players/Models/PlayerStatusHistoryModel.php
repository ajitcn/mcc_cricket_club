<?php

declare(strict_types=1);

namespace App\Modules\Players\Models;

use CodeIgniter\Model;

final class PlayerStatusHistoryModel extends Model
{
    protected $table = 'player_status_history';
    protected $returnType = 'array';
    protected $allowedFields = ['player_id', 'previous_status', 'new_status', 'reason', 'actor_user_id', 'created_at'];
    protected $useTimestamps = false;
}
