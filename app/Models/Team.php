<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $fillable = [
        'team_name',
        'team_avatar',
        'sport_id',
        'year',
        'coach_id',
        'assistant_coach_id',
        'description',
        'archived_at',
        'archived_by',
    ];

    protected $casts = [
        'archived_at' => 'datetime',
    ];

    public function sport()
    {
        return $this->belongsTo(Sport::class, 'sport_id');
    }

    public function coach()
    {
        return $this->belongsTo(Coach::class, 'coach_id');
    }

    public function assistantCoach()
    {
        return $this->belongsTo(Coach::class, 'assistant_coach_id');
    }

    public function players()
    {
        return $this->hasMany(TeamPlayer::class, 'team_id');
    }

    public function schedules()
    {
        return $this->hasMany(TeamSchedule::class, 'team_id');
    }

    public function archivedByUser()
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function wellnessLogs()
    {
        return $this->hasMany(WellnessLog::class, 'team_id');
    }
}
