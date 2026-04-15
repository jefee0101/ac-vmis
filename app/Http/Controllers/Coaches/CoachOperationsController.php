<?php

namespace App\Http\Controllers\Coaches;

use App\Http\Controllers\Controller;
use App\Models\ScheduleAttendance;
use App\Models\Team;
use App\Models\TeamSchedule;
use App\Models\TeamPlayer;
use App\Models\WellnessLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CoachOperationsController extends Controller
{
    public function index(Request $request)
    {
        $tab = (string) $request->query('tab', 'attendance');
        if (!in_array($tab, ['attendance', 'wellness'], true)) {
            $tab = 'attendance';
        }

        $coach = $request->user()?->coach;
        if (!$coach) {
            return Inertia::render('Coaches/CoachOperations', [
                'activeTab' => $tab,
                'teams' => [],
                'selectedTeamId' => null,
                'team' => null,
                'attendanceSchedules' => [],
                'selectedAttendanceScheduleId' => null,
                'attendanceRows' => [],
                'wellnessSchedules' => [],
                'selectedWellnessScheduleId' => null,
                'wellnessAthletes' => [],
            ]);
        }

        $teams = Team::with(['sport', 'players.student'])
            ->forCoach($coach->id)
            ->orderBy('team_name')
            ->get();

        if ($teams->isEmpty()) {
            return Inertia::render('Coaches/CoachOperations', [
                'activeTab' => $tab,
                'teams' => [],
                'selectedTeamId' => null,
                'team' => null,
                'attendanceSchedules' => [],
                'selectedAttendanceScheduleId' => null,
                'attendanceRows' => [],
                'wellnessSchedules' => [],
                'selectedWellnessScheduleId' => null,
                'wellnessAthletes' => [],
            ]);
        }

        $teamIds = $teams->pluck('id')->all();
        $selectedTeamId = (int) $request->query('team_id', 0);
        if (!$selectedTeamId || !in_array($selectedTeamId, $teamIds, true)) {
            $selectedTeamId = $teamIds[0];
        }
        $ownerTeam = $teams->firstWhere('id', $selectedTeamId);

        if (!$ownerTeam) {
            return Inertia::render('Coaches/CoachOperations', [
                'activeTab' => $tab,
                'teams' => [],
                'selectedTeamId' => null,
                'team' => null,
                'attendanceSchedules' => [],
                'selectedAttendanceScheduleId' => null,
                'attendanceRows' => [],
                'wellnessSchedules' => [],
                'selectedWellnessScheduleId' => null,
                'wellnessAthletes' => [],
            ]);
        }

        $attendanceSchedules = TeamSchedule::where('team_id', $ownerTeam->id)
            ->orderBy('start_time')
            ->get()
            ->filter(function ($schedule) {
                if (!$schedule->end_time) return false;
                return Carbon::parse($schedule->end_time)->lt(now());
            })
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

        $selectedAttendanceScheduleId = (int) $request->query('attendance_schedule_id', 0);
        $availableAttendanceScheduleIds = $attendanceSchedules->pluck('id')->all();
        if (!$selectedAttendanceScheduleId || !in_array($selectedAttendanceScheduleId, $availableAttendanceScheduleIds, true)) {
            $selectedAttendanceScheduleId = $attendanceSchedules->first()['id'] ?? null;
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
        if ($selectedAttendanceScheduleId) {
            $attendanceByStudent = ScheduleAttendance::where('schedule_id', $selectedAttendanceScheduleId)
                ->whereIn('student_id', $players->pluck('student_id')->all())
                ->get()
                ->keyBy('student_id');
        }

        $wellnessByStudent = collect();
        if ($selectedAttendanceScheduleId) {
            $wellnessByStudent = WellnessLog::query()
                ->where('schedule_id', $selectedAttendanceScheduleId)
                ->get()
                ->keyBy('student_id');
        }

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

        $wellnessSchedules = TeamSchedule::query()
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

        $selectedWellnessScheduleId = (int) $request->query('wellness_schedule_id', 0);
        $allowedWellnessIds = $wellnessSchedules->pluck('id')->all();
        if (!$selectedWellnessScheduleId || !in_array($selectedWellnessScheduleId, $allowedWellnessIds, true)) {
            $selectedWellnessScheduleId = $wellnessSchedules->first()['id'] ?? null;
        }

        $wellnessAthletes = collect();
        if ($selectedWellnessScheduleId) {
            $attendanceRowsForWellness = ScheduleAttendance::query()
                ->with('student')
                ->where('schedule_id', $selectedWellnessScheduleId)
                ->whereIn('status', ['present', 'late', 'excused'])
                ->get();

            $wellnessByStudent = WellnessLog::query()
                ->where('schedule_id', $selectedWellnessScheduleId)
                ->get()
                ->keyBy('student_id');

            $wellnessAthletes = $attendanceRowsForWellness
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

        return Inertia::render('Coaches/CoachOperations', [
            'activeTab' => $tab,
            'teams' => $teams->map(function (Team $team) {
                return [
                    'id' => $team->id,
                    'team_name' => $team->team_name,
                    'sport' => $team->sport?->name ?? $team->sport_id ?? 'unknown',
                ];
            })->values(),
            'selectedTeamId' => $selectedTeamId,
            'team' => [
                'id' => $ownerTeam->id,
                'team_name' => $ownerTeam->team_name,
                'sport' => $ownerTeam->sport?->name ?? $ownerTeam->sport_id ?? 'unknown',
            ],
            'attendanceSchedules' => $attendanceSchedules,
            'selectedAttendanceScheduleId' => $selectedAttendanceScheduleId,
            'attendanceRows' => $attendanceRows,
            'attendancePagination' => [
                'current_page' => $playersPaginator->currentPage(),
                'last_page' => $playersPaginator->lastPage(),
                'per_page' => $playersPaginator->perPage(),
                'total' => $playersPaginator->total(),
            ],
            'wellnessSchedules' => $wellnessSchedules,
            'selectedWellnessScheduleId' => $selectedWellnessScheduleId,
            'wellnessAthletes' => $wellnessAthletes,
        ]);
    }
}
