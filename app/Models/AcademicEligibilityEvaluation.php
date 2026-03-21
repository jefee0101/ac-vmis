<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicEligibilityEvaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'academic_period_id',
        'document_id',
        'gpa',
        'status',
        'evaluated_by',
        'evaluated_at',
        'remarks',
    ];

    protected $casts = [
        'gpa' => 'decimal:2',
        'evaluated_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function academicPeriod()
    {
        return $this->belongsTo(AcademicPeriod::class, 'academic_period_id');
    }

    public function document()
    {
        return $this->belongsTo(AcademicDocument::class, 'document_id');
    }

    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }
}

