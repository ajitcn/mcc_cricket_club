<?php

declare(strict_types=1);

namespace App\Modules\Matches\Models;

use CodeIgniter\Model;

final class MatchModel extends Model
{
    protected $table = 'matches';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['branch_id', 'ground_id', 'coach_user_id', 'opponent_id', 'opponent', 'your_team', 'your_team_opponent_id', 'match_type', 'match_category', 'status', 'starts_at', 'ends_at', 'result', 'score_summary', 'coach_remarks', 'created_by', 'created_at', 'updated_at'];
}
