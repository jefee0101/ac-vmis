<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduleAttendance extends Model
{
    protected $fillable = [
        'schedule_id',
        'student_id',
        'status',
        'verification_method',
        'qr_token_id',
        'recorded_by',
        'recorded_at',
        'verified_at',
        'notes',
        'override_reason',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function schedule()
    {
        return $this->belongsTo(TeamSchedule::class, 'schedule_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function qrToken()
    {
        return $this->belongsTo(ScheduleQrToken::class, 'qr_token_id');
    }
}
