<?php

use App\Models\Sport;
use App\Models\Student;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

function makeStudentDashboardFixture(string $email = 'student-dashboard@example.com'): array
{
    $user = User::query()->create([
        'first_name' => 'Dashboard',
        'last_name' => 'Student',
        'email' => $email,
        'password' => Hash::make('password'),
        'role' => 'student-athlete',
        'account_state' => 'active',
    ]);

    $student = Student::query()->create([
        'user_id' => $user->id,
        'student_id_number' => '2026-1001',
        'first_name' => 'Dashboard',
        'last_name' => 'Student',
        'date_of_birth' => '2005-10-10',
        'gender' => 'Female',
        'home_address' => 'Sample Address',
        'course_or_strand' => 'BSIT',
        'current_grade_level' => '1',
        'approval_status' => 'approved',
        'student_status' => 'Enrolled',
        'phone_number' => '09170000011',
        'emergency_contact_name' => 'Parent Student',
        'emergency_contact_relationship' => 'Parent',
        'emergency_contact_phone' => '09179990011',
    ]);

    return [$user, $student];
}

it('marks the student dashboard as team-assigned when the athlete belongs to a team', function () {
    [$user, $student] = makeStudentDashboardFixture('student-with-team@example.com');

    $sport = Sport::query()->firstOrCreate([
        'name' => 'Basketball',
    ]);

    $team = Team::query()->create([
        'team_name' => 'Blue Hawks',
        'sport_id' => $sport->id,
        'year' => 2026,
        'description' => 'Varsity basketball team',
    ]);

    TeamPlayer::query()->create([
        'team_id' => $team->id,
        'student_id' => $student->id,
        'player_status' => 'active',
    ]);

    $this->actingAs($user)
        ->get('/StudentAthleteDashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('StudentAthletes/StudentAthleteDashboard')
            ->where('dashboard.kpis.has_team_assignment', true)
        );
});

it('marks the student dashboard as not team-assigned when the athlete has no team membership', function () {
    [$user] = makeStudentDashboardFixture('student-without-team@example.com');

    $this->actingAs($user)
        ->get('/StudentAthleteDashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('StudentAthletes/StudentAthleteDashboard')
            ->where('dashboard.kpis.has_team_assignment', false)
        );
});
