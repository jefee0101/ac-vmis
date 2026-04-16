<?php

namespace App\Http\Controllers\Coaches;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\User;
use App\Services\SystemNotificationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CoachTeamController extends Controller
{
    public function __construct(private SystemNotificationService $notifications)
    {
    }

    public function index()
    {
        $coach = request()->user()?->coach;

        if (!$coach) {
            return Inertia::render('Coaches/CoachTeam', [
                'team' => null,
                'teams' => [],
                'selectedTeamId' => null,
            ]);
        }

        $teams = Team::with([
            'sport',
            'coach',
            'assistantCoach',
            'players.student.user'
        ])
        ->forCoach($coach->id)
        ->orderBy('team_name')
        ->get();

        if ($teams->isEmpty()) {
            return Inertia::render('Coaches/CoachTeam', [
                'team' => null,
                'teams' => [],
                'selectedTeamId' => null,
            ]);
        }

        $teamIds = $teams->pluck('id')->all();
        $selectedTeamId = (int) request()->query('team_id', 0);
        if (!$selectedTeamId || !in_array($selectedTeamId, $teamIds, true)) {
            $selectedTeamId = $teamIds[0];
        }

        $team = $teams->firstWhere('id', $selectedTeamId);
        if ($team) {
            $team = [
                'id' => $team->id,
                'team_name' => $team->team_name,
                'team_avatar' => $team->team_avatar,
                'sport' => $team->sport,
                'year' => $team->year,
                'coach' => $team->coach, // will have first_name, last_name, etc.
                'assistantCoach' => $team->assistantCoach,
                'players' => $team->players,
            ];
        }

        return Inertia::render('Coaches/CoachTeam', [
            'team' => $team,
            'teams' => $teams->map(function (Team $team) {
                return [
                    'id' => $team->id,
                    'team_name' => $team->team_name,
                    'sport' => $team->sport?->name ?? $team->sport_id ?? 'unknown',
                ];
            })->values(),
            'selectedTeamId' => $selectedTeamId,
        ]);
    }

    public function printRoster(Request $request)
    {
        $coach = $request->user()?->coach;
        abort_unless($coach, 403);

        $teams = Team::with([
            'sport',
            'coach',
            'assistantCoach',
            'players.student.user',
        ])
            ->forCoach($coach->id)
            ->get();

        abort_unless($teams->isNotEmpty(), 403);

        $teamIds = $teams->pluck('id')->all();
        $selectedTeamId = (int) $request->query('team_id', 0);
        if (!$selectedTeamId || !in_array($selectedTeamId, $teamIds, true)) {
            $selectedTeamId = $teamIds[0];
        }

        $team = $teams->firstWhere('id', $selectedTeamId);
        abort_unless($team, 404);

        $players = $team->players
            ->sortBy(function ($player) {
                return strtolower(($player->student?->last_name ?? '') . ' ' . ($player->student?->first_name ?? ''));
            })
            ->values()
            ->map(fn ($player) => [
                'name' => trim(($player->student?->first_name ?? '') . ' ' . ($player->student?->last_name ?? '')),
                'student_id_number' => $player->student?->student_id_number,
                'jersey_number' => $player->jersey_number,
                'athlete_position' => $player->athlete_position,
                'player_status' => $player->player_status ?? 'active',
            ])
            ->all();

        return view('print.team-roster', [
            'contextLabel' => 'Coach Report',
            'generatedAt' => now()->format('M j, Y g:i A'),
            'team' => [
                'team_name' => $team->team_name,
                'sport' => $team->sport?->name ?? 'Unknown',
                'year' => $team->year,
                'coach' => trim(($team->coach?->first_name ?? '') . ' ' . ($team->coach?->last_name ?? '')) ?: 'Unassigned',
                'assistant' => trim(($team->assistantCoach?->first_name ?? '') . ' ' . ($team->assistantCoach?->last_name ?? '')) ?: 'Unassigned',
            ],
            'players' => $players,
        ]);
    }

    public function updatePlayerPosition(Request $request, TeamPlayer $teamPlayer)
    {
        $coach = $request->user()?->coach;
        if (!$coach) {
            abort(403);
        }

        $teamPlayer->load(['team', 'student']);
        $team = $teamPlayer->team;
        if (!$team || !in_array($coach->id, $team->activeCoachIds(), true)) {
            abort(403);
        }

        $validated = $request->validate([
            'athlete_position' => 'nullable|string|max:100',
        ]);

        $position = trim((string) ($validated['athlete_position'] ?? ''));
        $teamPlayer->update([
            'athlete_position' => $position === '' ? null : $position,
        ]);

        $studentUserId = $teamPlayer->student?->user_id;
        if ($studentUserId) {
            $message = $position === ''
                ? "Your team position in {$team->team_name} was cleared by your coach."
                : "Your team position in {$team->team_name} was set to {$position}.";

            $this->notifications->announce(
                (int) $studentUserId,
                'Team Position Update',
                $message,
                'system',
                $request->user()?->id,
                'notify_attendance_exceptions'
            );
        }

        return back()->with('success', 'Player position updated.');
    }

    public function updatePlayerStatus(Request $request, TeamPlayer $teamPlayer)
    {
        $coach = $request->user()?->coach;
        if (!$coach) {
            abort(403);
        }

        $teamPlayer->load(['team', 'student']);
        $team = $teamPlayer->team;
        if (!$team || !in_array($coach->id, $team->activeCoachIds(), true)) {
            abort(403);
        }

        $validated = $request->validate([
            'player_status' => 'required|in:active,injured,suspended',
        ]);

        $teamPlayer->update([
            'player_status' => $validated['player_status'],
        ]);

        return back()->with('success', 'Player status updated.');
    }

    public function requestChange(Request $request)
    {
        $validated = $request->validate([
            'team_id' => 'nullable|integer',
            'type' => 'required|in:assistant_add,assistant_remove,player_add,player_remove,team_change',
            'target' => 'nullable|string|max:120',
            'notes' => 'nullable|string|max:500',
        ]);

        $coach = $request->user()?->coach;
        if (!$coach) {
            abort(403);
        }

        $teams = Team::query()
            ->forCoach($coach->id)
            ->get();

        if ($teams->isEmpty()) {
            abort(403, 'No team assigned.');
        }

        $teamIds = $teams->pluck('id')->all();
        $selectedTeamId = (int) ($validated['team_id'] ?? 0);
        if (!$selectedTeamId || !in_array($selectedTeamId, $teamIds, true)) {
            if (count($teamIds) === 1) {
                $selectedTeamId = $teamIds[0];
            } else {
                return back()->withErrors(['team_id' => 'Please select a team.']);
            }
        }

        $team = $teams->firstWhere('id', $selectedTeamId);
        if (!$team) {
            abort(403, 'Invalid team selection.');
        }

        $coachName = trim(($coach->first_name ?? '') . ' ' . ($coach->last_name ?? ''));
        $title = match ($validated['type']) {
            'assistant_add' => 'Assistant Coach Request',
            'assistant_remove' => 'Assistant Coach Removal Request',
            'player_add' => 'Athlete Add Request',
            'player_remove' => 'Athlete Removal Request',
            'team_change' => 'Team Change Request',
            default => 'Team Request',
        };

        $target = trim((string) ($validated['target'] ?? ''));
        $notes = trim((string) ($validated['notes'] ?? ''));
        $messageLines = [
            "Team: {$team->team_name}",
            "Requested by: {$coachName}",
        ];

        if ($target !== '') {
            $messageLines[] = "Target: {$target}";
        }
        if ($notes !== '') {
            $messageLines[] = "Notes: {$notes}";
        }

        $adminUserIds = User::query()
            ->where('account_state', 'active')
            ->where('role', 'admin')
            ->pluck('id')
            ->all();

        $this->notifications->announceMany(
            $adminUserIds,
            $title,
            implode("\n", $messageLines),
            'system',
            $request->user()?->id ?? 0,
            'notify_attendance_exceptions'
        );

        return back()->with('success', 'Request sent to admin.');
    }
}
