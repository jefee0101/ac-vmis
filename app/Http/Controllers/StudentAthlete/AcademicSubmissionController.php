<?php

namespace App\Http\Controllers\StudentAthlete;

use App\Http\Controllers\Controller;
use App\Models\AcademicDocument;
use App\Models\AcademicEligibilityEvaluation;
use App\Models\AcademicPeriod;
use App\Models\Student;
use App\Models\Team;
use App\Models\User;
use App\Services\AnnouncementService;
use App\Services\SecureUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class AcademicSubmissionController extends Controller
{
    public function __construct(
        private AnnouncementService $announcements,
        private SecureUploadService $secureUpload
    )
    {
    }

    public function index()
    {
        $student = Student::where('user_id', Auth::id())->first();

        if (!$student) {
            return Inertia::render('StudentAthletes/AcademicSubmissions', [
                'student' => null,
                'openPeriods' => [],
                'submissions' => [],
            ]);
        }

        $openPeriodsQuery = AcademicPeriod::query()
            ->orderByDesc('starts_on');

        // Backward-compatible guard: if migration adding this column has not run yet,
        // skip the open-window filter instead of crashing.
        if (Schema::hasColumn('academic_periods', 'is_submission_open')) {
            $openPeriodsQuery->where('is_submission_open', true);
        }
        if (Schema::hasColumn('academic_periods', 'is_locked')) {
            $openPeriodsQuery->where('is_locked', false);
        }

        $openPeriods = $openPeriodsQuery->get();

        $submissions = AcademicDocument::query()
            ->with('academicPeriod')
            ->where('student_id', $student->id)
            ->whereNotNull('academic_period_id')
            ->latest('uploaded_at')
            ->get();

        $evalByPeriod = AcademicEligibilityEvaluation::query()
            ->where('student_id', $student->id)
            ->whereNotNull('academic_period_id')
            ->get()
            ->keyBy('academic_period_id');

        return Inertia::render('StudentAthletes/AcademicSubmissions', [
            'student' => [
                'id' => $student->id,
                'name' => trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')),
                'student_id_number' => $student->student_id_number,
            ],
            'openPeriods' => $openPeriods->map(function ($p) use ($evalByPeriod) {
                $evaluation = $evalByPeriod->get($p->id);
                $status = $evaluation?->status;
                $isEligible = $status === 'eligible';

                return [
                    'id' => $p->id,
                    'school_year' => $p->school_year,
                    'term' => $p->term,
                    'starts_on' => optional($p->starts_on)->toDateString(),
                    'ends_on' => optional($p->ends_on)->toDateString(),
                    'announcement' => $p->announcement,
                    'eligibility_status' => $status,
                    'is_eligible' => $isEligible,
                    'can_submit' => !$isEligible,
                ];
            }),
            'submissions' => $submissions->map(function ($doc) use ($evalByPeriod) {
                $evaluation = $doc->academic_period_id ? $evalByPeriod->get($doc->academic_period_id) : null;
                return [
                    'id' => $doc->id,
                    'period_id' => $doc->academic_period_id,
                    'period_label' => $doc->academicPeriod
                        ? ($doc->academicPeriod->school_year . ' - ' . $doc->academicPeriod->term)
                        : null,
                    'document_type' => $doc->document_type,
                    'file_url' => $doc->id ? route('files.academic', $doc->id) : null,
                    'uploaded_at' => optional($doc->uploaded_at)->toDateTimeString(),
                    'notes' => $doc->notes,
                    'evaluation' => $evaluation ? [
                        'gpa' => $evaluation->gpa,
                        'status' => $evaluation->status,
                        'remarks' => $evaluation->remarks,
                        'evaluated_at' => optional($evaluation->evaluated_at)->toDateTimeString(),
                    ] : null,
                ];
            }),
        ]);
    }

    public function store(Request $request)
    {
        $student = Student::where('user_id', Auth::id())->firstOrFail();

        $validated = $request->validate([
            'academic_period_id' => 'required|exists:academic_periods,id',
            'document_type' => 'required|in:grade_report,other',
            'semester_gpa' => 'nullable|numeric|min:0|max:5',
            'notes' => 'nullable|string|max:1000',
            'document_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $period = AcademicPeriod::findOrFail((int) $validated['academic_period_id']);
        if (Schema::hasColumn('academic_periods', 'is_submission_open')) {
            abort_unless((bool) $period->is_submission_open, 422, 'Submission window is closed for this period.');
        }
        if (Schema::hasColumn('academic_periods', 'is_locked')) {
            abort_unless(!(bool) $period->is_locked, 422, 'This period is locked and no longer accepts submissions.');
        }

        $eligible = AcademicEligibilityEvaluation::query()
            ->where('student_id', $student->id)
            ->where('academic_period_id', $period->id)
            ->where('status', 'eligible')
            ->exists();
        if ($eligible) {
            abort(422, 'You are already eligible for this period. Further submissions are locked.');
        }

        $filePath = $this->secureUpload->storePublic(
            $request->file('document_file'),
            'academic_documents',
            'academic_document'
        );

        $notes = trim((string) ($validated['notes'] ?? ''));
        if (isset($validated['semester_gpa']) && $validated['semester_gpa'] !== null && $validated['semester_gpa'] !== '') {
            $gpaLine = 'Submitted GPA: ' . $validated['semester_gpa'];
            $notes = $notes !== '' ? ($gpaLine . "\n" . $notes) : $gpaLine;
        }

        AcademicDocument::create([
            'student_id' => $student->id,
            'document_type' => $validated['document_type'],
            'academic_period_id' => (int) $validated['academic_period_id'],
            'file_path' => $filePath,
            'uploaded_by' => Auth::id(),
            'uploaded_at' => now(),
            'notes' => $notes !== '' ? $notes : null,
        ]);

        $periodLabel = "{$period->school_year} - {$period->term}";
        $studentName = trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? ''));
        $message = "New academic submission from {$studentName} for {$periodLabel}.";

        $adminUserIds = User::query()
            ->where('status', 'approved')
            ->where('role', 'admin')
            ->pluck('id')
            ->all();

        $coachUserIds = Team::query()
            ->whereHas('players', fn ($q) => $q->where('student_id', $student->id))
            ->with(['coach:id,user_id', 'assistantCoach:id,user_id'])
            ->get()
            ->flatMap(function ($team) {
                return [
                    $team->coach?->user_id,
                    $team->assistantCoach?->user_id,
                ];
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->announcements->announceMany(
            array_merge($adminUserIds, $coachUserIds),
            'Academic Submission Received',
            $message,
            'academic',
            Auth::id()
        );

        return back()->with('success', 'Semestral grade document submitted.');
    }

    public function print(Request $request)
    {
        $student = Student::where('user_id', Auth::id())->first();
        abort_unless($student, 403);

        $openPeriodsQuery = AcademicPeriod::query()
            ->orderByDesc('starts_on');

        if (Schema::hasColumn('academic_periods', 'is_submission_open')) {
            $openPeriodsQuery->where('is_submission_open', true);
        }
        if (Schema::hasColumn('academic_periods', 'is_locked')) {
            $openPeriodsQuery->where('is_locked', false);
        }

        $openPeriods = $openPeriodsQuery->get();

        $submissions = AcademicDocument::query()
            ->with('academicPeriod')
            ->where('student_id', $student->id)
            ->whereNotNull('academic_period_id')
            ->latest('uploaded_at')
            ->get();

        $evalByPeriod = AcademicEligibilityEvaluation::query()
            ->where('student_id', $student->id)
            ->whereNotNull('academic_period_id')
            ->get()
            ->keyBy('academic_period_id');

        $periods = $openPeriods->map(function ($p) use ($evalByPeriod) {
            $evaluation = $evalByPeriod->get($p->id);
            $label = $p->school_year . ' ' . $p->term;
            $window = (optional($p->starts_on)->toDateString() ?: '-') . ' to ' . (optional($p->ends_on)->toDateString() ?: '-');

            return [
                'label' => $label,
                'eligibility_status' => $evaluation?->status,
                'window' => $window,
            ];
        })->values();

        $submissionRows = $submissions->map(function ($doc) use ($evalByPeriod) {
            $evaluation = $doc->academic_period_id ? $evalByPeriod->get($doc->academic_period_id) : null;

            return [
                'period_label' => $doc->academicPeriod
                    ? ($doc->academicPeriod->school_year . ' - ' . $doc->academicPeriod->term)
                    : null,
                'document_type' => $doc->document_type,
                'uploaded_at' => optional($doc->uploaded_at)->format('M j, Y g:i A'),
                'status' => $evaluation?->status,
                'gpa' => $evaluation?->gpa,
                'remarks' => $evaluation?->remarks,
            ];
        })->values();

        return view('print.student-academics', [
            'student' => [
                'name' => trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')),
                'student_id_number' => $student->student_id_number,
            ],
            'periods' => $periods,
            'submissions' => $submissionRows,
            'generatedAt' => now()->format('M j, Y g:i A'),
        ]);
    }
}
