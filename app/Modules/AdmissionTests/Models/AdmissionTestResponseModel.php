<?php

declare(strict_types=1);

namespace App\Modules\AdmissionTests\Models;

use CodeIgniter\Model;

final class AdmissionTestResponseModel extends Model
{
    protected $table = 'admission_test_responses';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'admission_test_id', 'variant', 'question_number', 'prompt', 'options_json',
        'answer', 'marks', 'created_at', 'updated_at',
    ];
}
