<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicDocumentOcrRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_document_id',
        'ocr_engine',
        'ocr_engine_version',
        'run_status',
        'raw_text',
        'mean_confidence',
        'validation_status',
        'validation_summary',
        'validation_flags',
        'validation_checked_at',
        'processed_at',
        'error_message',
    ];

    protected $casts = [
        'mean_confidence' => 'decimal:2',
        'validation_flags' => 'array',
        'validation_checked_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function academicDocument()
    {
        return $this->belongsTo(AcademicDocument::class, 'academic_document_id');
    }

    public function parsedSummary()
    {
        return $this->hasOne(AcademicDocumentParsedSummary::class, 'academic_document_ocr_run_id');
    }

    public function evaluations()
    {
        return $this->hasMany(AcademicEligibilityEvaluation::class, 'academic_document_ocr_run_id');
    }
}
