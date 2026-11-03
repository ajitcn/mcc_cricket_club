<?php

declare(strict_types=1);

namespace App\Modules\Matches\Models;

use CodeIgniter\Model;

final class MatchPlayerModel extends Model
{
    protected $table = 'match_players';
    protected $primaryKey = 'match_id';
    protected $returnType = 'array';
    protected $allowedFields = ['match_id', 'player_id', 'selection_type', 'created_at'];
}
