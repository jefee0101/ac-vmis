<?php

use App\Mail\AnnouncementNotificationMail;
use App\Models\Announcement;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\BrevoTransactionalMailer;
use App\Services\SystemNotificationService;
use Mockery\MockInterface;

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
    $sent = [];
    app()->instance(BrevoTransactionalMailer::class, mock(BrevoTransactionalMailer::class, function (MockInterface $mock) use (&$sent) {
        $mock->shouldReceive('sendMailable')
            ->once()
            ->andReturnUsing(function (string $email, AnnouncementNotificationMail $mailable, ?string $name = null) use (&$sent) {
                $sent[] = compact('email', 'mailable', 'name');
            });
    }));

    $actor = User::factory()->create([
        'role' => 'admin',
        'account_state' => 'active',
    ]);

    $recipient = User::factory()->create([
        'role' => 'coach',
        'account_state' => 'active',
    ]);

    createNotificationSettings($recipient);

    app(SystemNotificationService::class)->announce(
        $recipient->id,
        'Roster Updated',
        '2 athletes were added to Team Falcons.',
        'system',
        $actor->id,
        'notify_attendance_exceptions'
    );

    expect(Announcement::query()->count())->toBe(1);
    expect($sent)->toHaveCount(1)
        ->and($sent[0]['email'])->toBe($recipient->email)
        ->and($sent[0]['name'])->toBe($recipient->name)
        ->and($sent[0]['mailable'])->toBeInstanceOf(AnnouncementNotificationMail::class)
        ->and($sent[0]['mailable']->notificationTitle)->toBe('Roster Updated');
});

it('does not send an email when the mapped preference is disabled', function () {
    app()->instance(BrevoTransactionalMailer::class, mock(BrevoTransactionalMailer::class, function (MockInterface $mock) {
        $mock->shouldNotReceive('sendMailable');
    }));

    $actor = User::factory()->create([
        'role' => 'admin',
        'account_state' => 'active',
    ]);

    $recipient = User::factory()->create([
        'role' => 'student-athlete',
        'account_state' => 'active',
    ]);

    createNotificationSettings($recipient, [
        'notify_attendance_exceptions' => false,
    ]);

    app(SystemNotificationService::class)->announce(
        $recipient->id,
        'Team Assignment',
        'You were added to Team Falcons.',
        'system',
        $actor->id,
        'notify_attendance_exceptions'
    );

    expect(Announcement::query()->count())->toBe(1);
});

it('does not email self-authored notifications', function () {
    app()->instance(BrevoTransactionalMailer::class, mock(BrevoTransactionalMailer::class, function (MockInterface $mock) {
        $mock->shouldNotReceive('sendMailable');
    }));

    $user = User::factory()->create([
        'role' => 'student-athlete',
        'account_state' => 'active',
    ]);

    createNotificationSettings($user);

    app(SystemNotificationService::class)->announce(
        $user->id,
        'Attendance Status Updated',
        'Attendance marked: PRESENT for Morning Practice.',
        'schedule',
        $user->id,
        'notify_attendance_changes'
    );

    expect(Announcement::query()->count())->toBe(1);
});

it('can skip the email delivery while still creating the in-app announcement', function () {
    app()->instance(BrevoTransactionalMailer::class, mock(BrevoTransactionalMailer::class, function (MockInterface $mock) {
        $mock->shouldNotReceive('sendMailable');
    }));

    $actor = User::factory()->create([
        'role' => 'admin',
        'account_state' => 'active',
    ]);

    $recipient = User::factory()->create([
        'role' => 'coach',
        'account_state' => 'active',
    ]);

    app(SystemNotificationService::class)->announce(
        $recipient->id,
        'Account Approved',
        'Your account has been approved.',
        'approval',
        $actor->id,
        'notify_approvals',
        false
    );

    expect(Announcement::query()->count())->toBe(1);
});
