<?php

namespace App\Services;

use App\Models\Announcement;

class AnnouncementService
{
    public function announce(
        int $userId,
        string $title,
        string $message,
        string $type = 'system',
        ?int $createdBy = null
    ): void {
        $normalizedType = \App\Models\Announcement::normalizeType($type);

        Announcement::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $normalizedType,
            'published_at' => now(),
            'created_by' => $createdBy,
        ]);
    }

    /**
     * @param array<int> $userIds
     */
    public function announceMany(
        array $userIds,
        string $title,
        string $message,
        string $type = 'system',
        ?int $createdBy = null
    ): void {
        $normalizedType = \App\Models\Announcement::normalizeType($type);
        $uniqueIds = collect($userIds)
            ->filter(fn ($id) => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        foreach ($uniqueIds as $userId) {
            $this->announce($userId, $title, $message, $normalizedType, $createdBy);
        }
    }
}
