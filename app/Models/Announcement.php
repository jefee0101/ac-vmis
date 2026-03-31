<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory;

    public const TYPE_APPROVAL = 'approval';
    public const TYPE_GENERAL = 'general';
    public const TYPE_ACADEMIC = 'academic';
    public const TYPE_SCHEDULE = 'schedule';
    public const TYPE_SYSTEM = 'system';

    protected $fillable = [
        'user_id',
        'title',
        'message',
        'type',
        'published_at',
        'read_at',
        'created_by',
    ];

    protected $casts = [
        'published_at' => 'datetime',
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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
