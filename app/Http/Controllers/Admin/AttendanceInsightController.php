<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coach;
use App\Models\Sport;
use App\Models\Team;
use App\Models\TeamSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AttendanceInsightController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'sport_id' => 'nullable|integer|exists:sports,id',
            'team_id' => 'nullable|integer|exists:teams,id',
            'coach_id' => 'nullable|integer|exists:coaches,id',
            'schedule_type' => 'nullable|string|max:50',
            'status' => 'nullable|in:present,absent,late,excused,no_response',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $selected = [
            'sport_id' => $validated['sport_id'] ?? null,
            'team_id' => $validated['team_id'] ?? null,
            'coach_id' => $validated['coach_id'] ?? null,
            'schedule_type' => $validated['schedule_type'] ?? null,
            'status' => $validated['status'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
        ];

        $scheduleQuery = TeamSchedule::with([
            'team.sport',
            'team.coach',
            'team.assistantCoach',
            'team.players.student',
            'attendances',
        ])->orderBy('start_time');

        if (!empty($selected['sport_id'])) {
            $sportId = (int) $selected['sport_id'];
            $scheduleQuery->whereHas('team', fn ($q) => $q->where('sport_id', $sportId));
        }

        if (!empty($selected['team_id'])) {
            $scheduleQuery->where('team_id', (int) $selected['team_id']);
        }

        if (!empty($selected['coach_id'])) {
            $coachId = (int) $selected['coach_id'];
            $scheduleQuery->whereHas('team', function ($q) use ($coachId) {
                $q->where('coach_id', $coachId)
                    ->orWhere('assistant_coach_id', $coachId);
            });
        }

        if (!empty($selected['schedule_type'])) {
            $scheduleQuery->where('type', $selected['schedule_type']);
        }

        if (!empty($selected['start_date'])) {
            $scheduleQuery->where('start_time', '>=', Carbon::parse($selected['start_date'])->startOfDay());
        }

        if (!empty($selected['end_date'])) {
            $scheduleQuery->where('start_time', '<=', Carbon::parse($selected['end_date'])->endOfDay());
        }

        $schedules = $scheduleQuery->get();
        $statusFilter = $selected['status'] ?? null;

        $rows = collect();
        foreach ($schedules as $schedule) {
            if (!$schedule->team) {
                continue;
            }

            $attendanceByStudent = $schedule->attendances->keyBy('student_id');
            $team = $schedule->team;
            $coachName = trim(
                ($team->coach->first_name ?? '')
                . ' '
                . ($team->coach->last_name ?? '')
            );
            if ($coachName === '') {
                $coachName = trim(
                    ($team->assistantCoach->first_name ?? '')
                    . ' '
                    . ($team->assistantCoach->last_name ?? '')
                );
            }

            foreach ($team->players as $player) {
                $student = $player->student;
                if (!$student) {
                    continue;
                }

                $attendance = $attendanceByStudent->get($student->id);
                $status = $attendance?->status ?? 'no_response';

                if ($statusFilter && $statusFilter !== $status) {
                    continue;
                }

                $rows->push([
                    'date' => Carbon::parse($schedule->start_time)->format('Y-m-d'),
                    'schedule_id' => $schedule->id,
                    'schedule_title' => $schedule->title,
                    'schedule_type' => $schedule->type,
                    'schedule_start' => Carbon::parse($schedule->start_time)->toIso8601String(),
                    'team_id' => $team->id,
                    'team_name' => $team->team_name,
                    'sport_name' => $team->sport?->name ?? 'Unknown',
                    'coach_name' => $coachName !== '' ? $coachName : 'Unassigned',
                    'student_id' => $student->id,
                    'student_name' => trim(
                        ($student->last_name ?? '')
                        . ', '
                        . ($student->first_name ?? '')
                        . (!empty($student->middle_name) ? ' ' . $student->middle_name : '')
                    ),
                    'status' => $status,
                    'notes' => $attendance?->notes,
                    'recorded_at' => $attendance?->recorded_at?->toIso8601String(),
                ]);
            }
        }

        $total = $rows->count();
        $present = $rows->where('status', 'present')->count();
        $absent = $rows->where('status', 'absent')->count();
        $late = $rows->where('status', 'late')->count();
        $excused = $rows->where('status', 'excused')->count();
        $noResponse = $rows->where('status', 'no_response')->count();
        $responded = $total - $noResponse;

        $kpis = [
            'total_records' => $total,
            'total_schedules' => $schedules->count(),
            'total_teams' => $schedules->pluck('team_id')->unique()->count(),
            'attendance_rate_percent' => $total > 0 ? round(($present / $total) * 100, 2) : 0,
            'response_rate_percent' => $total > 0 ? round(($responded / $total) * 100, 2) : 0,
            'counts' => [
                'present' => $present,
                'absent' => $absent,
                'late' => $late,
                'excused' => $excused,
                'no_response' => $noResponse,
            ],
        ];

        $statusDistribution = [
            ['status' => 'present', 'label' => 'Present', 'value' => $present],
            ['status' => 'absent', 'label' => 'Absent', 'value' => $absent],
            ['status' => 'late', 'label' => 'Late', 'value' => $late],
            ['status' => 'excused', 'label' => 'Excused', 'value' => $excused],
            ['status' => 'no_response', 'label' => 'No Response', 'value' => $noResponse],
        ];

        $trend = $rows->groupBy('date')
            ->map(function ($group, $date) {
                $groupTotal = $group->count();
                $groupPresent = $group->where('status', 'present')->count();
                $groupAbsent = $group->where('status', 'absent')->count();
                $groupLate = $group->where('status', 'late')->count();
                $groupExcused = $group->where('status', 'excused')->count();
                $groupNoResponse = $group->where('status', 'no_response')->count();

                return [
                    'date' => $date,
                    'total' => $groupTotal,
                    'present' => $groupPresent,
                    'absent' => $groupAbsent,
                    'late' => $groupLate,
                    'excused' => $groupExcused,
                    'no_response' => $groupNoResponse,
                    'attendance_rate_percent' => $groupTotal > 0 ? round(($groupPresent / $groupTotal) * 100, 2) : 0,
                ];
            })
            ->sortBy('date')
            ->values();

        $teamComparison = $rows->groupBy('team_id')
            ->map(function ($group) {
                $groupTotal = $group->count();
                $groupPresent = $group->where('status', 'present')->count();
                $groupAbsent = $group->where('status', 'absent')->count();
                $groupLate = $group->where('status', 'late')->count();
                $groupExcused = $group->where('status', 'excused')->count();
                $groupNoResponse = $group->where('status', 'no_response')->count();

                return [
                    'team_id' => $group->first()['team_id'],
                    'team_name' => $group->first()['team_name'],
                    'sport_name' => $group->first()['sport_name'],
                    'coach_name' => $group->first()['coach_name'],
                    'total' => $groupTotal,
                    'present' => $groupPresent,
                    'absent' => $groupAbsent,
                    'late' => $groupLate,
                    'excused' => $groupExcused,
                    'no_response' => $groupNoResponse,
                    'attendance_rate_percent' => $groupTotal > 0 ? round(($groupPresent / $groupTotal) * 100, 2) : 0,
                ];
            })
            ->sortBy('team_name')
            ->values();

        $scheduleTable = $rows->groupBy('schedule_id')
            ->map(function ($group) {
                $groupTotal = $group->count();
                $groupPresent = $group->where('status', 'present')->count();
                $groupAbsent = $group->where('status', 'absent')->count();
                $groupLate = $group->where('status', 'late')->count();
                $groupExcused = $group->where('status', 'excused')->count();
                $groupNoResponse = $group->where('status', 'no_response')->count();

                return [
                    'schedule_id' => $group->first()['schedule_id'],
                    'schedule_title' => $group->first()['schedule_title'],
                    'schedule_type' => $group->first()['schedule_type'],
                    'schedule_start' => $group->first()['schedule_start'],
                    'team_name' => $group->first()['team_name'],
                    'sport_name' => $group->first()['sport_name'],
                    'coach_name' => $group->first()['coach_name'],
                    'total' => $groupTotal,
                    'present' => $groupPresent,
                    'absent' => $groupAbsent,
                    'late' => $groupLate,
                    'excused' => $groupExcused,
                    'no_response' => $groupNoResponse,
                    'attendance_rate_percent' => $groupTotal > 0 ? round(($groupPresent / $groupTotal) * 100, 2) : 0,
                ];
            })
            ->sortBy('schedule_start')
            ->values();

        $atRiskAthletes = $rows->groupBy('student_id')
            ->map(function ($group) {
                $groupTotal = $group->count();
                $groupPresent = $group->where('status', 'present')->count();
                $groupAbsent = $group->where('status', 'absent')->count();
                $groupLate = $group->where('status', 'late')->count();
                $groupNoResponse = $group->where('status', 'no_response')->count();

                return [
                    'student_id' => $group->first()['student_id'],
                    'student_name' => $group->first()['student_name'],
                    'team_name' => $group->first()['team_name'],
                    'sport_name' => $group->first()['sport_name'],
                    'total' => $groupTotal,
                    'present' => $groupPresent,
                    'absent' => $groupAbsent,
                    'late' => $groupLate,
                    'no_response' => $groupNoResponse,
                    'risk_score' => $groupAbsent + $groupLate + $groupNoResponse,
                    'attendance_rate_percent' => $groupTotal > 0 ? round(($groupPresent / $groupTotal) * 100, 2) : 0,
                ];
            })
            ->sort(function ($a, $b) {
                if ($a['risk_score'] === $b['risk_score']) {
                    return $a['attendance_rate_percent'] <=> $b['attendance_rate_percent'];
                }

                return $b['risk_score'] <=> $a['risk_score'];
            })
            ->take(20)
            ->values();

        $scheduleTypes = TeamSchedule::query()
            ->select('type')
            ->distinct()
            ->orderBy('type')
            ->pluck('type')
            ->filter()
            ->values();

        $filters = [
            'selected' => $selected,
            'options' => [
                'sports' => Sport::query()
                    ->orderBy('name')
                    ->get(['id', 'name']),
                'teams' => Team::query()
                    ->with('sport')
                    ->orderBy('team_name')
                    ->get()
                    ->map(fn ($team) => [
                        'id' => $team->id,
                        'team_name' => $team->team_name,
                        'sport_name' => $team->sport?->name ?? 'Unknown',
                    ])->values(),
                'coaches' => Coach::query()
                    ->orderBy('last_name')
                    ->orderBy('first_name')
                    ->get(['id', 'first_name', 'last_name'])
                    ->map(fn ($coach) => [
                        'coach_id' => $coach->id,
                        'name' => trim(($coach->first_name ?? '') . ' ' . ($coach->last_name ?? '')),
                    ])->values(),
                'schedule_types' => $scheduleTypes,
                'statuses' => [
                    ['value' => 'present', 'label' => 'Present'],
                    ['value' => 'absent', 'label' => 'Absent'],
                    ['value' => 'late', 'label' => 'Late'],
                    ['value' => 'excused', 'label' => 'Excused'],
                    ['value' => 'no_response', 'label' => 'No Response'],
                ],
            ],
        ];

        $charts = [
            'status_distribution' => $statusDistribution,
            'attendance_trend' => $trend,
            'team_comparison' => $teamComparison,
        ];

        $tables = [
            'teams' => $teamComparison,
            'schedules' => $scheduleTable,
            'at_risk_athletes' => $atRiskAthletes,
        ];

        return Inertia::render('Admin/OperationsAttendance', [
            'filters' => $filters,
            'kpis' => $kpis,
            'charts' => $charts,
            'tables' => $tables,
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'records_scope' => $total,
            ],
        ]);
    }
}
