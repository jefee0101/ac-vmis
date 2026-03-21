<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class AcademicPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_year',
        'term',
        'starts_on',
        'ends_on',
        'is_submission_open',
        'announcement',
        'is_locked',
        'locked_at',
        'locked_by',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'is_submission_open' => 'boolean',
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
    ];

    public function documents()
    {
        return $this->hasMany(AcademicDocument::class, 'academic_period_id');
    }

    public function evaluations()
    {
        return $this->hasMany(AcademicEligibilityEvaluation::class, 'academic_period_id');
    }

    public function lockedBy()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }
}
