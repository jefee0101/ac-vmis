<?php

use App\Models\Coach;
use App\Models\ScheduleAttendance;
use App\Models\Student;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\TeamSchedule;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function makeStudentScheduleFixture(string $email = 'student-schedule-actions@example.com'): array
{
    $studentUser = User::query()->create([
        'first_name' => 'Student',
        'last_name' => 'Schedule',
        'email' => $email,
        'password' => Hash::make('password'),
        'role' => 'student-athlete',
        'account_state' => 'active',
    ]);

    $student = Student::query()->create([
        'user_id' => $studentUser->id,
        'student_id_number' => '2026-2001',
        'first_name' => 'Student',
        'last_name' => 'Schedule',
        'date_of_birth' => '2005-04-20',
        'gender' => 'Female',
        'home_address' => 'Sample Address',
        'course_or_strand' => 'BSIT',
        'current_grade_level' => '1',
        'approval_status' => 'approved',
        'student_status' => 'Enrolled',
        'phone_number' => '09170000031',
        'emergency_contact_name' => 'Parent Schedule',
        'emergency_contact_relationship' => 'Parent',
        'emergency_contact_phone' => '09179990031',
    ]);

    $coachUser = User::query()->create([
        'first_name' => 'Coach',
        'last_name' => 'Owner',
        'email' => 'coach-owner-' . uniqid() . '@example.com',
        'password' => Hash::make('password'),
        'role' => 'coach',
        'account_state' => 'active',
    ]);

    $coach = Coach::query()->create([
        'user_id' => $coachUser->id,
        'phone_number' => '09170000032',
        'coach_status' => 'Active',
    ]);

    $team = Team::query()->create([
        'team_name' => 'Student Schedule Team',
        'coach_id' => $coach->id,
        'assistant_coach_id' => null,
        'sport_id' => null,
        'year' => 2026,
        'description' => 'Student schedule attendance test team',
    ]);

    TeamPlayer::query()->create([
        'team_id' => $team->id,
        'student_id' => $student->id,
        'player_status' => 'active',
    ]);

    return [$studentUser, $student, $team];
}

it('returns a recoverable validation error instead of an exception page when student attendance is outside the check-in window', function () {
    [$studentUser, $student, $team] = makeStudentScheduleFixture('student-schedule-closed-window@example.com');

    $schedule = TeamSchedule::query()->create([
        'team_id' => $team->id,
        'title' => 'Future Practice',
        'type' => 'practice',
        'venue' => 'Main Gym',
        'start_time' => now()->addHour(),
        'end_time' => now()->addHours(2),
        'notes' => null,
    ]);

    $response = $this->actingAs($studentUser)->from('/MySchedule')->put("/Student/Schedules/{$schedule->id}/attendance", [
        'status' => 'present',
        'notes' => null,
    ]);

    $response->assertRedirect('/MySchedule');
    $response->assertSessionHasErrors('attendance');
    expect(ScheduleAttendance::query()->count())->toBe(0);
});

it('returns a json 422 message for qr requests outside the check-in window', function () {
    [$studentUser, $student, $team] = makeStudentScheduleFixture('student-schedule-qr-closed@example.com');

    $schedule = TeamSchedule::query()->create([
        'team_id' => $team->id,
        'title' => 'Future Meeting',
        'type' => 'meeting',
        'venue' => 'Conference Room',
        'start_time' => now()->addHour(),
        'end_time' => now()->addHours(2),
        'notes' => null,
    ]);

    $this->actingAs($studentUser)
        ->getJson("/Student/Schedules/{$schedule->id}/qr-token")
        ->assertStatus(422)
        ->assertJson([
            'message' => 'QR check-in is unavailable. Check-in starts when the schedule starts.',
        ]);
});
