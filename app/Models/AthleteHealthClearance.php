<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AthleteHealthClearance extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'clearance_date',
        'valid_until',
        'physician_name',
        'conditions',
        'allergies',
        'restrictions',
        'clearance_status',
        'certificate_path',
        'reviewed_by',
        'reviewed_at',
        'notes',
    ];

    protected $casts = [
        'clearance_date' => 'date',
        'valid_until' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

