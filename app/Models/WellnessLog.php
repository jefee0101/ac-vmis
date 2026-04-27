<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WellnessLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'schedule_id',
        'logged_by',
        'log_date',
        'injury_observed',
        'injury_notes',
        'injury_resolved_at',
        'injury_resolved_by',
        'fatigue_level',
        'performance_condition',
        'remarks',
    ];

    protected $casts = [
        'log_date' => 'date',
        'injury_observed' => 'boolean',
        'injury_resolved_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function schedule()
    {
        return $this->belongsTo(TeamSchedule::class, 'schedule_id');
    }

    public function logger()
    {
        return $this->belongsTo(User::class, 'logged_by');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'injury_resolved_by');
    }
}
