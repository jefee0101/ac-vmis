<?php

use App\Models\Coach;
use App\Models\Team;
use App\Models\TeamSchedule;
use App\Models\TeamStaffAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

function makeCoachScheduleFixture(string $email = 'coach-schedule@example.com'): array
{
    $user = User::query()->create([
        'first_name' => 'Coach',
        'last_name' => 'Scheduler',
        'email' => $email,
        'password' => Hash::make('password'),
        'role' => 'coach',
        'account_state' => 'active',
    ]);

    $coach = Coach::query()->create([
        'user_id' => $user->id,
        'phone_number' => '09170000021',
        'coach_status' => 'Active',
    ]);

    $team = Team::query()->create([
        'team_name' => 'Schedule Test Team',
        'sport_id' => null,
        'year' => 2026,
        'description' => 'Coach schedule test team',
    ]);

    TeamStaffAssignment::query()->create([
        'team_id' => $team->id,
        'coach_id' => $coach->id,
        'role' => TeamStaffAssignment::ROLE_HEAD,
        'starts_at' => now(),
        'ends_at' => null,
        'created_by' => $user->id,
    ]);

    return [$user, $coach, $team];
}

it('allows a coach to create a schedule when the submitted type uses title case labels', function () {
    [$user, $coach, $team] = makeCoachScheduleFixture('coach-schedule-title-case@example.com');

    $response = $this->actingAs($user)->post('/coach/schedules', [
        'team_id' => $team->id,
        'title' => 'Morning Practice',
        'type' => 'Practice',
        'venue' => 'Main Gym',
        'start_time' => '2026-05-01 08:00:00',
        'end_time' => '2026-05-01 10:00:00',
        'notes' => 'Bring training bibs.',
    ]);

    $response->assertRedirect(route('coach.schedule.index', ['team_id' => $team->id]));

    $schedule = TeamSchedule::query()->firstOrFail();

    expect($schedule->team_id)->toBe($team->id)
        ->and($schedule->title)->toBe('Morning Practice')
        ->and($schedule->type)->toBe('practice')
        ->and($schedule->venue)->toBe('Main Gym');
});
