<?php

namespace App\Services;

use App\Mail\AnnouncementNotificationMail;
use App\Models\Announcement;
use App\Models\AnnouncementEvent;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;

class SystemNotificationService
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

    public function __construct(private BrevoTransactionalMailer $mailer)
    {
    }

    public function announce(
        int $userId,
        string $title,
        string $message,
        string $type = 'system',
        ?int $createdBy = null,
        ?string $notificationPreference = null,
        bool $sendEmail = true,
    ): void {
        $normalizedType = Announcement::normalizeType($type);
        $event = $this->createAnnouncementEvent($title, $message, $normalizedType, $createdBy);

        $this->storeAnnouncementRecipient($event->id, $userId);

        if (!$sendEmail) {
            return;
        }

        $user = $this->findNotificationUser($userId);

        if (!$user) {
            return;
        }

        $this->sendUserEmail(
            $user,
            $this->buildAnnouncementMail($user, $title, $message, $normalizedType),
            $this->buildAnnouncementEmailOptions($title, $normalizedType, $notificationPreference, $createdBy)
        );
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
        $normalizedType = Announcement::normalizeType($type);
        $uniqueIds = $this->normalizeUserIds($userIds);

        if (empty($uniqueIds)) {
            return;
        }

        $event = $this->createAnnouncementEvent($title, $message, $normalizedType, $createdBy);
        $this->storeAnnouncementRecipients($event->id, $uniqueIds);

        if (!$sendEmail) {
            return;
        }

        $this->findNotificationUsers($uniqueIds)
            ->each(function (User $user) use ($title, $message, $normalizedType, $createdBy, $notificationPreference) {
                $this->sendUserEmail(
                    $user,
                    $this->buildAnnouncementMail($user, $title, $message, $normalizedType),
                    $this->buildAnnouncementEmailOptions(
                        $title,
                        $normalizedType,
                        $notificationPreference,
                        $createdBy
                    )
                );
            });
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

    /**
     * @param array<string, mixed> $options
     */
    public function sendUserEmail(User $user, Mailable $mailable, array $options = []): bool
    {
        $respectPreferences = (bool) ($options['respect_preferences'] ?? true);
        $notificationPreference = $options['notification_preference'] ?? null;
        $createdBy = isset($options['created_by']) ? (int) $options['created_by'] : null;

        if ($respectPreferences && !$this->shouldSendEmailNotification($user, $notificationPreference, $createdBy)) {
            return false;
        }

        return $this->sendEmail(
            $user->email,
            $mailable,
            $user->name,
            $options
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    public function sendEmail(string $email, Mailable $mailable, ?string $name = null, array $options = []): bool
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $defer = (bool) ($options['defer'] ?? true);
        $context = array_filter([
            'email' => $email,
            'mailable' => $mailable::class,
            'mail_provider' => 'brevo_api',
            'mail_timeout' => config('services.brevo.timeout'),
            ...((array) ($options['context'] ?? [])),
        ], fn ($value) => $value !== null && $value !== '');

        $deliver = function () use ($email, $mailable, $name, $context): bool {
            try {
                $this->mailer->sendMailable($email, $mailable, $name);

                return true;
            } catch (\Throwable $e) {
                Log::error('System notification email failed.', [
                    ...$context,
                    'exception' => $e::class,
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                ]);

                return false;
            }
        };

        if ($defer && !$this->shouldDeliverImmediately()) {
            app()->terminating($deliver);

            return true;
        }

        return $deliver();
    }

    private function shouldDeliverImmediately(): bool
    {
        if (app()->runningUnitTests()) {
            return true;
        }

        return !(bool) config('notifications.defer_email_delivery', true);
    }

    private function createAnnouncementEvent(
        string $title,
        string $message,
        string $type,
        ?int $createdBy
    ): AnnouncementEvent {
        return AnnouncementEvent::create([
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'published_at' => now(),
            'created_by' => $createdBy,
        ]);
    }

    private function storeAnnouncementRecipient(int $eventId, int $userId): void
    {
        Announcement::create([
            'event_id' => $eventId,
            'user_id' => $userId,
        ]);
    }

    /**
     * @param array<int> $userIds
     */
    private function storeAnnouncementRecipients(int $eventId, array $userIds): void
    {
        $timestamp = now();

        Announcement::insert(
            array_map(
                fn (int $userId) => [
                    'event_id' => $eventId,
                    'user_id' => $userId,
                    'read_at' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ],
                $userIds
            )
        );
    }

    private function findNotificationUser(int $userId): ?User
    {
        return User::query()
            ->with('settings')
            ->find($userId);
    }

    /**
     * @param array<int> $userIds
     */
    private function findNotificationUsers(array $userIds)
    {
        return User::query()
            ->with('settings')
            ->whereIn('id', $userIds)
            ->get();
    }

    /**
     * @param array<int> $userIds
     * @return array<int>
     */
    private function normalizeUserIds(array $userIds): array
    {
        return collect($userIds)
            ->filter(fn ($id) => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function buildAnnouncementMail(
        User $user,
        string $title,
        string $message,
        string $type
    ): AnnouncementNotificationMail {
        return new AnnouncementNotificationMail(
            $user,
            $title,
            $message,
            Announcement::labelForType($type),
            url((string) config('notifications.announcement_action_url', '/announcements')),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAnnouncementEmailOptions(
        string $title,
        string $type,
        ?string $notificationPreference,
        ?int $createdBy
    ): array {
        return [
            'notification_preference' => $notificationPreference,
            'created_by' => $createdBy,
            'context' => [
                'communication' => 'announcement',
                'title' => $title,
                'type' => $type,
            ],
        ];
    }
}
