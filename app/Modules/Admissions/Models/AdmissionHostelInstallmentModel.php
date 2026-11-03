<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Models;

use CodeIgniter\Model;

final class AdmissionHostelInstallmentModel extends Model
{
    protected $table = 'admission_hostel_installments';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'admission_id',
        'admission_period_id',
        'year_number',
        'year_label',
        'installment_number',
        'due_date',
        'amount',
        'created_by',
        'version',
        'created_at',
        'updated_at',
    ];
}
