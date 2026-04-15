<?php

namespace App\Http\Controllers\Coaches;

use App\Http\Controllers\Controller;
use App\Models\AcademicDocument;
use App\Models\AcademicEligibilityEvaluation;
use App\Models\AcademicPeriod;
use App\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AcademicVisibilityController extends Controller
{
    public function index(Request $request)
    {
        $coach = $request->user()?->coach;

        if (!$coach) {
            return Inertia::render('Coaches/AcademicVisibility', [
                'team' => null,
                'periods' => [],
                'selectedPeriodId' => null,
                'rows' => [],
            ]);
        }

        $team = Team::with(['players.student', 'sport'])
            ->forCoach($coach->id)
            ->first();

        if (!$team) {
            return Inertia::render('Coaches/AcademicVisibility', [
                'team' => null,
                'periods' => [],
                'selectedPeriodId' => null,
                'rows' => [],
            ]);
        }

        $periods = AcademicPeriod::query()
            ->orderByDesc('starts_on')
            ->get();

        $selectedPeriodId = (int) $request->query('period_id', 0);
        if (!$selectedPeriodId) {
            $selectedPeriodId = (int) ($periods->first()->id ?? 0);
        }

        $studentIds = $team->players->pluck('student_id')->filter()->unique()->values();

        $docsByStudent = AcademicDocument::query()
            ->periodSubmission()
            ->whereIn('student_id', $studentIds)
            ->when($selectedPeriodId, fn ($q) => $q->where('academic_period_id', $selectedPeriodId))
            ->get()
            ->groupBy('student_id');

        $evalByStudent = AcademicEligibilityEvaluation::query()
            ->whereIn('student_id', $studentIds)
            ->when($selectedPeriodId, fn ($q) => $q->where('academic_period_id', $selectedPeriodId))
            ->get()
            ->keyBy('student_id');

        $rows = $team->players
            ->filter(fn ($tp) => $tp->student !== null)
            ->map(function ($tp) use ($docsByStudent, $evalByStudent) {
                $student = $tp->student;
                $doc = $docsByStudent->get($student->id)?->sortByDesc('uploaded_at')?->first();
                $evaluation = $evalByStudent->get($student->id);

                return [
                    'student_id' => $student->id,
                    'student_name' => trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')),
                    'student_id_number' => $student->student_id_number,
                    'submitted' => $doc !== null,
                    'uploaded_at' => optional($doc?->uploaded_at)->toDateTimeString(),
                    'document_url' => $doc?->id ? route('files.academic', $doc->id) : null,
                    'evaluation_status' => $evaluation?->status,
                    'evaluation_gpa' => $evaluation?->gpa,
                ];
            })
            ->values();

        return Inertia::render('Coaches/AcademicVisibility', [
            'team' => [
                'id' => $team->id,
                'team_name' => $team->team_name,
                'sport' => $team->sport?->name ?? $team->sport_id ?? 'unknown',
            ],
            'periods' => $periods->map(fn ($p) => [
                'id' => $p->id,
                'school_year' => $p->school_year,
                'term' => $p->term,
                'status' => (string) $p->status,
            ]),
            'selectedPeriodId' => $selectedPeriodId ?: null,
            'rows' => $rows,
        ]);
    }
}
