<?php

declare(strict_types=1);

namespace App\Modules\Performance\Models;

use CodeIgniter\Model;

final class PerformanceRecordModel extends Model
{
    protected $table = 'performance_records';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'branch_id', 'player_id', 'session_id', 'record_type', 'recorded_on',
        'runs', 'balls_faced', 'dismissals', 'fours', 'sixes', 'wickets',
        'runs_conceded', 'legal_balls', 'catches', 'stumpings', 'run_outs',
        'notes', 'recorded_by', 'created_at', 'updated_at', 'deleted_at',
    ];
}
