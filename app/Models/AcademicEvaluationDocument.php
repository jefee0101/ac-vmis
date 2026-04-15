<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicEvaluationDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'evaluation_id',
        'document_id',
    ];

    public function evaluation()
    {
        return $this->belongsTo(AcademicEligibilityEvaluation::class, 'evaluation_id');
    }

    public function document()
    {
        return $this->belongsTo(AcademicDocument::class, 'document_id');
    }
}
