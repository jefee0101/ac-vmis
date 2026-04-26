<?php

namespace App\Http\Controllers\Coaches;

use App\Http\Controllers\Controller;
use App\Models\ScheduleAttendance;
use App\Models\Team;
use App\Models\TeamSchedule;
use App\Models\Student;
use App\Models\User;
use App\Models\WellnessLog;
use App\Services\SystemNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class WellnessMonitoringController extends Controller
{
    public function __construct(private SystemNotificationService $notifications)
    {
    }

    public function index(Request $request)
    {
        $coach = $request->user()?->coach;

        if (!$coach) {
            return Inertia::render('Coaches/WellnessMonitoring', [
                'team' => null,
                'schedules' => [],
                'selectedScheduleId' => null,
                'athletes' => [],
            ]);
        }

        $ownerTeam = Team::with(['sport'])
            ->forCoach($coach->id)
            ->first();

        if (!$ownerTeam) {
            return Inertia::render('Coaches/WellnessMonitoring', [
                'team' => null,
                'schedules' => [],
                'selectedScheduleId' => null,
                'athletes' => [],
            ]);
        }

        $schedules = TeamSchedule::query()
            ->where('team_id', $ownerTeam->id)
            ->whereIn('type', ['practice', 'game'])
            ->where('end_time', '<=', now())
            ->orderByDesc('start_time')
            ->get()
            ->map(function ($schedule) {
                return [
                    'id' => $schedule->id,
                    'title' => $schedule->title,
                    'type' => $schedule->type,
                    'venue' => $schedule->venue,
                    'start' => Carbon::parse($schedule->start_time)->toIso8601String(),
                    'end' => Carbon::parse($schedule->end_time)->toIso8601String(),
                ];
            })->values();

        $selectedScheduleId = (int) $request->query('schedule_id', 0);
        $allowedIds = $schedules->pluck('id')->all();
        if (!$selectedScheduleId || !in_array($selectedScheduleId, $allowedIds, true)) {
            $selectedScheduleId = $schedules->first()['id'] ?? null;
        }

        $athletes = collect();
        if ($selectedScheduleId) {
            $attendanceRows = ScheduleAttendance::query()
                ->with('student')
                ->where('schedule_id', $selectedScheduleId)
                ->whereIn('status', ['present', 'late', 'excused'])
                ->get();

            $wellnessByStudent = WellnessLog::query()
                ->where('schedule_id', $selectedScheduleId)
                ->get()
                ->keyBy('student_id');

            $athletes = $attendanceRows
                ->filter(fn ($row) => $row->student !== null)
                ->map(function ($row) use ($wellnessByStudent) {
                    $student = $row->student;
                    $log = $wellnessByStudent->get($student->id);

                    return [
                        'student_id' => $student->id,
                        'student_id_number' => $student->student_id_number,
                        'name' => trim(($student->last_name ?? '') . ', ' . ($student->first_name ?? '')),
                        'attendance_status' => $row->status,
                        'wellness' => [
                            'injury_observed' => (bool) ($log->injury_observed ?? false),
                            'injury_notes' => $log?->injury_notes,
                            'fatigue_level' => $log?->fatigue_level,
                            'performance_condition' => $log?->performance_condition,
                            'remarks' => $log?->remarks,
                            'log_id' => $log->id ?? null,
                        ],
                    ];
                })
                ->sortBy('name')
                ->values();
        }

        return Inertia::render('Coaches/WellnessMonitoring', [
            'team' => [
                'id' => $ownerTeam->id,
                'team_name' => $ownerTeam->team_name,
                'sport' => $ownerTeam->sport?->name ?? $ownerTeam->sport_id ?? 'unknown',
            ],
            'schedules' => $schedules,
            'selectedScheduleId' => $selectedScheduleId,
            'athletes' => $athletes,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'schedule_id' => 'required|exists:team_schedules,id',
            'student_id' => 'required|exists:students,id',
            'injury_observed' => 'required|boolean',
            'injury_notes' => 'nullable|string',
            'fatigue_level' => 'required|integer|min:1|max:5',
            'performance_condition' => 'required|in:excellent,good,fair,poor',
            'remarks' => 'nullable|string',
        ]);

        $coach = $request->user()?->coach;
        abort_unless($coach, 403);
        $schedule = TeamSchedule::findOrFail((int) $validated['schedule_id']);

        abort_unless(
            $schedule->type !== 'meeting',
            422,
            'Wellness monitoring is only for practice/game schedules.'
        );

        $ownerTeam = Team::query()
            ->forCoach($coach->id)
            ->first();

        abort_unless($ownerTeam && $schedule->team_id === $ownerTeam->id, 403, 'Unauthorized schedule.');

        $attended = ScheduleAttendance::query()
            ->where('schedule_id', $schedule->id)
            ->where('student_id', (int) $validated['student_id'])
            ->whereIn('status', ['present', 'late', 'excused'])
            ->exists();

        abort_unless($attended, 422, 'Only attended athletes can be logged for wellness.');

        $wellness = WellnessLog::updateOrCreate(
            [
                'schedule_id' => $schedule->id,
                'student_id' => (int) $validated['student_id'],
            ],
            [
                'logged_by' => Auth::id(),
                'log_date' => Carbon::now()->toDateString(),
                'injury_observed' => (bool) $validated['injury_observed'],
                'injury_notes' => (bool) $validated['injury_observed']
                    ? ($validated['injury_notes'] ?? null)
                    : null,
                'fatigue_level' => (int) $validated['fatigue_level'],
                'performance_condition' => $validated['performance_condition'],
                'remarks' => $validated['remarks'] ?? null,
            ]
        );

        $studentUserId = Student::where('id', (int) $validated['student_id'])->value('user_id');
        if ($studentUserId) {
            $this->notifications->announce(
                (int) $studentUserId,
                'Wellness Log Recorded',
                sprintf(
                    'Wellness entry recorded after %s (%s). Fatigue level: %d/5.',
                    $schedule->title,
                    strtolower((string) $schedule->type),
                    (int) $wellness->fatigue_level
                ),
                'general',
                Auth::id(),
                'notify_wellness_alerts'
            );
        }

        if ((bool) $wellness->injury_observed) {
            $adminUserIds = User::query()
                ->where('account_state', 'active')
                ->where('role', 'admin')
                ->pluck('id')
                ->all();

            $this->notifications->announceMany(
                $adminUserIds,
                'Injury Observation Alert',
                "Injury was observed for a student in {$schedule->title}. Please review wellness monitoring.",
                'system',
                Auth::id(),
                'notify_wellness_alerts'
            );
        }

        return back()->with('success', 'Wellness log saved.');
    }
}
