<?php

namespace App\Http\Controllers\StudentAthlete;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\Student;
use Illuminate\Validation\Rule;

class StudentAthleteController extends Controller
{
    public function index(Request $request)
    {
        // Get the logged-in student-athlete
        $student = Student::where('user_id', Auth::id())->first();

        if (!$student) {
            // If not a student-athlete, return empty team
            return Inertia::render('StudentAthletes/MyTeam', [
                'team' => null,
                'teams' => [],
                'selectedTeamId' => null,
                'currentStudentId' => null,
            ]);
        }

        // Find teams where the student is a member
        $teams = Team::with([
            'sport',
            'coach.user',
            'assistantCoach.user',
            'players.student'
        ])
            ->whereHas('players', function($q) use ($student) {
                $q->where('student_id', $student->id);
            })
            ->orderBy('team_name')
            ->get();

        if ($teams->isEmpty()) {
            return Inertia::render('StudentAthletes/MyTeam', [
                'team' => null,
                'teams' => [],
                'selectedTeamId' => null,
                'currentStudentId' => $student->id,
            ]);
        }

        $teamIds = $teams->pluck('id')->all();
        $selectedTeamId = (int) $request->query('team_id', 0);
        if (!$selectedTeamId || !in_array($selectedTeamId, $teamIds, true)) {
            $selectedTeamId = $teamIds[0];
        }

        $team = $teams->firstWhere('id', $selectedTeamId);

        if ($team) {
            $coach = $team->coach ? [
                'id' => $team->coach->id,
                'first_name' => $team->coach->first_name,
                'last_name' => $team->coach->last_name,
                'phone_number' => $team->coach->phone_number,
                'email' => $team->coach->user?->email,
            ] : null;

            $assistantCoach = $team->assistantCoach ? [
                'id' => $team->assistantCoach->id,
                'first_name' => $team->assistantCoach->first_name,
                'last_name' => $team->assistantCoach->last_name,
                'phone_number' => $team->assistantCoach->phone_number,
                'email' => $team->assistantCoach->user?->email,
            ] : null;

            $team = [
                'id' => $team->id,
                'team_name' => $team->team_name,
                'team_avatar' => $team->team_avatar,
                'sport' => $team->sport,
                'year' => $team->year,
                'coach' => $coach,
                'assistantCoach' => $assistantCoach,
                'players' => $team->players,
            ];
        }
        return Inertia::render('StudentAthletes/MyTeam', [
            'team' => $team,
            'teams' => $teams->map(function (Team $team) {
                return [
                    'id' => $team->id,
                    'team_name' => $team->team_name,
                    'sport' => $team->sport?->name ?? $team->sport_id ?? 'unknown',
                ];
            })->values(),
            'selectedTeamId' => $selectedTeamId,
            'currentStudentId' => $student->id,
        ]);
    }

    public function updateDesiredJersey(Request $request, TeamPlayer $teamPlayer)
    {
        $student = Student::where('user_id', Auth::id())->first();
        if (!$student || $teamPlayer->student_id !== $student->id) {
            abort(403);
        }

        $validated = $request->validate([
            'jersey_number' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('team_players', 'jersey_number')
                    ->where(fn ($query) => $query->where('team_id', $teamPlayer->team_id))
                    ->ignore($teamPlayer->id),
            ],
        ]);

        $jerseyNumber = trim((string) ($validated['jersey_number'] ?? ''));
        $teamPlayer->update([
            'jersey_number' => $jerseyNumber === '' ? null : $jerseyNumber,
        ]);

        return back()->with('success', 'Desired jersey number updated.');
    }
}   
