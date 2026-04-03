<?php

use App\Mail\AnnouncementNotificationMail;
use App\Models\Announcement;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\AnnouncementService;
use Illuminate\Support\Facades\Mail;

function createNotificationSettings(User $user, array $overrides = []): void
{
    UserSetting::create(array_merge([
        'user_id' => $user->id,
        'notification_email_enabled' => true,
        'notification_in_app_enabled' => true,
        'notify_approvals' => true,
        'notify_schedule_changes' => true,
        'notify_attendance_changes' => true,
        'notify_wellness_alerts' => true,
        'notify_academic_alerts' => true,
        'notify_attendance_exceptions' => true,
        'notify_wellness_injury_threshold' => true,
        'wellness_injury_threshold_level' => 3,
        'theme_preference' => 'light',
        'timezone' => 'Asia/Manila',
        'language' => 'en',
    ], $overrides));
}

it('stores the announcement and emails the recipient when the preference is enabled', function () {
    Mail::fake();

    $actor = User::factory()->create([
        'role' => 'admin',
        'status' => 'approved',
    ]);

    $recipient = User::factory()->create([
        'role' => 'coach',
        'status' => 'approved',
    ]);

    createNotificationSettings($recipient);

    app(AnnouncementService::class)->announce(
        $recipient->id,
        'Roster Updated',
        '2 athletes were added to Team Falcons.',
        'system',
        $actor->id,
        'notify_attendance_exceptions'
    );

    expect(Announcement::query()->count())->toBe(1);

    Mail::assertSent(AnnouncementNotificationMail::class, function (AnnouncementNotificationMail $mail) use ($recipient) {
        return $mail->hasTo($recipient->email)
            && $mail->notificationTitle === 'Roster Updated';
    });
});

it('does not send an email when the mapped preference is disabled', function () {
    Mail::fake();

    $actor = User::factory()->create([
        'role' => 'admin',
        'status' => 'approved',
    ]);

    $recipient = User::factory()->create([
        'role' => 'student-athlete',
        'status' => 'approved',
    ]);

    createNotificationSettings($recipient, [
        'notify_attendance_exceptions' => false,
    ]);

    app(AnnouncementService::class)->announce(
        $recipient->id,
        'Team Assignment',
        'You were added to Team Falcons.',
        'system',
        $actor->id,
        'notify_attendance_exceptions'
    );

    expect(Announcement::query()->count())->toBe(1);
    Mail::assertNothingSent();
});

it('does not email self-authored notifications', function () {
    Mail::fake();

    $user = User::factory()->create([
        'role' => 'student-athlete',
        'status' => 'approved',
    ]);

    createNotificationSettings($user);

    app(AnnouncementService::class)->announce(
        $user->id,
        'Attendance Status Updated',
        'Attendance marked: PRESENT for Morning Practice.',
        'schedule',
        $user->id,
        'notify_attendance_changes'
    );

    expect(Announcement::query()->count())->toBe(1);
    Mail::assertNothingSent();
});

it('can skip the email delivery while still creating the in-app announcement', function () {
    Mail::fake();

    $actor = User::factory()->create([
        'role' => 'admin',
        'status' => 'approved',
    ]);

    $recipient = User::factory()->create([
        'role' => 'coach',
        'status' => 'approved',
    ]);

    app(AnnouncementService::class)->announce(
        $recipient->id,
        'Account Approved',
        'Your account has been approved.',
        'approval',
        $actor->id,
        'notify_approvals',
        false
    );

    expect(Announcement::query()->count())->toBe(1);
    Mail::assertNothingSent();
});
