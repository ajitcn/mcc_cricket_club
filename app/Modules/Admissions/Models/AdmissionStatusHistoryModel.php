<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Models;

use CodeIgniter\Model;

final class AdmissionStatusHistoryModel extends Model
{
    protected $table = 'admission_status_history';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['admission_id', 'actor_user_id', 'previous_status', 'new_status', 'reason', 'created_at'];
}
