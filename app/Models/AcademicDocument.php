<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'document_type_id',
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
        return $query->whereHas('documentTypeDefinition', function ($typeQuery) {
            $typeQuery->where('context', AcademicDocumentType::CONTEXT_REGISTRATION);
        });
    }

    public function scopePeriodSubmission($query)
    {
        return $query->whereHas('documentTypeDefinition', function ($typeQuery) {
            $typeQuery->where('context', AcademicDocumentType::CONTEXT_PERIOD_SUBMISSION);
        });
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

    public function documentTypeDefinition()
    {
        return $this->belongsTo(AcademicDocumentType::class, 'document_type_id');
    }

    public function getDocumentTypeAttribute(): ?string
    {
        $type = $this->relationLoaded('documentTypeDefinition')
            ? $this->getRelation('documentTypeDefinition')
            : $this->documentTypeDefinition()->first();

        return $type?->code;
    }

    public function getDocumentContextAttribute(): ?string
    {
        $type = $this->relationLoaded('documentTypeDefinition')
            ? $this->getRelation('documentTypeDefinition')
            : $this->documentTypeDefinition()->first();

        return $type?->context;
    }
}
