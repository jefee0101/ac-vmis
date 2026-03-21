<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Coach;
use App\Models\Student;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\UserSetting;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'avatar', // added avatar
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Relation to Student
     */
    public function student()
    {
        return $this->hasOne(Student::class, 'user_id');
    }

    /**
     * Relation to Coach
     */
    public function coach()
    {
        return $this->hasOne(Coach::class, 'user_id');
    }

    /**
     * Get all teams associated with this user
     */
    public function teams()
    {
        if ($this->role === 'coach') {
            $coachId = $this->coach?->id;
            if (!$coachId) {
                return collect();
            }

            return Team::where('coach_id', $coachId)
                ->orWhere('assistant_coach_id', $coachId)
                ->get();
        } elseif ($this->role === 'student' || $this->role === 'student-athlete') {
            return Team::whereHas('players', function ($q) {
                $q->where('student_id', $this->student->id);
            })->get();
        } else {
            return collect(); // empty collection for admin or other roles
        }
    }
    public function recordedAttendances()
    {
        return $this->hasMany(ScheduleAttendance::class, 'recorded_by');
    }

    public function reviewedHealthClearances()
    {
        return $this->hasMany(AthleteHealthClearance::class, 'reviewed_by');
    }

    public function wellnessLogs()
    {
        return $this->hasMany(WellnessLog::class, 'logged_by');
    }

    public function wellnessAttachments()
    {
        return $this->hasMany(WellnessAttachment::class, 'uploaded_by');
    }

    public function uploadedAcademicDocuments()
    {
        return $this->hasMany(AcademicDocument::class, 'uploaded_by');
    }

    public function academicEvaluations()
    {
        return $this->hasMany(AcademicEligibilityEvaluation::class, 'evaluated_by');
    }

    public function announcements()
    {
        return $this->hasMany(Announcement::class, 'user_id');
    }

    public function createdAnnouncements()
    {
        return $this->hasMany(Announcement::class, 'created_by');
    }

    public function settings()
    {
        return $this->hasOne(UserSetting::class, 'user_id');
    }
}
