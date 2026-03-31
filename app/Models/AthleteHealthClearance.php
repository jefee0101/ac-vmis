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

    protected $appends = [
        'clearance_status',
    ];

    public static function statusForAttributes(
        ?string $validUntil,
        ?string $conditions,
        ?string $allergies,
        ?string $restrictions
    ): string {
        if ($validUntil === null || trim((string) $validUntil) === '') {
            return 'not_fit';
        }

        $today = now()->toDateString();
        if ($validUntil < $today) {
            return 'expired';
        }

        $hasNotes = trim((string) $conditions) !== ''
            || trim((string) $allergies) !== ''
            || trim((string) $restrictions) !== '';

        if ($hasNotes) {
            return 'fit_with_restrictions';
        }

        return 'fit';
    }

    public static function statusCaseSql(string $alias = ''): string
    {
        $prefix = $alias !== '' ? $alias . '.' : '';

        return "CASE
            WHEN {$prefix}valid_until IS NULL THEN 'not_fit'
            WHEN {$prefix}valid_until < ? THEN 'expired'
            WHEN (COALESCE({$prefix}conditions, '') <> '' OR COALESCE({$prefix}allergies, '') <> '' OR COALESCE({$prefix}restrictions, '') <> '') THEN 'fit_with_restrictions'
            ELSE 'fit'
        END";
    }

    public function getClearanceStatusAttribute(): string
    {
        return self::statusForAttributes(
            $this->valid_until?->toDateString(),
            $this->conditions,
            $this->allergies,
            $this->restrictions
        );
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
