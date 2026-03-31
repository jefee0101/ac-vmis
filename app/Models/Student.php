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
        'date_of_birth',
        'gender',
        'home_address',
        'course_or_strand',
        'current_grade_level',
        'student_status',
        'phone_number',
        'emergency_contact_name',
        'emergency_contact_relationship',
        'emergency_contact_phone',
        'height',
        'weight',
    ];

    protected $with = ['user'];

    protected $appends = [
        'first_name',
        'middle_name',
        'last_name',
        'full_name',
        'education_level',
    ];

    /**
     * Relation to the user
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getFirstNameAttribute(): ?string
    {
        return $this->user?->first_name;
    }

    public function getMiddleNameAttribute(): ?string
    {
        return $this->user?->middle_name;
    }

    public function getLastNameAttribute(): ?string
    {
        return $this->user?->last_name;
    }

    public function getFullNameAttribute(): string
    {
        return $this->user?->full_name ?? '';
    }

    public function getEducationLevelAttribute(): ?string
    {
        $raw = trim((string) ($this->current_grade_level ?? ''));
        if ($raw === '') {
            return null;
        }

        $numeric = (int) preg_replace('/[^0-9]/', '', $raw);
        if ($numeric >= 11) {
            return 'Senior High';
        }

        return 'College';
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
