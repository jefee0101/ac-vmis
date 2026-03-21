<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\TeamPlayer;
use App\Models\Team;

class Student extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'student_id_number',
        'first_name',
        'middle_name',
        'last_name',
        'date_of_birth',
        'gender',
        'home_address',
        'course',
        'education_level',
        'current_grade_level',
        'year_level',
        'student_status',
        'phone_number',
        'emergency_contact_name',
        'emergency_contact_relationship',
        'emergency_contact_phone',
        'height',
        'weight',
    ];

    /**
     * Relation to the user
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Teams the student belongs to (via pivot table team_players)
     */
    public function teams()
    {
        return $this->hasMany(TeamPlayer::class, 'student_id');
    }

    public function attendances()
    {
        return $this->hasMany(ScheduleAttendance::class, 'student_id');
    }

    public function healthClearances()
    {
        return $this->hasMany(AthleteHealthClearance::class, 'student_id');
    }

    public function latestHealthClearance()
    {
        return $this->hasOne(AthleteHealthClearance::class, 'student_id')->latestOfMany();
    }

    public function wellnessLogs()
    {
        return $this->hasMany(WellnessLog::class, 'student_id');
    }

    public function academicDocuments()
    {
        return $this->hasMany(AcademicDocument::class, 'student_id');
    }

    public function latestAcademicDocument()
    {
        return $this->hasOne(AcademicDocument::class, 'student_id')->latestOfMany();
    }

    public function academicEvaluations()
    {
        return $this->hasMany(AcademicEligibilityEvaluation::class, 'student_id');
    }
}
