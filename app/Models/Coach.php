<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Team;

class Coach extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'first_name',
        'middle_name',
        'last_name',
        'phone_number',
        'date_of_birth',
        'gender',
        'home_address',
        'coach_status',
    ];

    /**
     * Relation to the user
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Teams where this coach is the head coach
     */
    public function headTeams()
    {
        return $this->hasMany(Team::class, 'coach_id');
    }

    /**
     * Teams where this coach is the assistant coach
     */
    public function assistantTeams()
    {
        return $this->hasMany(Team::class, 'assistant_coach_id');
    }
}
