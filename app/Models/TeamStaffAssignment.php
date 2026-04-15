<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamStaffAssignment extends Model
{
    public const ROLE_HEAD = 'head';
    public const ROLE_ASSISTANT = 'assistant';

    protected $fillable = [
        'team_id',
        'coach_id',
        'role',
        'starts_at',
        'ends_at',
        'created_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function coach()
    {
        return $this->belongsTo(Coach::class, 'coach_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('ends_at');
    }
}
