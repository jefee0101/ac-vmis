<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Team;
use App\Models\Student;

class TeamPlayer extends Model
{
    use HasFactory;

    protected $table = 'team_players';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INJURED = 'injured';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'team_id',
        'student_id',
        'jersey_number',
        'athlete_position',
        'player_status',
        'manual_inactive',
    ];

    protected $casts = [
        'manual_inactive' => 'boolean',
    ];

    // Relations
    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'id');
    }
}
