<?php

namespace App\Http\Controllers\Coaches;

use App\Http\Controllers\Controller;
use App\Models\AcademicDocument;
use App\Models\AcademicPeriod;
use App\Models\ScheduleAttendance;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\TeamSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class CoachDashboardController extends Controller
{
    public function index(Request $request)
    {
        $coach = $request->user()?->coach;

        if (!$coach) {
            return Inertia::render('Coaches/CoachDashboard', $this->emptyPayload());
        }

        $team = Team::with('sport')
            ->forCoach($coach->id)
            ->first();

        if (!$team) {
            return Inertia::render('Coaches/CoachDashboard', $this->emptyPayload());
        }

        $teamId = $team->id;
        $now = Carbon::now(config('app.timezone'));

        $rosterTotal = TeamPlayer::where('team_id', $teamId)->count();
        $hasPlayerStatus = Schema::hasColumn('team_players', 'player_status');
        $rosterInjured = $hasPlayerStatus
            ? TeamPlayer::where('team_id', $teamId)->where('player_status', 'injured')->count()
            : 0;
        $rosterMissingPositions = TeamPlayer::where('team_id', $teamId)
            ->where(function ($query) {
                $query->whereNull('athlete_position')->orWhere('athlete_position', '');
            })
            ->count();
        $rosterJerseyPending = TeamPlayer::where('team_id', $teamId)
            ->where(function ($query) {
                $query->whereNull('jersey_number')->orWhere('jersey_number', '');
            })
            ->count();

        $nextScheduleModel = TeamSchedule::where('team_id', $teamId)
            ->where('start_time', '>=', $now)
            ->orderBy('start_time')
            ->first();

        $nextSchedule = $nextScheduleModel ? [
            'id' => $nextScheduleModel->id,
            'title' => $nextScheduleModel->title,
            'type' => $nextScheduleModel->type,
            'venue' => $nextScheduleModel->venue,
            'start' => $nextScheduleModel->start_time?->toIso8601String(),
            'end' => $nextScheduleModel->end_time?->toIso8601String(),
        ] : null;

        $pastSchedules = TeamSchedule::query()
            ->where('team_id', $teamId)
            ->where('end_time', '<', $now)
            ->withCount(['attendances', 'wellnessLogs'])
            ->get();

        $attendanceNeedsReview = $pastSchedules->where('attendances_count', 0)->count();
        $attendanceInProgress = $rosterTotal > 0
            ? $pastSchedules->filter(fn ($s) => $s->attendances_count > 0 && $s->attendances_count < $rosterTotal)->count()
            : 0;
        $wellnessPending = $pastSchedules->filter(function ($s) {
            $type = strtolower((string) $s->type);
            return in_array($type, ['practice', 'game'], true) && $s->attendances_count > 0 && $s->wellness_logs_count === 0;
        })->count();

        $latestPeriod = AcademicPeriod::query()
            ->orderByDesc('starts_on')
            ->first();

        $academicMissing = 0;
        if ($latestPeriod && $rosterTotal > 0) {
            $studentIds = TeamPlayer::where('team_id', $teamId)
                ->pluck('student_id')
                ->filter()
                ->unique()
                ->values();

            if ($studentIds->isNotEmpty()) {
                $submittedIds = AcademicDocument::query()
                    ->periodSubmission()
                    ->whereIn('student_id', $studentIds)
                    ->where('academic_period_id', $latestPeriod->id)
                    ->pluck('student_id')
                    ->unique();

                $academicMissing = $studentIds->diff($submittedIds)->count();
            }
        }

        $since = $now->copy()->subDays(7);
        $attendanceRaw = ScheduleAttendance::query()
            ->join('team_schedules', 'schedule_attendances.schedule_id', '=', 'team_schedules.id')
            ->where('team_schedules.team_id', $teamId)
            ->where('team_schedules.start_time', '>=', $since)
            ->groupBy('schedule_attendances.status')
            ->select('schedule_attendances.status', DB::raw('COUNT(*) as total'))
            ->pluck('total', 'schedule_attendances.status');

        $attendanceSnapshot = [
            'present' => (int) ($attendanceRaw['present'] ?? 0),
            'late' => (int) ($attendanceRaw['late'] ?? 0),
            'absent' => (int) ($attendanceRaw['absent'] ?? 0),
            'excused' => (int) ($attendanceRaw['excused'] ?? 0),
        ];

        return Inertia::render('Coaches/CoachDashboard', [
            'team' => [
                'id' => $team->id,
                'team_name' => $team->team_name,
                'sport' => $team->sport?->name ?? $team->sport_id ?? 'unknown',
            ],
            'nextSchedule' => $nextSchedule,
            'metrics' => [
                'attendance_needs_review' => $attendanceNeedsReview,
                'attendance_in_progress' => $attendanceInProgress,
                'wellness_pending' => $wellnessPending,
                'academic_missing' => $academicMissing,
                'roster_total' => $rosterTotal,
                'roster_injured' => $rosterInjured,
                'roster_missing_positions' => $rosterMissingPositions,
                'roster_jersey_pending' => $rosterJerseyPending,
                'latest_period' => $latestPeriod ? ($latestPeriod->school_year . ' ' . $latestPeriod->term) : null,
            ],
            'attendanceSnapshot' => $attendanceSnapshot,
        ]);
    }

    private function emptyPayload(): array
    {
        return [
            'team' => null,
            'nextSchedule' => null,
            'metrics' => [
                'attendance_needs_review' => 0,
                'attendance_in_progress' => 0,
                'wellness_pending' => 0,
                'academic_missing' => 0,
                'roster_total' => 0,
                'roster_injured' => 0,
                'roster_missing_positions' => 0,
                'roster_jersey_pending' => 0,
                'latest_period' => null,
            ],
            'attendanceSnapshot' => [
                'present' => 0,
                'late' => 0,
                'absent' => 0,
                'excused' => 0,
            ],
        ];
    }
}
