<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicDocumentType extends Model
{
    public const CONTEXT_REGISTRATION = 'registration';
    public const CONTEXT_PERIOD_SUBMISSION = 'period_submission';

    public const CODE_TOR = 'tor';
    public const CODE_GRADE_REPORT = 'grade_report';
    public const CODE_SUPPORTING_DOCUMENT = 'supporting_document';

    protected $fillable = [
        'context',
        'code',
        'label',
    ];

    public $timestamps = false;

    public static function resolveId(string $context, string $code): int
    {
        $id = static::query()
            ->where('context', $context)
            ->where('code', $code)
            ->value('id');

        if (!$id) {
            throw new \InvalidArgumentException("Unknown academic document type [{$context}:{$code}].");
        }

        return (int) $id;
    }
}
