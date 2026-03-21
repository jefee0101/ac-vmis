<?php

namespace App\Http\Controllers\StudentAthlete;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Team;
use App\Models\Student;
use App\Models\TeamSchedule;
use App\Models\ScheduleAttendance;
use App\Models\ScheduleQrToken;
use App\Services\AnnouncementService;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class ScheduleRecord extends Controller
{
    public function __construct(private AnnouncementService $announcements)
    {
    }

    public function mySchedules(Request $request)
    {
        $student = Student::where('user_id', Auth::id())->first();

        if (!$student) {
            return Inertia::render('StudentAthletes/MySchedules', [
                'team' => null,
                'teams' => [],
                'selectedTeamId' => null,
                'schedules' => [],
            ]);
        }

        $teams = Team::with('sport')
            ->whereHas('players', function ($q) use ($student) {
                $q->where('student_id', $student->id);
            })
            ->orderBy('team_name')
            ->get();

        if ($teams->isEmpty()) {
            return Inertia::render('StudentAthletes/MySchedules', [
                'team' => null,
                'teams' => [],
                'selectedTeamId' => null,
                'schedules' => [],
            ]);
        }

        $teamIds = $teams->pluck('id')->all();
        $selectedTeamId = (int) $request->query('team_id', 0);
        if (!$selectedTeamId || !in_array($selectedTeamId, $teamIds, true)) {
            $selectedTeamId = $teamIds[0];
        }

        $team = $teams->firstWhere('id', $selectedTeamId);
        if (!$team) {
            return Inertia::render('StudentAthletes/MySchedules', [
                'team' => null,
                'teams' => [],
                'selectedTeamId' => null,
                'schedules' => [],
            ]);
        }

        $attendanceBySchedule = ScheduleAttendance::where('student_id', $student->id)
            ->get()
            ->keyBy('schedule_id');

        $schedules = TeamSchedule::where('team_id', $team->id)
            ->orderBy('start_time')
            ->get()
            ->map(function ($schedule) use ($attendanceBySchedule, $team) {
                $attendance = $attendanceBySchedule->get($schedule->id);

                return [
                    'id' => $schedule->id,
                    'title' => $schedule->title,
                    'type' => $schedule->type,
                    'venue' => $schedule->venue,
                    'notes' => $schedule->notes,
                    'sport' => $team->sport?->name ?? $team->sport_id ?? 'unknown',
                    'start' => Carbon::parse($schedule->start_time)->toIso8601String(),
                    'end' => Carbon::parse($schedule->end_time)->toIso8601String(),
                    'attendance_status' => optional($attendance)->status,
                    'attendance_notes' => optional($attendance)->notes,
                ];
            });

        return Inertia::render('StudentAthletes/MySchedules', [
            'team' => [
                'id' => $team->id,
                'team_name' => $team->team_name,
                'sport' => $team->sport?->name ?? $team->sport_id ?? 'unknown',
            ],
            'teams' => $teams->map(function (Team $team) {
                return [
                    'id' => $team->id,
                    'team_name' => $team->team_name,
                    'sport' => $team->sport?->name ?? $team->sport_id ?? 'unknown',
                ];
            })->values(),
            'selectedTeamId' => $selectedTeamId,
            'schedules' => $schedules,
        ]);
    }

    public function print(Request $request)
    {
        $student = Student::where('user_id', Auth::id())->first();
        abort_unless($student, 403);

        $teams = Team::with('sport')
            ->whereHas('players', function ($q) use ($student) {
                $q->where('student_id', $student->id);
            })
            ->orderBy('team_name')
            ->get();

        abort_unless($teams->isNotEmpty(), 403);

        $teamIds = $teams->pluck('id')->all();
        $selectedTeamId = (int) $request->query('team_id', 0);
        if (!$selectedTeamId || !in_array($selectedTeamId, $teamIds, true)) {
            $selectedTeamId = $teamIds[0];
        }

        $team = $teams->firstWhere('id', $selectedTeamId);
        abort_unless($team, 404);

        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $attendanceBySchedule = ScheduleAttendance::where('student_id', $student->id)
            ->get()
            ->keyBy('schedule_id');

        $query = TeamSchedule::query()->where('team_id', $team->id);
        if (!empty($validated['start_date'])) {
            $query->whereDate('start_time', '>=', $validated['start_date']);
        }
        if (!empty($validated['end_date'])) {
            $query->whereDate('start_time', '<=', $validated['end_date']);
        }

        $schedules = $query->orderBy('start_time')->get()->map(function ($schedule) use ($attendanceBySchedule) {
            $attendance = $attendanceBySchedule->get($schedule->id);

            return [
                'title' => $schedule->title,
                'type' => $schedule->type,
                'venue' => $schedule->venue,
                'start' => optional($schedule->start_time)->format('M j, Y g:i A'),
                'end' => optional($schedule->end_time)->format('M j, Y g:i A'),
                'attendance_status' => $attendance?->status,
                'attendance_notes' => $attendance?->notes,
            ];
        })->values();

        $rangeLabel = (!empty($validated['start_date']) || !empty($validated['end_date']))
            ? trim(($validated['start_date'] ?? '...') . ' to ' . ($validated['end_date'] ?? '...'))
            : 'All Dates';

        return view('print.student-schedule', [
            'student' => [
                'name' => trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')),
                'student_id_number' => $student->student_id_number,
            ],
            'team' => [
                'team_name' => $team->team_name,
            ],
            'schedules' => $schedules,
            'rangeLabel' => $rangeLabel,
            'generatedAt' => now()->format('M j, Y g:i A'),
        ]);
    }

    public function updateScheduleAttendance(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:present,absent,excused',
            'notes' => 'nullable|string|max:1000',
        ]);

        if (in_array($validated['status'], ['absent', 'excused'], true) && empty(trim($validated['notes'] ?? ''))) {
            return back()->withErrors([
                'notes' => 'Please provide a reason for this status.',
            ]);
        }

        $student = Student::where('user_id', Auth::id())->firstOrFail();
        $schedule = TeamSchedule::findOrFail($id);

        $teamIds = Team::whereHas('players', function ($q) use ($student) {
            $q->where('student_id', $student->id);
        })->pluck('id')->all();

        abort_unless(in_array($schedule->team_id, $teamIds, true), 403, 'Unauthorized schedule attendance update.');

        $window = $this->checkinWindow($schedule);
        abort_unless($window['is_open'], 422, "Attendance updates are closed. {$window['reason']}");

        $existing = ScheduleAttendance::query()
            ->where('schedule_id', $schedule->id)
            ->where('student_id', $student->id)
            ->first();
        $previousStatus = $existing?->status;

        $attendancePayload = [
            'status' => $validated['status'],
            'recorded_by' => Auth::id(),
            'recorded_at' => now(),
            'notes' => $validated['notes'] ?? null,
        ];

        // Backward-compatible: if QR migration columns are not yet present,
        // keep base attendance recording functional.
        if (Schema::hasColumn('schedule_attendances', 'verification_method')) {
            $attendancePayload['verification_method'] = 'self_response';
        }

        $attendance = ScheduleAttendance::updateOrCreate(
            [
                'schedule_id' => $schedule->id,
                'student_id' => $student->id,
            ],
            $attendancePayload
        );

        if ($previousStatus !== $attendance->status) {
            $status = strtoupper((string) $attendance->status);
            $this->announcements->announce(
                Auth::id(),
                'Attendance Status Updated',
                sprintf(
                    'Attendance marked: %s for %s (%s).',
                    $status,
                    $schedule->title,
                    Carbon::parse($schedule->start_time)->format('M j')
                ),
                'schedule',
                Auth::id()
            );
        }

        return back();
    }

    public function qrToken(Request $request, $id)
    {
        $student = Student::where('user_id', Auth::id())->firstOrFail();
        $schedule = TeamSchedule::findOrFail($id);

        $team = Team::whereHas('players', function ($q) use ($student) {
            $q->where('student_id', $student->id);
        })->first();

        abort_unless($team && $schedule->team_id === $team->id, 403, 'Unauthorized schedule QR request.');
        $window = $this->checkinWindow($schedule);
        abort_unless($window['is_open'], 422, "QR check-in is unavailable. {$window['reason']}");

        $rotation = max(20, (int) ($schedule->qr_rotation_seconds ?? 25));
        $expiresAt = min(
            now()->copy()->addSeconds($rotation),
            $window['closes_at']
        );

        $rawToken = Str::random(48);
        $token = ScheduleQrToken::create([
            'schedule_id' => $schedule->id,
            'student_id' => $student->id,
            'token_hash' => hash('sha256', $rawToken),
            'issued_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        return response()->json([
            'token' => $rawToken,
            'expires_at' => $token->expires_at?->toIso8601String(),
            'rotation_seconds' => $rotation,
            'window_closes_at' => $window['closes_at']->toIso8601String(),
        ]);
    }

    private function checkinWindow(TeamSchedule $schedule): array
    {
        $tz = config('app.timezone');
        $startsAt = Carbon::parse($schedule->start_time, $tz);
        $windowMinutes = max(1, (int) ($schedule->qr_window_minutes ?? 20));
        $closesAt = $startsAt->copy()->addMinutes($windowMinutes);
        $now = now($tz);

        if ($now->lt($startsAt)) {
            return [
                'is_open' => false,
                'reason' => 'Check-in starts when the schedule starts.',
                'closes_at' => $closesAt,
            ];
        }

        if ($now->gt($closesAt)) {
            return [
                'is_open' => false,
                'reason' => 'Check-in window already closed.',
                'closes_at' => $closesAt,
            ];
        }

        return [
            'is_open' => true,
            'reason' => null,
            'closes_at' => $closesAt,
        ];
    }
}
