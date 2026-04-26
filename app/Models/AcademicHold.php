<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicHold extends Model
{
    use HasFactory;

    public const REASON_MISSING_SUBMISSIONS = 'missing_submissions';
    public const REASON_PENDING_ELIGIBILITY = 'pending_eligibility';
    public const REASON_LEGACY_STUDENT_STATUS = 'legacy_student_status';
    public const REASON_MANUAL_HOLD = 'manual_hold';

    protected $fillable = [
        'student_id',
        'source_period_id',
        'reason',
        'status',
        'started_at',
        'resolved_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function sourcePeriod()
    {
        return $this->belongsTo(AcademicPeriod::class, 'source_period_id');
    }
}
