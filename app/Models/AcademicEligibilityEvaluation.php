<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicEligibilityEvaluation extends Model
{
    use HasFactory;

    public const GPA_ELIGIBLE_MAX = 2.0;
    public const GPA_PROBATION_MAX = 2.5;

    protected $fillable = [
        'student_id',
        'academic_period_id',
        'document_id',
        'gpa',
        'evaluated_by',
        'evaluated_at',
        'remarks',
    ];

    protected $casts = [
        'gpa' => 'decimal:2',
        'evaluated_at' => 'datetime',
    ];

    protected $appends = [
        'status',
    ];

    public static function statusForGpa(?float $gpa): ?string
    {
        if ($gpa === null) {
            return null;
        }

        if ($gpa <= self::GPA_ELIGIBLE_MAX) {
            return 'eligible';
        }

        if ($gpa <= self::GPA_PROBATION_MAX) {
            return 'probation';
        }

        return 'ineligible';
    }

    public static function statusCaseSql(string $alias = 'e'): string
    {
        $prefix = $alias !== '' ? $alias . '.' : '';

        return "CASE
            WHEN {$prefix}gpa IS NULL THEN NULL
            WHEN {$prefix}gpa <= " . self::GPA_ELIGIBLE_MAX . " THEN 'eligible'
            WHEN {$prefix}gpa <= " . self::GPA_PROBATION_MAX . " THEN 'probation'
            ELSE 'ineligible'
        END";
    }

    public function getStatusAttribute(): ?string
    {
        return self::statusForGpa($this->gpa !== null ? (float) $this->gpa : null);
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function academicPeriod()
    {
        return $this->belongsTo(AcademicPeriod::class, 'academic_period_id');
    }

    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }

    public function document()
    {
        return $this->belongsTo(AcademicDocument::class, 'document_id');
    }
}
