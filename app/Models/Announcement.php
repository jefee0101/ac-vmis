<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory;

    protected $table = 'announcement_recipients';

    public const TYPE_APPROVAL = 'approval';
    public const TYPE_GENERAL = 'general';
    public const TYPE_ACADEMIC = 'academic';
    public const TYPE_SCHEDULE = 'schedule';
    public const TYPE_SYSTEM = 'system';

    protected $fillable = [
        'event_id',
        'user_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    /**
     * @return array<int, string>
     */
    public static function allowedTypes(): array
    {
        return [
            self::TYPE_APPROVAL,
            self::TYPE_GENERAL,
            self::TYPE_ACADEMIC,
            self::TYPE_SCHEDULE,
            self::TYPE_SYSTEM,
        ];
    }

    public static function normalizeType(?string $type): string
    {
        $value = strtolower(trim((string) $type));

        if (in_array($value, self::allowedTypes(), true)) {
            return $value;
        }

        return self::TYPE_SYSTEM;
    }

    public static function labelForType(?string $type): string
    {
        return match (self::normalizeType($type)) {
            self::TYPE_APPROVAL => 'Approval',
            self::TYPE_ACADEMIC => 'Academic',
            self::TYPE_SCHEDULE => 'Schedule',
            self::TYPE_GENERAL => 'General',
            default => 'System',
        };
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function event()
    {
        return $this->belongsTo(AnnouncementEvent::class, 'event_id');
    }

    public function getTitleAttribute(): ?string
    {
        return $this->event?->title;
    }

    public function getMessageAttribute(): ?string
    {
        return $this->event?->message;
    }

    public function getTypeAttribute(): string
    {
        return self::normalizeType($this->event?->type);
    }

    public function getPublishedAtAttribute()
    {
        return $this->event?->published_at;
    }

    public function getCreatedByAttribute(): ?int
    {
        return $this->event?->created_by;
    }

    public function getCreatorAttribute(): ?User
    {
        return $this->event?->creator;
    }
}
