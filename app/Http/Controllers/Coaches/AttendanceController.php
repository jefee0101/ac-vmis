<?php

namespace App\Http\Controllers\Coaches;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Team;
use App\Models\TeamSchedule;
use App\Models\TeamPlayer;
use App\Models\ScheduleAttendance;
use App\Models\ScheduleQrToken;
use App\Models\WellnessLog;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    private function buildAttendanceRows(Team $team, int $scheduleId, bool $includeStatus): array
    {
        $players = TeamPlayer::query()
            ->with('student')
            ->where('team_id', $team->id)
            ->join('students', 'team_players.student_id', '=', 'students.id')
            ->join('users as su', 'su.id', '=', 'students.user_id')
            ->orderBy('su.last_name')
            ->orderBy('su.first_name')
            ->select('team_players.*')
            ->get();

        $attendanceByStudent = collect();
        if ($includeStatus) {
            $attendanceByStudent = ScheduleAttendance::query()
                ->where('schedule_id', $scheduleId)
                ->whereIn('student_id', $players->pluck('student_id')->all())
                ->get()
                ->keyBy('student_id');
        }

        return $players->map(function ($player) use ($attendanceByStudent) {
            $student = $player->student;
            $attendance = $attendanceByStudent->get($student?->id);

            return [
                'name' => trim(($student?->first_name ?? '') . ' ' . ($student?->last_name ?? '')),
                'student_id_number' => $student?->student_id_number,
                'jersey_number' => $player->jersey_number,
                'athlete_position' => $player->athlete_position,
                'status' => $attendance?->status,
                'notes' => $attendance?->notes,
            ];
        })->values()->all();
    }
    public function index(Request $request)
    {
        $coach = $request->user()?->coach;

        if (!$coach) {
            return Inertia::render('Coaches/AttendanceRecord', [
                'team' => null,
                'schedules' => [],
                'selectedScheduleId' => null,
                'attendanceRows' => [],
            ]);
        }

        $teamIds = Team::where('coach_id', $coach->id)
            ->orWhere('assistant_coach_id', $coach->id)
            ->pluck('id')
            ->all();

        if (empty($teamIds)) {
            return Inertia::render('Coaches/AttendanceRecord', [
                'team' => null,
                'schedules' => [],
                'selectedScheduleId' => null,
                'attendanceRows' => [],
            ]);
        }

        $selectedTeamId = (int) $request->query('team_id', 0);
        if (!$selectedTeamId || !in_array($selectedTeamId, $teamIds, true)) {
            $selectedTeamId = $teamIds[0];
        }

        $ownerTeam = Team::with(['sport', 'players.student'])->find($selectedTeamId);
        if (!$ownerTeam) {
            return Inertia::render('Coaches/AttendanceRecord', [
                'team' => null,
                'schedules' => [],
                'selectedScheduleId' => null,
                'attendanceRows' => [],
            ]);
        }

        $schedules = TeamSchedule::where('team_id', $ownerTeam->id)
            ->orderBy('start_time')
            ->get()
            ->map(function ($schedule) {
                $windowMinutes = max(1, (int) ($schedule->qr_window_minutes ?? 20));
                $qrClosesAt = Carbon::parse($schedule->start_time)->addMinutes($windowMinutes);
                return [
                    'id' => $schedule->id,
                    'title' => $schedule->title,
                    'type' => $schedule->type,
                    'venue' => $schedule->venue,
                    'start' => Carbon::parse($schedule->start_time)->toIso8601String(),
                    'end' => Carbon::parse($schedule->end_time)->toIso8601String(),
                    'qr_closes_at' => $qrClosesAt->toIso8601String(),
                    'is_qr_open' => now()->between(
                        Carbon::parse($schedule->start_time),
                        $qrClosesAt
                    ),
                ];
            })->values();

        $selectedScheduleId = (int) $request->query('schedule_id', 0);
        $availableScheduleIds = $schedules->pluck('id')->all();

        if (!$selectedScheduleId || !in_array($selectedScheduleId, $availableScheduleIds, true)) {
            $selectedScheduleId = $schedules->first()['id'] ?? null;
        }

        $playersPaginator = TeamPlayer::query()
            ->with('student')
            ->where('team_id', $ownerTeam->id)
            ->join('students', 'team_players.student_id', '=', 'students.id')
            ->join('users as su', 'su.id', '=', 'students.user_id')
            ->orderBy('su.last_name')
            ->orderBy('su.first_name')
            ->select('team_players.*')
            ->paginate(50, ['*'], 'attendance_page');

        $players = $playersPaginator->getCollection()
            ->filter(fn ($player) => $player->student !== null)
            ->values();

        $attendanceByStudent = collect();
        if ($selectedScheduleId) {
            $attendanceByStudent = ScheduleAttendance::where('schedule_id', $selectedScheduleId)
                ->whereIn('student_id', $players->pluck('student_id')->all())
                ->get()
                ->keyBy('student_id');
        }

        $attendanceRows = $players->map(function ($player) use ($attendanceByStudent) {
            $student = $player->student;
            $attendance = $attendanceByStudent->get($student->id);

            return [
                'student_id' => $student->id,
                'student_id_number' => $student->student_id_number,
                'full_name' => trim(
                    ($student->last_name ?? '')
                    . ', '
                    . ($student->first_name ?? '')
                    . (!empty($student->middle_name) ? ' ' . $student->middle_name : '')
                ),
                'jersey_number' => $player->jersey_number,
                'athlete_position' => $player->athlete_position,
                'attendance_status' => optional($attendance)->status,
                'verification_method' => optional($attendance)->verification_method,
                'attendance_notes' => optional($attendance)->notes,
                'recorded_at' => $attendance?->recorded_at?->toIso8601String(),
            ];
        })->values();

        return Inertia::render('Coaches/AttendanceRecord', [
            'team' => [
                'id' => $ownerTeam->id,
                'team_name' => $ownerTeam->team_name,
                'sport' => $ownerTeam->sport?->name ?? $ownerTeam->sport_id ?? 'unknown',
            ],
            'schedules' => $schedules,
            'selectedScheduleId' => $selectedScheduleId,
            'attendanceRows' => $attendanceRows,
            'attendancePagination' => [
                'current_page' => $playersPaginator->currentPage(),
                'last_page' => $playersPaginator->lastPage(),
                'per_page' => $playersPaginator->perPage(),
                'total' => $playersPaginator->total(),
            ],
        ]);
    }

    public function show(TeamSchedule $schedule)
    {
        $coach = request()->user()?->coach;

        if (!$coach) {
            return Inertia::render('Coaches/AttendanceRecord', [
                'mode' => 'detail',
                'team' => null,
                'schedules' => [],
                'selectedScheduleId' => null,
                'attendanceRows' => [],
            ]);
        }

        $teamIds = Team::where('coach_id', $coach->id)
            ->orWhere('assistant_coach_id', $coach->id)
            ->pluck('id')
            ->all();
        abort_unless(in_array($schedule->team_id, $teamIds, true), 403, 'Unauthorized schedule.');

        $ownerTeam = Team::with(['sport', 'players.student'])->find($schedule->team_id);
        if (!$ownerTeam) {
            return Inertia::render('Coaches/AttendanceRecord', [
                'mode' => 'detail',
                'team' => null,
                'schedules' => [],
                'selectedScheduleId' => null,
                'attendanceRows' => [],
            ]);
        }

        $windowMinutes = max(1, (int) ($schedule->qr_window_minutes ?? 20));
        $qrClosesAt = Carbon::parse($schedule->start_time)->addMinutes($windowMinutes);

        $schedules = collect([
            [
                'id' => $schedule->id,
                'title' => $schedule->title,
                'type' => $schedule->type,
                'venue' => $schedule->venue,
                'start' => Carbon::parse($schedule->start_time)->toIso8601String(),
                'end' => Carbon::parse($schedule->end_time)->toIso8601String(),
                'qr_closes_at' => $qrClosesAt->toIso8601String(),
                'is_qr_open' => now()->between(
                    Carbon::parse($schedule->start_time),
                    $qrClosesAt
                ),
            ],
        ])->values();

        $playersPaginator = TeamPlayer::query()
            ->with('student')
            ->where('team_id', $ownerTeam->id)
            ->join('students', 'team_players.student_id', '=', 'students.id')
            ->join('users as su', 'su.id', '=', 'students.user_id')
            ->orderBy('su.last_name')
            ->orderBy('su.first_name')
            ->select('team_players.*')
            ->paginate(50, ['*'], 'attendance_page');

        $players = $playersPaginator->getCollection()
            ->filter(fn ($player) => $player->student !== null)
            ->values();

        $attendanceByStudent = ScheduleAttendance::where('schedule_id', $schedule->id)
            ->whereIn('student_id', $players->pluck('student_id')->all())
            ->get()
            ->keyBy('student_id');

        $wellnessByStudent = WellnessLog::query()
            ->where('schedule_id', $schedule->id)
            ->get()
            ->keyBy('student_id');

        $attendanceRows = $players->map(function ($player) use ($attendanceByStudent, $wellnessByStudent) {
            $student = $player->student;
            $attendance = $attendanceByStudent->get($student->id);
            $log = $wellnessByStudent->get($student->id);

            return [
                'student_id' => $student->id,
                'student_id_number' => $student->student_id_number,
                'full_name' => trim(
                    ($student->last_name ?? '')
                    . ', '
                    . ($student->first_name ?? '')
                    . (!empty($student->middle_name) ? ' ' . $student->middle_name : '')
                ),
                'jersey_number' => $player->jersey_number,
                'athlete_position' => $player->athlete_position,
                'attendance_status' => optional($attendance)->status,
                'verification_method' => optional($attendance)->verification_method,
                'attendance_notes' => optional($attendance)->notes,
                'recorded_at' => $attendance?->recorded_at?->toIso8601String(),
                'wellness' => [
                    'injury_observed' => (bool) ($log->injury_observed ?? false),
                    'injury_notes' => $log?->injury_notes,
                    'fatigue_level' => $log?->fatigue_level,
                    'performance_condition' => $log?->performance_condition,
                    'remarks' => $log?->remarks,
                    'log_id' => $log->id ?? null,
                ],
            ];
        })->values();

        return Inertia::render('Coaches/AttendanceRecord', [
            'mode' => 'detail',
            'team' => [
                'id' => $ownerTeam->id,
                'team_name' => $ownerTeam->team_name,
                'sport' => $ownerTeam->sport?->name ?? $ownerTeam->sport_id ?? 'unknown',
            ],
            'schedules' => $schedules,
            'selectedScheduleId' => $schedule->id,
            'attendanceRows' => $attendanceRows,
            'attendancePagination' => [
                'current_page' => $playersPaginator->currentPage(),
                'last_page' => $playersPaginator->lastPage(),
                'per_page' => $playersPaginator->perPage(),
                'total' => $playersPaginator->total(),
            ],
        ]);
    }

    public function printSheet(Request $request, TeamSchedule $schedule)
    {
        $coach = $request->user()?->coach;
        abort_unless($coach, 403);

        $teamIds = Team::where('coach_id', $coach->id)
            ->orWhere('assistant_coach_id', $coach->id)
            ->pluck('id')
            ->all();
        abort_unless(in_array($schedule->team_id, $teamIds, true), 403, 'Unauthorized schedule.');

        $team = Team::with('sport')->findOrFail($schedule->team_id);
        $rows = $this->buildAttendanceRows($team, $schedule->id, true);

        return view('print.attendance-sheet', [
            'modeLabel' => 'Filled Sheet',
            'generatedAt' => now()->format('M j, Y g:i A'),
            'team' => [
                'team_name' => $team->team_name,
            ],
            'schedule' => [
                'title' => $schedule->title,
                'type' => $schedule->type,
                'venue' => $schedule->venue,
                'start' => optional($schedule->start_time)->format('M j, Y g:i A'),
                'end' => optional($schedule->end_time)->format('M j, Y g:i A'),
            ],
            'rows' => $rows,
        ]);
    }

    public function printBlankSheet(Request $request, TeamSchedule $schedule)
    {
        $coach = $request->user()?->coach;
        abort_unless($coach, 403);

        $teamIds = Team::where('coach_id', $coach->id)
            ->orWhere('assistant_coach_id', $coach->id)
            ->pluck('id')
            ->all();
        abort_unless(in_array($schedule->team_id, $teamIds, true), 403, 'Unauthorized schedule.');

        $team = Team::with('sport')->findOrFail($schedule->team_id);
        $rows = $this->buildAttendanceRows($team, $schedule->id, false);

        return view('print.attendance-sheet', [
            'modeLabel' => 'Blank Sheet',
            'generatedAt' => now()->format('M j, Y g:i A'),
            'team' => [
                'team_name' => $team->team_name,
            ],
            'schedule' => [
                'title' => $schedule->title,
                'type' => $schedule->type,
                'venue' => $schedule->venue,
                'start' => optional($schedule->start_time)->format('M j, Y g:i A'),
                'end' => optional($schedule->end_time)->format('M j, Y g:i A'),
            ],
            'rows' => $rows,
        ]);
    }

    public function verifyQrScan(Request $request, $scheduleId)
    {
        $validated = $request->validate([
            'token' => 'required|string|min:10',
        ]);

        $coach = $request->user()?->coach;
        abort_unless($coach, 403);
        $schedule = TeamSchedule::findOrFail((int) $scheduleId);
        $teamIds = Team::where('coach_id', $coach->id)
            ->orWhere('assistant_coach_id', $coach->id)
            ->pluck('id')
            ->all();
        abort_unless(in_array($schedule->team_id, $teamIds, true), 403, 'Unauthorized schedule.');
        $window = $this->checkinWindow($schedule);
        abort_unless($window['is_open'], 422, "QR check-in is unavailable. {$window['reason']}");

        $submitted = trim((string) $validated['token']);
        $decoded = json_decode($submitted, true);
        if (is_array($decoded) && !empty($decoded['token'])) {
            if (!empty($decoded['schedule_id']) && (int) $decoded['schedule_id'] !== (int) $schedule->id) {
                abort(422, 'QR token does not match this schedule.');
            }
            $submitted = (string) $decoded['token'];
        }

        $tokenHash = hash('sha256', $submitted);
        $qrToken = ScheduleQrToken::query()
            ->where('schedule_id', $schedule->id)
            ->where('token_hash', $tokenHash)
            ->whereNull('used_at')
            ->where('expires_at', '>=', now())
            ->latest('id')
            ->first();

        abort_unless($qrToken, 422, 'Invalid or expired QR token.');

        $isMember = Team::query()
            ->where('id', $schedule->team_id)
            ->whereHas('players', fn ($q) => $q->where('student_id', $qrToken->student_id))
            ->exists();
        abort_unless($isMember, 422, 'Student is not a member of this team.');

        ScheduleAttendance::updateOrCreate(
            [
                'schedule_id' => $schedule->id,
                'student_id' => $qrToken->student_id,
            ],
            [
                'status' => 'present',
                'verification_method' => 'qr_verified',
                'qr_token_id' => $qrToken->id,
                'recorded_by' => Auth::id(),
                'recorded_at' => now(),
                'verified_at' => now(),
                'notes' => null,
                'override_reason' => null,
            ]
        );

        $qrToken->update([
            'used_at' => now(),
            'used_by' => Auth::id(),
        ]);

        return back()->with('success', 'QR attendance verified.');
    }

    public function manualOverride(Request $request, $scheduleId, $studentId)
    {
        $validated = $request->validate([
            'status' => 'required|in:present,late,excused,absent',
            'reason' => 'required|string|max:500',
        ]);

        $coach = $request->user()?->coach;
        abort_unless($coach, 403);
        $schedule = TeamSchedule::findOrFail((int) $scheduleId);
        $teamIds = Team::where('coach_id', $coach->id)
            ->orWhere('assistant_coach_id', $coach->id)
            ->pluck('id')
            ->all();
        abort_unless(in_array($schedule->team_id, $teamIds, true), 403, 'Unauthorized schedule.');

        $isMember = Team::query()
            ->where('id', $schedule->team_id)
            ->whereHas('players', fn ($q) => $q->where('student_id', (int) $studentId))
            ->exists();
        abort_unless($isMember, 422, 'Student is not a member of this team.');

        ScheduleAttendance::updateOrCreate(
            [
                'schedule_id' => $schedule->id,
                'student_id' => (int) $studentId,
            ],
            [
                'status' => $validated['status'],
                'verification_method' => 'manual_override',
                'recorded_by' => Auth::id(),
                'recorded_at' => now(),
                'verified_at' => now(),
                'notes' => $validated['reason'],
                'override_reason' => $validated['reason'],
                'qr_token_id' => null,
            ]
        );

        return back()->with('success', 'Manual attendance override saved.');
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
            ];
        }

        if ($now->gt($closesAt)) {
            return [
                'is_open' => false,
                'reason' => 'Check-in window already closed.',
            ];
        }

        return [
            'is_open' => true,
            'reason' => null,
        ];
    }
}
