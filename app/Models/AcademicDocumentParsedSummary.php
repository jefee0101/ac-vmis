<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicDocumentParsedSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_document_ocr_run_id',
        'gwa',
        'total_units',
        'parser_status',
        'parser_confidence',
    ];

    protected $casts = [
        'gwa' => 'decimal:2',
        'total_units' => 'decimal:2',
        'parser_confidence' => 'decimal:2',
    ];

    public function ocrRun()
    {
        return $this->belongsTo(AcademicDocumentOcrRun::class, 'academic_document_ocr_run_id');
    }
}
