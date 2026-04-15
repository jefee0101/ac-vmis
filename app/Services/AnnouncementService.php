<?php

namespace App\Services;

use App\Mail\AnnouncementNotificationMail;
use App\Models\Announcement;
use App\Models\AnnouncementEvent;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AnnouncementService
{
    /**
     * @var array<int, string>
     */
    private const EMAIL_PREFERENCES = [
        'notify_approvals',
        'notify_schedule_changes',
        'notify_attendance_changes',
        'notify_wellness_alerts',
        'notify_academic_alerts',
        'notify_attendance_exceptions',
        'notify_wellness_injury_threshold',
    ];

    public function announce(
        int $userId,
        string $title,
        string $message,
        string $type = 'system',
        ?int $createdBy = null,
        ?string $notificationPreference = null,
        bool $sendEmail = true,
    ): void {
        $normalizedType = \App\Models\Announcement::normalizeType($type);

        $event = AnnouncementEvent::create([
            'title' => $title,
            'message' => $message,
            'type' => $normalizedType,
            'published_at' => now(),
            'created_by' => $createdBy,
        ]);

        Announcement::create([
            'event_id' => $event->id,
            'user_id' => $userId,
        ]);

        if ($sendEmail) {
            $this->sendAnnouncementEmail(
                $userId,
                $title,
                $message,
                $normalizedType,
                $createdBy,
                $notificationPreference
            );
        }
    }

    /**
     * @param array<int> $userIds
     */
    public function announceMany(
        array $userIds,
        string $title,
        string $message,
        string $type = 'system',
        ?int $createdBy = null,
        ?string $notificationPreference = null,
        bool $sendEmail = true,
    ): void {
        $normalizedType = \App\Models\Announcement::normalizeType($type);
        $uniqueIds = collect($userIds)
            ->filter(fn ($id) => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($uniqueIds)) {
            return;
        }

        $event = AnnouncementEvent::create([
            'title' => $title,
            'message' => $message,
            'type' => $normalizedType,
            'published_at' => now(),
            'created_by' => $createdBy,
        ]);

        Announcement::insert(
            array_map(
                fn (int $userId) => [
                    'event_id' => $event->id,
                    'user_id' => $userId,
                    'read_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                $uniqueIds
            )
        );

        foreach ($uniqueIds as $userId) {
            if (!$sendEmail) {
                continue;
            }

            $this->sendAnnouncementEmail(
                $userId,
                $title,
                $message,
                $normalizedType,
                $createdBy,
                $notificationPreference
            );
        }
    }

    public function shouldSendEmailNotification(
        User $user,
        ?string $notificationPreference = null,
        ?int $createdBy = null
    ): bool {
        if (!filter_var((string) $user->email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (($createdBy ?? 0) > 0 && (int) $createdBy === (int) $user->id) {
            return false;
        }

        $settings = $user->relationLoaded('settings') ? $user->getRelation('settings') : $user->settings;
        if ($settings && !$settings->notification_email_enabled) {
            return false;
        }

        if (!$notificationPreference || !in_array($notificationPreference, self::EMAIL_PREFERENCES, true)) {
            return true;
        }

        if (!$settings) {
            return true;
        }

        return (bool) data_get($settings, $notificationPreference, true);
    }

    private function sendAnnouncementEmail(
        int $userId,
        string $title,
        string $message,
        string $type,
        ?int $createdBy,
        ?string $notificationPreference
    ): void {
        $user = User::query()
            ->with('settings')
            ->find($userId);

        if (!$user || !$this->shouldSendEmailNotification($user, $notificationPreference, $createdBy)) {
            return;
        }

        app()->terminating(function () use ($user, $title, $message, $type) {
            try {
                Mail::to($user->email)->send(new AnnouncementNotificationMail(
                    $user,
                    $title,
                    $message,
                    Announcement::labelForType($type),
                    url('/announcements'),
                ));
            } catch (\Throwable $e) {
                Log::error('Announcement email failed.', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'title' => $title,
                    'exception' => $e::class,
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'mail_host' => config('mail.mailers.smtp.host'),
                    'mail_port' => config('mail.mailers.smtp.port'),
                    'mail_scheme' => config('mail.mailers.smtp.scheme'),
                ]);
            }
        });
    }
}
