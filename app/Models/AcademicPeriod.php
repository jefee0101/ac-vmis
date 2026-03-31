<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_year',
        'term',
        'starts_on',
        'ends_on',
        'status',
        'announcement',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'status' => 'string',
    ];

    public function documents()
    {
        return $this->hasMany(AcademicDocument::class, 'academic_period_id');
    }

    public function evaluations()
    {
        return $this->hasMany(AcademicEligibilityEvaluation::class, 'academic_period_id');
    }

}
