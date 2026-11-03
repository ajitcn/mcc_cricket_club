<?php

declare(strict_types=1);

namespace App\Modules\Fitness\Models;

use CodeIgniter\Model;

final class FitnessAssessmentModel extends Model
{
    protected $table = 'fitness_assessments';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'player_id', 'branch_id', 'assessment_date', 'height_cm', 'weight_kg',
        'body_fat_percent', 'vertical_jump_cm', 'sprint_time_seconds',
        'agility_seconds', 'flexibility_cm', 'yoyo_level', 'endurance_minutes',
        'coach_notes', 'recorded_by', 'created_at', 'updated_at', 'deleted_at',
    ];
}
