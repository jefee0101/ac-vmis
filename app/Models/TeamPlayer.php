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

    protected $fillable = [
        'team_id',
        'student_id',
        'jersey_number',
        'athlete_position',
        'player_status',
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
