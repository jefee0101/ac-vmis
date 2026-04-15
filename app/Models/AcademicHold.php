<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicHold extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'source_period_id',
        'reason',
        'status',
        'started_at',
        'resolved_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function sourcePeriod()
    {
        return $this->belongsTo(AcademicPeriod::class, 'source_period_id');
    }
}
