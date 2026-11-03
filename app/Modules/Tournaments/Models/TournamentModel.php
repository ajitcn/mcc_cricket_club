<?php

declare(strict_types=1);

namespace App\Modules\Tournaments\Models;

use CodeIgniter\Model;

final class TournamentModel extends Model
{
    protected $table = 'tournaments';
    protected $returnType = 'array';
    protected $allowedFields = [
        'branch_id', 'name', 'tournament_type', 'status', 'starts_on', 'ends_on',
        'ground_id', 'description', 'created_by', 'created_at', 'updated_at',
    ];
}
