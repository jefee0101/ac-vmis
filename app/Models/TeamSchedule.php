<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamSchedule extends Model
{
    protected $fillable = [
        'team_id',
        'title',
        'type',
        'venue',
        'start_time',
        'end_time',
        'notes',
        'qr_window_minutes',
        'qr_rotation_seconds',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }
    public function attendances()
    {
        return $this->hasMany(ScheduleAttendance::class, 'schedule_id');
    }

    public function wellnessLogs()
    {
        return $this->hasMany(WellnessLog::class, 'schedule_id');
    }

    public function qrTokens()
    {
        return $this->hasMany(ScheduleQrToken::class, 'schedule_id');
    }
}
