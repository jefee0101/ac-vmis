<?php

namespace App\Http\Controllers\Coaches;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Team;
use App\Models\Coach;
use App\Models\TeamSchedule;
use App\Models\TeamPlayer;
use App\Models\Student;
use App\Services\AnnouncementService;
use Carbon\Carbon;

class CoachScheduleController extends Controller
{
    public function __construct(private AnnouncementService $announcements)
    {
    }

    public function index(Request $request)
    {
        $coach = $request->user()?->coach;

        if (!$coach) {
            return Inertia::render('Coaches/CoachSchedule', [
                'schedules' => [],
                'teams' => [],
                'selectedTeamId' => null,
            ]);
        }

        $teams = Team::with('sport')
            ->forCoach($coach->id)
            ->orderBy('team_name')
            ->get();

        if ($teams->isEmpty()) {
            return Inertia::render('Coaches/CoachSchedule', [
                'schedules' => [],
                'teams' => [],
                'selectedTeamId' => null,
            ]);
        }

        $teamIds = $teams->pluck('id')->all();
        $selectedTeamId = (int) $request->query('team_id', 0);
        if (!$selectedTeamId || !in_array($selectedTeamId, $teamIds, true)) {
            $selectedTeamId = $teamIds[0];
        }

        // Convert datetime to ISO 8601 for VueCal compatibility
        $schedules = TeamSchedule::with(['team.sport'])
            ->withCount('attendances')
            ->where('team_id', $selectedTeamId)
            ->orderBy('start_time')
            ->get();

        $rosterCount = (int) TeamPlayer::where('team_id', $selectedTeamId)->count();
        $tz = config('app.timezone');
        $now = Carbon::now($tz);

        $schedules = $schedules->map(function ($s) use ($teamIds, $rosterCount, $now, $tz) {
                $start = Carbon::parse($s->start_time, $tz);
                $end = Carbon::parse($s->end_time, $tz);

                if ($end->lt($now)) {
                    $status = 'completed';
                } elseif ($start->lte($now) && $end->gte($now)) {
                    $status = 'in_progress';
                } else {
                    $status = 'upcoming';
                }

                $attendanceCount = (int) ($s->attendances_count ?? 0);

                if ($attendanceCount === 0) {
                    $attendanceState = 'not_started';
                } elseif ($rosterCount > 0 && $attendanceCount >= $rosterCount) {
                    $attendanceState = 'completed';
                } else {
                    $attendanceState = 'in_progress';
                }

                $isLocked = $status === 'completed' && $attendanceCount > 0;

                return [
                    'id' => $s->id,
                    'title' => $s->title,
                    'type' => $s->type,
                    'venue' => $s->venue,
                    'sport' => $s->team?->sport?->name ?? $s->team?->sport_id ?? 'unknown',
                    'team_id' => $s->team_id,
                    'is_owner' => in_array($s->team_id, $teamIds, true),
                    'start' => Carbon::parse($s->start_time)->toIso8601String(),
                    'end' => Carbon::parse($s->end_time)->toIso8601String(),
                    'notes' => $s->notes,
                    'status' => $status,
                    'attendance_state' => $attendanceState,
                    'attendance_count' => $attendanceCount,
                    'roster_count' => $rosterCount,
                    'is_locked' => $isLocked,
                ];
            });

        return Inertia::render('Coaches/CoachSchedule', [
            'schedules' => $schedules,
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'team_id'    => 'nullable|integer',
            'title'      => 'required|string|max:255',
            'type'       => 'required|string|max:50',
            'venue'      => 'required|string|max:255',
            'start_time' => 'required|date',
            'end_time'   => 'required|date|after:start_time',
            'notes'      => 'nullable|string',
        ]);

        $coach = $request->user()?->coach;

        if (!$coach) {
            return back()->withErrors(['coach' => 'No coach record']);
        }

        $teams = Team::query()
            ->forCoach($coach->id)
            ->get();

        if ($teams->isEmpty()) {
            return back()->withErrors(['team' => 'No team assigned']);
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

        $ownerTeam = $teams->firstWhere('id', $selectedTeamId);
        if (!$ownerTeam) {
            return back()->withErrors(['team_id' => 'Invalid team selection.']);
        }

        $tz = config('app.timezone');
        $created = TeamSchedule::create([
            'team_id'    => $ownerTeam->id,
            'title'      => $validated['title'],
            'type'       => $validated['type'],
            'venue'      => $validated['venue'],
            'start_time' => Carbon::parse($validated['start_time'], $tz)
                ->format('Y-m-d H:i:s'),
            'end_time'   => Carbon::parse($validated['end_time'], $tz)
                ->format('Y-m-d H:i:s'),
            'notes'      => $validated['notes'] ?? null,
        ]);

        $this->notifyScheduleChange($ownerTeam, Auth::id(), 'Schedule Created', sprintf(
            'New %s scheduled: %s, %s, %s.',
            strtolower((string) $created->type),
            $created->title,
            Carbon::parse($created->start_time)->format('M j, g:i A'),
            $created->venue
        ));

        return redirect()->route('coach.schedule.index', [
            'team_id' => $ownerTeam->id,
        ]);
    }
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'team_id' => 'nullable|integer',
            'title' => 'sometimes|string|max:255',
            'type' => 'sometimes|string|max:50',
            'venue' => 'sometimes|string|max:255',
            'start_time' => 'sometimes|date',
            'end_time' => 'sometimes|date|after:start_time',
            'notes' => 'nullable|string',
        ]);

        $schedule = TeamSchedule::findOrFail($id);
        $coach = $request->user()?->coach;
        abort_unless($coach, 403);

        $teamIds = Team::query()
            ->forCoach($coach->id)
            ->pluck('id')
            ->all();

        abort_unless(in_array($schedule->team_id, $teamIds, true), 403, 'Unauthorized schedule update.');
        $tz = config('app.timezone');
        $isLocked = Carbon::parse($schedule->end_time, $tz)->lt(Carbon::now($tz))
            && $schedule->attendances()->exists();
        abort_if($isLocked, 403, 'Completed schedules with attendance cannot be edited.');

        if (isset($validated['start_time'])) {
            $validated['start_time'] = Carbon::parse(
                $validated['start_time'],
                $tz
            )->format('Y-m-d H:i:s');
        }

        if (isset($validated['end_time'])) {
            $validated['end_time'] = Carbon::parse(
                $validated['end_time'],
                $tz
            )->format('Y-m-d H:i:s');
        }

        unset($validated['team_id']);
        $schedule->update($validated);

        $ownerTeam = Team::find($schedule->team_id);
        if (!$ownerTeam) {
            return redirect()->route('coach.schedule.index');
        }

        $this->notifyScheduleChange($ownerTeam, Auth::id(), 'Schedule Updated', sprintf(
            'Schedule updated: %s on %s at %s (%s).',
            $schedule->title,
            Carbon::parse($schedule->start_time)->format('M j, g:i A'),
            $schedule->venue,
            strtolower((string) $schedule->type)
        ));

        return redirect()->route('coach.schedule.index', [
            'team_id' => $ownerTeam->id,
        ]);
    }


    public function destroy($id)
    {
        $schedule = TeamSchedule::findOrFail($id);
        $coach = Auth::user()?->coach;
        abort_unless($coach, 403);

        $teamIds = Team::query()
            ->forCoach($coach->id)
            ->pluck('id')
            ->all();

        abort_unless(in_array($schedule->team_id, $teamIds, true), 403, 'Unauthorized schedule delete.');
        $tz = config('app.timezone');
        $isLocked = Carbon::parse($schedule->end_time, $tz)->lt(Carbon::now($tz))
            && $schedule->attendances()->exists();
        abort_if($isLocked, 403, 'Completed schedules with attendance cannot be deleted.');

        $ownerTeam = Team::find($schedule->team_id);
        if (!$ownerTeam) {
            return redirect()->route('coach.schedule.index');
        }

        $title = $schedule->title;
        $type = strtolower((string) $schedule->type);
        $start = Carbon::parse($schedule->start_time)->format('M j, g:i A');
        $venue = (string) $schedule->venue;
        $schedule->delete();

        $this->notifyScheduleChange(
            $ownerTeam,
            Auth::id(),
            'Schedule Cancelled',
            "Schedule cancelled: {$title} ({$type}) on {$start} at {$venue}."
        );

        return redirect()->route('coach.schedule.index', [
            'team_id' => $ownerTeam->id,
        ]);
    }

    public function print(Request $request)
    {
        $coach = $request->user()?->coach;
        abort_unless($coach, 403);

        $teamIds = Team::query()
            ->forCoach($coach->id)
            ->pluck('id')
            ->all();
        abort_unless(!empty($teamIds), 403);

        $validated = $request->validate([
            'team_id' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $selectedTeamId = (int) ($validated['team_id'] ?? 0);
        if (!$selectedTeamId || !in_array($selectedTeamId, $teamIds, true)) {
            $selectedTeamId = $teamIds[0];
        }

        $team = Team::with('sport')->findOrFail($selectedTeamId);

        $query = TeamSchedule::query()->where('team_id', $selectedTeamId);
        if (!empty($validated['start_date'])) {
            $query->whereDate('start_time', '>=', $validated['start_date']);
        }
        if (!empty($validated['end_date'])) {
            $query->whereDate('start_time', '<=', $validated['end_date']);
        }

        $schedules = $query->orderBy('start_time')->get()->map(function ($schedule) {
            return [
                'title' => $schedule->title,
                'type' => $schedule->type,
                'venue' => $schedule->venue,
                'start' => optional($schedule->start_time)->format('M j, Y g:i A'),
                'end' => optional($schedule->end_time)->format('M j, Y g:i A'),
                'notes' => $schedule->notes,
            ];
        })->values();

        $rangeLabel = (!empty($validated['start_date']) || !empty($validated['end_date']))
            ? trim(($validated['start_date'] ?? '...') . ' to ' . ($validated['end_date'] ?? '...'))
            : 'All Dates';

        return view('print.coach-schedule', [
            'team' => [
                'team_name' => $team->team_name,
                'sport' => $team->sport?->name ?? 'Unknown',
            ],
            'schedules' => $schedules,
            'rangeLabel' => $rangeLabel,
            'generatedAt' => now()->format('M j, Y g:i A'),
        ]);
    }

    private function notifyScheduleChange(Team $team, int $actorUserId, string $title, string $message): void
    {
        $studentIds = TeamPlayer::where('team_id', $team->id)->pluck('student_id');
        $studentUserIds = Student::whereIn('id', $studentIds)->pluck('user_id')->filter()->all();

        $coachUserIds = Coach::whereIn('id', array_filter([(int) $team->coach_id, (int) $team->assistant_coach_id]))
            ->pluck('user_id')
            ->filter()
            ->reject(fn ($id) => (int) $id === $actorUserId)
            ->all();

        $recipientIds = array_merge($studentUserIds, $coachUserIds);
        $this->announcements->announceMany(
            $recipientIds,
            $title,
            $message,
            'schedule',
            $actorUserId,
            'notify_schedule_changes'
        );
    }
}
