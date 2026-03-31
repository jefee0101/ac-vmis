<?php

namespace App\Http\Controllers\StudentAthlete;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\WellnessLog;
use App\Services\AcademicHoldService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class WellnessHistoryController extends Controller
{
    public function __construct(private AcademicHoldService $holdService)
    {
    }

    public function index()
    {
        $student = Student::where('user_id', Auth::id())->first();

        if (!$student) {
            return Inertia::render('StudentAthletes/WellnessHistory', [
                'student' => null,
                'logs' => [],
            ]);
        }

        $holdState = $this->holdService->evaluate($student);
        if ($holdState['status']) {
            $teamName = \App\Models\Team::whereHas('players', fn ($q) => $q->where('student_id', $student->id))
                ->orderBy('team_name')
                ->value('team_name');
            $teamText = $teamName ? "Your team ({$teamName}) is temporarily paused." : 'Your team access is temporarily paused.';
            return Inertia::render('StudentAthletes/WellnessHistory', [
                'accessLocked' => true,
                'lockStatus' => $holdState['status'] ?? 'Suspended',
                'lockMessage' => "Academic submission window is active. {$teamText}",
                'student' => [
                    'id' => $student->id,
                    'student_id_number' => $student->student_id_number,
                    'name' => trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')),
                ],
                'logs' => [],
            ]);
        }

        $logs = WellnessLog::query()
            ->with(['team', 'schedule', 'logger'])
            ->where('student_id', $student->id)
            ->latest('log_date')
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'log_date' => optional($log->log_date)->toDateString(),
                    'team_name' => $log->team?->team_name,
                    'schedule_title' => $log->schedule?->title,
                    'schedule_type' => $log->schedule?->type,
                    'injury_observed' => (bool) $log->injury_observed,
                    'injury_notes' => $log->injury_notes,
                    'fatigue_level' => $log->fatigue_level,
                    'performance_condition' => $log->performance_condition,
                    'remarks' => $log->remarks,
                    'logged_by' => $log->logger?->name,
                    'created_at' => optional($log->created_at)->toDateTimeString(),
                ];
            })->values();

        return Inertia::render('StudentAthletes/WellnessHistory', [
            'student' => [
                'id' => $student->id,
                'student_id_number' => $student->student_id_number,
                'name' => trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')),
            ],
            'logs' => $logs,
        ]);
    }
}
