<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduleQrToken extends Model
{
    protected $fillable = [
        'schedule_id',
        'student_id',
        'token_hash',
        'issued_at',
        'expires_at',
        'used_at',
        'used_by',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function schedule()
    {
        return $this->belongsTo(TeamSchedule::class, 'schedule_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function usedBy()
    {
        return $this->belongsTo(User::class, 'used_by');
    }
}
