<?php

declare(strict_types=1);

namespace App\Modules\MeetingFollowUps\Models;

use CodeIgniter\Model;

final class MeetingFollowUpModel extends Model
{
    protected $table = 'admission_test_followups';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'admission_test_id', 'trial_id', 'branch_id', 'follow_up_at', 'start_at', 'end_at',
        'follow_up_type', 'status', 'assigned_user_id', 'notes', 'outcome', 'next_action',
        'version', 'created_by', 'updated_by', 'created_at', 'updated_at',
    ];
}
