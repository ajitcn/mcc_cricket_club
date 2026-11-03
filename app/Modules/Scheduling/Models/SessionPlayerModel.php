<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Models;

use CodeIgniter\Model;

final class SessionPlayerModel extends Model
{
    protected $table = 'session_players';
    protected $primaryKey = 'session_id';
    protected $returnType = 'array';
    protected $allowedFields = ['session_id', 'player_id', 'created_at'];
}
