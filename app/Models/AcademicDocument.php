<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicDocument extends Model
{
    use HasFactory;

    public const REVIEW_STATUS_PENDING = 'pending';
    public const REVIEW_STATUS_AUTO_PROCESSED = 'auto_processed';
    public const REVIEW_STATUS_NEEDS_REVIEW = 'needs_review';
    public const REVIEW_STATUS_REVIEWED = 'reviewed';

    protected $fillable = [
        'student_id',
        'document_type_id',
        'academic_period_id',
        'file_path',
        'uploaded_by',
        'uploaded_at',
        'notes',
        'review_status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (AcademicDocument $document) {
            $document->guardWorkflowContext();
        });
    }

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

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function documentTypeDefinition()
    {
        return $this->belongsTo(AcademicDocumentType::class, 'document_type_id');
    }

    public function ocrRuns()
    {
        return $this->hasMany(AcademicDocumentOcrRun::class, 'academic_document_id');
    }

    public function latestOcrRun()
    {
        return $this->hasOne(AcademicDocumentOcrRun::class, 'academic_document_id')->latestOfMany();
    }

    public function evaluations()
    {
        return $this->hasMany(AcademicEligibilityEvaluation::class, 'document_id');
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

    public function guardWorkflowContext(): void
    {
        $context = $this->document_context;

        if ($context === AcademicDocumentType::CONTEXT_REGISTRATION && $this->academic_period_id !== null) {
            throw new \InvalidArgumentException('Registration academic documents cannot be linked to an academic period.');
        }

        if ($context === AcademicDocumentType::CONTEXT_PERIOD_SUBMISSION && $this->academic_period_id === null) {
            throw new \InvalidArgumentException('Period submission academic documents must be linked to an academic period.');
        }
    }
}
