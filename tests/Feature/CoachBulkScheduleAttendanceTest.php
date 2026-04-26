<?php

use App\Models\Coach;
use App\Models\ScheduleAttendance;
use App\Models\Student;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\TeamStaffAssignment;
use App\Models\TeamSchedule;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function makeBulkAttendanceFixture(string $email = 'assistant-bulk-attendance@example.com'): array
{
    $assistantUser = User::query()->create([
        'first_name' => 'Assistant',
        'last_name' => 'Coach',
        'email' => $email,
        'password' => Hash::make('password'),
        'role' => 'coach',
        'account_state' => 'active',
    ]);

    $assistantCoach = Coach::query()->create([
        'user_id' => $assistantUser->id,
        'phone_number' => '09170000111',
        'coach_status' => 'Active',
    ]);

    $headUser = User::query()->create([
        'first_name' => 'Head',
        'last_name' => 'Coach',
        'email' => 'head-' . uniqid() . '@example.com',
        'password' => Hash::make('password'),
        'role' => 'coach',
        'account_state' => 'active',
    ]);

    $headCoach = Coach::query()->create([
        'user_id' => $headUser->id,
        'phone_number' => '09170000112',
        'coach_status' => 'Active',
    ]);

    $team = Team::query()->create([
        'team_name' => 'Bulk Attendance Team',
        'sport_id' => null,
        'year' => 2026,
        'description' => 'Assistant-managed attendance test team',
    ]);

    TeamStaffAssignment::query()->create([
        'team_id' => $team->id,
        'coach_id' => $headCoach->id,
        'role' => TeamStaffAssignment::ROLE_HEAD,
        'starts_at' => now(),
        'ends_at' => null,
        'created_by' => $headUser->id,
    ]);

    TeamStaffAssignment::query()->create([
        'team_id' => $team->id,
        'coach_id' => $assistantCoach->id,
        'role' => TeamStaffAssignment::ROLE_ASSISTANT,
        'starts_at' => now(),
        'ends_at' => null,
        'created_by' => $headUser->id,
    ]);

    $students = collect(range(1, 2))->map(function (int $index) use ($team) {
        $user = User::query()->create([
            'first_name' => 'Student',
            'last_name' => "Player{$index}",
            'email' => "bulk-attendance-student-{$index}-" . uniqid() . '@example.com',
            'password' => Hash::make('password'),
            'role' => 'student-athlete',
            'account_state' => 'active',
        ]);

        $student = Student::query()->create([
            'user_id' => $user->id,
            'student_id_number' => "2026-30{$index}",
            'first_name' => 'Student',
            'last_name' => "Player{$index}",
            'date_of_birth' => '2005-01-01',
            'gender' => 'Female',
            'home_address' => 'Sample Address',
            'course_or_strand' => 'BSIT',
            'current_grade_level' => '1',
            'approval_status' => 'approved',
            'student_status' => 'Enrolled',
            'phone_number' => '09170000113',
            'emergency_contact_name' => 'Parent',
            'emergency_contact_relationship' => 'Parent',
            'emergency_contact_phone' => '09170000114',
        ]);

        TeamPlayer::query()->create([
            'team_id' => $team->id,
            'student_id' => $student->id,
            'player_status' => 'active',
        ]);

        return $student;
    });

    $schedule = TeamSchedule::query()->create([
        'team_id' => $team->id,
        'title' => 'Coach-led Practice',
        'type' => 'practice',
        'venue' => 'Main Gym',
        'start_time' => now()->subHour(),
        'end_time' => now()->addHour(),
        'notes' => 'Attendance is coach managed.',
    ]);

    return [$assistantUser, $team, $schedule, $students];
}

it('allows an assistant coach to save attendance in bulk from the schedule workflow', function () {
    [$assistantUser, $team, $schedule, $students] = makeBulkAttendanceFixture();

    $response = $this->actingAs($assistantUser)->postJson("/coach/schedules/{$schedule->id}/attendance/bulk", [
        'rows' => [
            [
                'student_id' => $students[0]->id,
                'status' => 'present',
                'notes' => '',
            ],
            [
                'student_id' => $students[1]->id,
                'status' => 'absent',
                'notes' => 'Family emergency',
            ],
        ],
    ]);

    $response->assertOk()->assertJson([
        'message' => 'Attendance saved successfully.',
        'attendance_count' => 2,
    ]);

    $records = ScheduleAttendance::query()
        ->where('schedule_id', $schedule->id)
        ->orderBy('student_id')
        ->get();

    expect($records)->toHaveCount(2)
        ->and($records[0]->status)->toBe('present')
        ->and($records[0]->verification_method)->toBe('manual_override')
        ->and($records[1]->status)->toBe('absent')
        ->and($records[1]->notes)->toBe('Family emergency');
});

it('blocks bulk attendance updates before the schedule starts', function () {
    [$assistantUser, $team, $schedule] = makeBulkAttendanceFixture('assistant-bulk-upcoming@example.com');

    $schedule->update([
        'start_time' => now()->addHour(),
        'end_time' => now()->addHours(2),
    ]);

    $this->actingAs($assistantUser)->postJson("/coach/schedules/{$schedule->id}/attendance/bulk", [
        'rows' => [],
    ])->assertStatus(422)->assertJson([
        'message' => 'Attendance can only be recorded once the schedule has started.',
    ]);
});

it('keeps late and excused statuses available in the schedule attendance modal workflow', function () {
    [$assistantUser, $team, $schedule, $students] = makeBulkAttendanceFixture('assistant-bulk-statuses@example.com');

    $response = $this->actingAs($assistantUser)->postJson("/coach/schedules/{$schedule->id}/attendance/bulk", [
        'rows' => [
            [
                'student_id' => $students[0]->id,
                'status' => 'late',
                'notes' => 'Traffic delay',
            ],
            [
                'student_id' => $students[1]->id,
                'status' => 'excused',
                'notes' => 'Class conflict',
            ],
        ],
    ]);

    $response->assertOk()->assertJson([
        'message' => 'Attendance saved successfully.',
        'attendance_count' => 2,
    ]);

    $records = ScheduleAttendance::query()
        ->where('schedule_id', $schedule->id)
        ->orderBy('student_id')
        ->get();

    expect($records)->toHaveCount(2)
        ->and($records[0]->status)->toBe('late')
        ->and($records[0]->notes)->toBe('Traffic delay')
        ->and($records[1]->status)->toBe('excused')
        ->and($records[1]->notes)->toBe('Class conflict');
});
