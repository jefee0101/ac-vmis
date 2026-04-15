<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_year',
        'term',
        'starts_on',
        'ends_on',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    protected $appends = [
        'status',
        'announcement',
    ];

    public function getStatusAttribute(): string
    {
        $today = now()->startOfDay();
        $startsOn = $this->starts_on?->copy()->startOfDay();
        $endsOn = $this->ends_on?->copy()->startOfDay();

        if ($startsOn && $today->lt($startsOn)) {
            return 'draft';
        }

        if ($endsOn && $today->gt($endsOn)) {
            return 'closed';
        }

        return 'open';
    }

    public function scopeOpen($query)
    {
        $today = now()->toDateString();
        return $query
            ->whereDate('starts_on', '<=', $today)
            ->whereDate('ends_on', '>=', $today);
    }

    public function documents()
    {
        return $this->hasMany(AcademicDocument::class, 'academic_period_id');
    }

    public function evaluations()
    {
        return $this->hasMany(AcademicEligibilityEvaluation::class, 'academic_period_id');
    }

    public function messages()
    {
        return $this->hasMany(AcademicPeriodMessage::class, 'academic_period_id');
    }

    public function latestMessage()
    {
        return $this->hasOne(AcademicPeriodMessage::class, 'academic_period_id')->latestOfMany('published_at');
    }

    public function getAnnouncementAttribute(): ?string
    {
        $message = $this->relationLoaded('latestMessage')
            ? $this->getRelation('latestMessage')
            : $this->latestMessage()->first();

        return $message?->message;
    }

    public function syncAnnouncement(?string $message, ?int $createdBy = null): void
    {
        $normalized = trim((string) $message);
        $current = $this->latestMessage()->first();
        $currentMessage = trim((string) ($current?->message ?? ''));

        if ($normalized === '') {
            $this->messages()->delete();
            $this->unsetRelation('latestMessage');
            return;
        }

        if ($currentMessage === $normalized) {
            return;
        }

        $this->messages()->create([
            'message' => $normalized,
            'published_at' => now(),
            'created_by' => $createdBy,
        ]);

        $this->unsetRelation('latestMessage');
    }
}
