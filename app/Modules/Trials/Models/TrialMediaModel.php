<?php

declare(strict_types=1);

namespace App\Modules\Trials\Models;

use CodeIgniter\Model;

final class TrialMediaModel extends Model
{
    protected $table = 'trial_media';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $allowedFields = [
        'trial_id',
        'branch_id',
        'original_name',
        'storage_key',
        'mime_type',
        'file_size',
        'uploaded_by',
        'status',
        'created_at',
        'updated_at',
    ];
}
