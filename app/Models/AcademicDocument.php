<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicDocument extends Model
{
    use HasFactory;

    public const CONTEXT_REGISTRATION = 'registration';
    public const CONTEXT_PERIOD_SUBMISSION = 'period_submission';

    protected $fillable = [
        'student_id',
        'document_type',
        'document_context',
        'academic_period_id',
        'file_path',
        'uploaded_by',
        'uploaded_at',
        'notes',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public function scopeRegistration($query)
    {
        return $query->where('document_context', self::CONTEXT_REGISTRATION);
    }

    public function scopePeriodSubmission($query)
    {
        return $query->where('document_context', self::CONTEXT_PERIOD_SUBMISSION);
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function academicPeriod()
    {
        return $this->belongsTo(AcademicPeriod::class, 'academic_period_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
