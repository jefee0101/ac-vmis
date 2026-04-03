<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\AccountApprovedMail;
use App\Mail\AccountRejectedMail;
use App\Mail\CoachOnboardingMail;
use App\Models\AccountApproval;
use App\Models\Announcement;
use App\Models\Coach;
use App\Models\Sport;
use App\Models\Student;
use App\Models\Team;
use App\Models\User;
use App\Services\AnnouncementService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AdminController extends Controller
{
    public function __construct(private AnnouncementService $announcements)
    {
    }

    public function dashboard(Request $request)
    {
        $period = (string) $request->query('period', 'week');
        if (!in_array($period, ['today', 'week', 'month'], true)) {
            $period = 'week';
        }

        $cacheKey = "admin_dashboard:{$period}";
        $payload = Cache::remember($cacheKey, now()->addSeconds(60), function () use ($period) {
            [$start, $end] = $this->dashboardRange($period);

            $attendanceSummary = $this->attendanceSummary($start, $end);
            $attendanceTrend = $this->attendanceTrend($start, $end);
            $healthDistribution = $this->healthDistribution();
            $academicByTeam = $this->academicByTeam();
            $heatmap = $this->attendanceHeatmap($start, $end);
            $todaySchedules = $this->todaySchedules();
            $needsAttentionQueue = $this->needsAttentionQueue();

            return [
                'dashboard' => [
                    'filters' => [
                        'period' => $period,
                        'start_date' => $start->toDateString(),
                        'end_date' => $end->toDateString(),
                    ],
                    'kpis' => [
                        'attendance_rate' => $attendanceSummary['attendance_rate'],
                        'no_response' => $attendanceSummary['no_response'],
                        'expired_clearances' => $healthDistribution['expired'],
                        'academic_at_risk' => $academicByTeam['totals']['probation'] + $academicByTeam['totals']['ineligible'],
                        'pending_approvals' => User::query()
                            ->where('status', 'pending')
                            ->whereIn('role', ['student-athlete', 'student', 'coach'])
                            ->count(),
                    ],
                    'trends' => [
                        'labels' => $attendanceTrend['labels'],
                        'attendance' => $attendanceTrend['series'],
                        'health_distribution' => $healthDistribution,
                        'academic_by_team' => $academicByTeam['rows'],
                        'heatmap' => $heatmap,
                    ],
                    'queues' => [
                        'today_schedules' => $todaySchedules,
                        'needs_attention' => $needsAttentionQueue,
                    ],
                    'activity_log' => $this->recentActivityLog(),
                ],
            ];
        });

        return Inertia::render('Admin/AdminDashboard', $payload);
    }

    public function approve(User $user)
    {
        $clearance = null;

        if (in_array($user->role, ['student-athlete', 'student'], true)) {
            $student = $user->student;
            $clearance = $student?->latestHealthClearance;
            $academicDocument = $student?->latestAcademicDocument;

            if (!$clearance) {
                return back()->withErrors([
                    'approval' => 'Cannot approve this student-athlete without health clearance data.',
                ]);
            }
            if (!$academicDocument) {
                return back()->withErrors([
                    'approval' => 'Cannot approve this student-athlete without academic document data.',
                ]);
            }
        }

        DB::transaction(function () use ($user, $clearance) {
            if ($clearance) {
                $clearance->update([
                    'reviewed_by' => Auth::id(),
                    'reviewed_at' => now(),
                ]);
            }

            $user->update([
                'status' => 'approved',
            ]);

            AccountApproval::create([
                'user_id' => $user->id,
                'admin_id' => Auth::id(),
                'decision' => 'approved',
            ]);
        });

        $this->announcements->announce(
            $user->id,
            'Account Approved',
            'Your account has been approved. You may now log in and access the system.',
            Announcement::TYPE_APPROVAL,
            Auth::id(),
            'notify_approvals',
            false
        );

        $this->sendAccountStatusMail($user->fresh(['settings']), new AccountApprovedMail($user));

        return back()->with('success', 'User approved.');
    }

    public function reject(Request $request, User $user)
    {
        $request->validate([
            'remarks' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($user, $request) {
            $user->update([
                'status' => 'rejected',
            ]);

            AccountApproval::create([
                'user_id' => $user->id,
                'admin_id' => Auth::id(),
                'decision' => 'rejected',
                'remarks' => $request->remarks,
            ]);
        });

        $this->announcements->announce(
            $user->id,
            'Account Rejected',
            'Your account registration was rejected.' . ($request->remarks ? " Remarks: {$request->remarks}" : ''),
            Announcement::TYPE_APPROVAL,
            Auth::id(),
            'notify_approvals',
            false
        );

        $this->sendAccountStatusMail($user->fresh(['settings']), new AccountRejectedMail($user, $request->remarks));

        return back()->with('success', 'User rejected.');
    }

    public function deactivate(User $user)
    {
        if ($user->role === 'admin') {
            return back()->withErrors([
                'user_action' => 'Admin accounts cannot be deactivated from this panel.',
            ]);
        }

        if ($user->status !== 'approved') {
            return back()->withErrors([
                'user_action' => 'Only approved accounts can be deactivated.',
            ]);
        }

        DB::transaction(function () use ($user) {
            $user->update([
                'status' => 'deactivated',
            ]);
        });

        $this->announcements->announce(
            $user->id,
            'Account Deactivated',
            'Your account has been temporarily deactivated. Contact administration for reactivation.',
            Announcement::TYPE_SYSTEM,
            Auth::id(),
            'notify_approvals'
        );

        return back()->with('success', 'User deactivated.');
    }

    public function reactivate(User $user)
    {
        if ($user->role === 'admin') {
            return back()->withErrors([
                'user_action' => 'Admin accounts cannot be reactivated from this panel.',
            ]);
        }

        if ($user->status !== 'deactivated') {
            return back()->withErrors([
                'user_action' => 'Only deactivated accounts can be reactivated.',
            ]);
        }

        DB::transaction(function () use ($user) {
            $user->update([
                'status' => 'approved',
            ]);
        });

        $this->announcements->announce(
            $user->id,
            'Account Reactivated',
            'Your account has been reactivated. You may log in again.',
            Announcement::TYPE_SYSTEM,
            Auth::id(),
            'notify_approvals'
        );

        return back()->with('success', 'User reactivated.');
    }

    public function approvalManagement(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'pending');
        $readiness = (string) $request->query('readiness', 'all');
        $sort = (string) $request->query('sort', 'newest');

        $allowedStatuses = ['pending', 'rejected'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'pending';
        }

        $allowedReadiness = ['all', 'ready', 'incomplete'];
        if (!in_array($readiness, $allowedReadiness, true)) {
            $readiness = 'all';
        }

        $allowedSorts = ['newest', 'oldest', 'name_asc', 'name_desc'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'newest';
        }

        $baseQuery = User::query()
            ->where('status', $status)
            ->whereIn('role', ['student-athlete', 'student', 'coach']);

        if ($search !== '') {
            $baseQuery->where(function ($query) use ($search) {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $applyReadinessFilter = function ($query, string $state): void {
            if ($state === 'ready') {
                $query->where(function ($q) {
                    $q->where('role', 'coach')
                        ->orWhere(function ($sq) {
                            $sq->whereIn('role', ['student-athlete', 'student'])
                                ->whereHas('student.latestHealthClearance')
                                ->whereHas('student.latestAcademicDocument');
                        });
                });
            }

            if ($state === 'incomplete') {
                $query->whereIn('role', ['student-athlete', 'student'])
                    ->where(function ($q) {
                        $q->whereDoesntHave('student.latestHealthClearance')
                            ->orWhereDoesntHave('student.latestAcademicDocument');
                    });
            }
        };

        if ($status === 'pending') {
            $applyReadinessFilter($baseQuery, $readiness);
        }

        $queueQuery = (clone $baseQuery)
            ->select(['id', 'first_name', 'middle_name', 'last_name', 'email', 'role', 'status', 'avatar', 'created_at'])
            ->with([
                'student:id,user_id,student_id_number,course_or_strand,current_grade_level',
                'student.latestHealthClearance' => function ($query) {
                    $query->select(
                        'athlete_health_clearances.id',
                        'athlete_health_clearances.student_id',
                        'athlete_health_clearances.valid_until',
                        'athlete_health_clearances.physician_name',
                        'athlete_health_clearances.conditions',
                        'athlete_health_clearances.allergies',
                        'athlete_health_clearances.restrictions'
                    );
                },
                'student.latestAcademicDocument' => function ($query) {
                    $query->select(
                        'academic_documents.id',
                        'academic_documents.student_id',
                        'academic_documents.document_type',
                        'academic_documents.uploaded_at'
                    );
                },
                'coach:id,user_id,coach_status',
            ]);

        if ($sort === 'newest') {
            $queueQuery->latest();
        } elseif ($sort === 'oldest') {
            $queueQuery->oldest();
        } elseif ($sort === 'name_asc') {
            $queueQuery->orderBy('last_name', 'asc')->orderBy('first_name', 'asc');
        } else {
            $queueQuery->orderBy('last_name', 'desc')->orderBy('first_name', 'desc');
        }

        $queue = $queueQuery
            ->paginate(10)
            ->withQueryString();

        $pendingPool = User::query()
            ->where('status', 'pending')
            ->whereIn('role', ['student-athlete', 'student', 'coach']);

        $rejectedPool = User::query()
            ->where('status', 'rejected')
            ->whereIn('role', ['student-athlete', 'student', 'coach']);

        $readyPool = User::query()
            ->where('status', 'pending')
            ->whereIn('role', ['student-athlete', 'student', 'coach']);
        $applyReadinessFilter($readyPool, 'ready');

        $incompletePool = User::query()
            ->where('status', 'pending')
            ->whereIn('role', ['student-athlete', 'student', 'coach']);
        $applyReadinessFilter($incompletePool, 'incomplete');

        return inertia('Admin/PeopleQueue', [
            'queue' => $queue,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'readiness' => $readiness,
                'sort' => $sort,
            ],
            'stats' => [
                'pending_total' => (clone $pendingPool)->count(),
                'ready_total' => (clone $readyPool)->count(),
                'incomplete_total' => (clone $incompletePool)->count(),
                'rejected_total' => (clone $rejectedPool)->count(),
            ],
            'pendingCount' => (clone $pendingPool)->count(),
        ]);
    }

    public function userManagement(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $role = (string) $request->query('role', 'all');
        $status = (string) $request->query('status', 'approved');
        $sort = (string) $request->query('sort', 'created_at');
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedRoles = ['all', 'student-athlete', 'coach'];
        if (!in_array($role, $allowedRoles, true)) {
            $role = 'all';
        }

        $allowedStatuses = ['approved', 'deactivated'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'approved';
        }

        $allowedSorts = ['name', 'email', 'created_at'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        $baseQuery = User::query()
            ->where('status', $status)
            ->whereIn('role', ['student-athlete', 'coach']);

        if ($role !== 'all') {
            $baseQuery->where('role', $role);
        }

        if ($search !== '') {
            $baseQuery->where(function ($query) use ($search) {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = (clone $baseQuery)
            ->select(['id', 'first_name', 'middle_name', 'last_name', 'email', 'role', 'status', 'avatar', 'created_at'])
            ->with([
                'student:id,user_id,student_id_number,course_or_strand,current_grade_level,student_status,phone_number,date_of_birth,gender,height,weight,emergency_contact_name,emergency_contact_relationship,emergency_contact_phone',
                'coach:id,user_id,coach_status,phone_number,date_of_birth,gender',
            ])
            ->when($sort === 'name', function ($query) use ($direction) {
                $query->orderBy('last_name', $direction)->orderBy('first_name', $direction);
            }, function ($query) use ($sort, $direction) {
                $query->orderBy($sort, $direction);
            })
            ->paginate(10)
            ->withQueryString();

        $totalBase = User::query()
            ->whereIn('status', ['approved', 'deactivated'])
            ->whereIn('role', ['student-athlete', 'coach']);

        $totals = [
            'all' => (clone $totalBase)->where('status', 'approved')->count(),
            'students' => (clone $totalBase)->where('status', 'approved')->where('role', 'student-athlete')->count(),
            'coaches' => (clone $totalBase)->where('status', 'approved')->where('role', 'coach')->count(),
            'deactivated' => (clone $totalBase)->where('status', 'deactivated')->count(),
            'filtered' => (clone $baseQuery)->count(),
        ];
        $pendingCount = User::query()->where('status', 'pending')->count();
        $sports = Sport::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Sport $sport) => [
                'id' => (int) $sport->id,
                'name' => $sport->name,
            ])
            ->values();

        $assignableTeams = Team::query()
            ->whereNull('archived_at')
            ->with([
                'sport:id,name',
                'coach.user:id,first_name,last_name',
                'assistantCoach.user:id,first_name,last_name',
            ])
            ->orderBy('team_name')
            ->get()
            ->map(function (Team $team) {
                $headCoach = trim((string) ($team->coach?->first_name . ' ' . $team->coach?->last_name));
                $assistantCoach = trim((string) ($team->assistantCoach?->first_name . ' ' . $team->assistantCoach?->last_name));

                return [
                    'id' => (int) $team->id,
                    'team_name' => $team->team_name,
                    'year' => $team->year,
                    'sport_id' => (int) $team->sport_id,
                    'sport_name' => $team->sport?->name,
                    'coach_id' => $team->coach_id ? (int) $team->coach_id : null,
                    'assistant_coach_id' => $team->assistant_coach_id ? (int) $team->assistant_coach_id : null,
                    'coach_name' => $headCoach !== '' ? $headCoach : null,
                    'assistant_coach_name' => $assistantCoach !== '' ? $assistantCoach : null,
                ];
            })
            ->values();

        return Inertia::render('Admin/PeopleUsers', [
            'users' => $users,
            'filters' => [
                'search' => $search,
                'role' => $role,
                'status' => $status,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'totals' => $totals,
            'pendingCount' => $pendingCount,
            'sports' => $sports,
            'assignableTeams' => $assignableTeams,
        ]);
    }

    public function storeCoach(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone_number' => 'nullable|string|max:30',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:Male,Female,Other',
            'home_address' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:500',
            'assignment_role' => 'nullable|in:head,assistant',
            'team_ids' => 'nullable|array',
            'team_ids.*' => 'integer|exists:teams,id',
        ]);

        $teamIds = collect($validated['team_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $assignmentRole = (string) ($validated['assignment_role'] ?? 'assistant');
        $temporaryPassword = Str::random(12);

        $activeTeams = Team::query()
            ->whereIn('id', $teamIds)
            ->whereNull('archived_at')
            ->get(['id', 'team_name', 'sport_id', 'year', 'coach_id', 'assistant_coach_id']);

        if (count($teamIds) !== $activeTeams->count()) {
            throw ValidationException::withMessages([
                'team_ids' => 'One or more selected teams are archived or unavailable.',
            ]);
        }

        $conflicts = [];
        foreach ($activeTeams as $team) {
            if ($assignmentRole === 'head' && $team->coach_id) {
                $conflicts[] = "{$team->team_name} ({$team->year}) already has a head coach.";
            }
            if ($assignmentRole === 'assistant' && $team->assistant_coach_id) {
                $conflicts[] = "{$team->team_name} ({$team->year}) already has an assistant coach.";
            }
        }

        if (!empty($conflicts)) {
            throw ValidationException::withMessages([
                'team_ids' => "Assignment conflicts found:\n- " . implode("\n- ", $conflicts),
            ]);
        }

        $newUser = DB::transaction(function () use ($validated, $teamIds, $assignmentRole, $temporaryPassword) {
            $user = User::create([
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'password' => Hash::make($temporaryPassword),
                'must_change_password' => true,
                'role' => 'coach',
                'status' => 'approved',
            ]);

            $coach = Coach::create([
                'user_id' => $user->id,
                'phone_number' => $validated['phone_number'] ?? null,
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'home_address' => $validated['home_address'] ?? null,
                'coach_status' => 'Active',
            ]);

            if (!empty($teamIds)) {
                $column = $assignmentRole === 'head' ? 'coach_id' : 'assistant_coach_id';
                $teams = Team::query()
                    ->whereIn('id', $teamIds)
                    ->whereNull('archived_at')
                    ->get(['id', 'team_name', 'year', 'coach_id', 'assistant_coach_id']);

                foreach ($teams as $team) {
                    $team->update([$column => $coach->id]);
                    $roleLabel = $assignmentRole === 'head' ? 'head coach' : 'assistant coach';
                    AccountApproval::create([
                        'user_id' => $user->id,
                        'admin_id' => Auth::id(),
                        'decision' => 'approved',
                        'remarks' => "Coach assignment audit: assigned as {$roleLabel} to {$team->team_name} ({$team->year}).",
                    ]);
                }
            }

            $remarks = "Admin-provisioned coach account created."
                . (!empty($validated['notes']) ? " Notes: {$validated['notes']}" : '');
            AccountApproval::create([
                'user_id' => $user->id,
                'admin_id' => Auth::id(),
                'decision' => 'approved',
                'remarks' => $remarks,
            ]);

            return $user;
        });
        $activationToken = Password::broker()->createToken($newUser);
        $activationUrl = route('coach.onboarding.activate', [
            'email' => $newUser->email,
            'token' => $activationToken,
        ]);

        $this->announcements->announce(
            $newUser->id,
            'Coach Account Created',
            'Your coach account has been provisioned by the administrator. Please sign in and update your profile details.',
            Announcement::TYPE_APPROVAL,
            Auth::id(),
            'notify_approvals',
            false
        );

        $emailSent = true;
        try {
            Mail::to($newUser->email)->send(new CoachOnboardingMail($newUser, $temporaryPassword, url('/Login'), $activationUrl));
        } catch (\Throwable $e) {
            $emailSent = false;
            Log::notice('Coach onboarding email not sent. Check mail credentials/settings.', [
                'user_id' => $newUser->id,
                'email' => $newUser->email,
                'reason' => $e->getCode() ?: 'smtp_auth_or_transport',
            ]);
        }

        return back()
            ->with('success', 'Coach account created successfully.')
            ->with('coach_onboarding', [
                'email' => $newUser->email,
                'temporary_password' => $temporaryPassword,
                'email_sent' => $emailSent,
                'activation_url' => $activationUrl,
            ]);
    }

    public function regenerateCoachOnboarding(User $user)
    {
        if ($user->role !== 'coach') {
            return back()->withErrors([
                'user_action' => 'Onboarding credentials can only be regenerated for coach accounts.',
            ]);
        }

        $temporaryPassword = Str::random(12);

        DB::transaction(function () use ($user, $temporaryPassword) {
            $user->update([
                'password' => Hash::make($temporaryPassword),
                'must_change_password' => true,
            ]);

            AccountApproval::create([
                'user_id' => $user->id,
                'admin_id' => Auth::id(),
                'decision' => 'approved',
                'remarks' => 'Coach onboarding credentials regenerated by admin.',
            ]);
        });

        $activationToken = Password::broker()->createToken($user);
        $activationUrl = route('coach.onboarding.activate', [
            'email' => $user->email,
            'token' => $activationToken,
        ]);

        $this->announcements->announce(
            $user->id,
            'Coach Credentials Updated',
            'Your coach onboarding credentials were refreshed by the administrator.',
            Announcement::TYPE_APPROVAL,
            Auth::id(),
            'notify_approvals',
            false
        );

        $emailSent = true;
        try {
            Mail::to($user->email)->send(new CoachOnboardingMail($user, $temporaryPassword, url('/Login'), $activationUrl));
        } catch (\Throwable $e) {
            $emailSent = false;
            Log::notice('Coach onboarding regeneration email not sent. Check mail credentials/settings.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'reason' => $e->getCode() ?: 'smtp_auth_or_transport',
            ]);
        }

        return back()
            ->with('success', 'Coach onboarding credentials regenerated.')
            ->with('coach_onboarding', [
                'email' => $user->email,
                'temporary_password' => $temporaryPassword,
                'email_sent' => $emailSent,
                'activation_url' => $activationUrl,
            ]);
    }

    private function dashboardRange(string $period): array
    {
        $now = now();
        $start = $now->copy()->startOfDay();
        $end = $now->copy()->endOfDay();

        if ($period === 'week') {
            $start = $now->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
            $end = $now->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();
        }

        if ($period === 'month') {
            $start = $now->copy()->startOfMonth()->startOfDay();
            $end = $now->copy()->endOfMonth()->endOfDay();
        }

        return [$start, $end];
    }

    private function attendanceSummary(CarbonInterface $start, CarbonInterface $end): array
    {
        $row = DB::table('team_schedules as ts')
            ->join('team_players as tp', 'tp.team_id', '=', 'ts.team_id')
            ->leftJoin('schedule_attendances as sa', function ($join) {
                $join->on('sa.schedule_id', '=', 'ts.id')
                    ->on('sa.student_id', '=', 'tp.student_id');
            })
            ->whereBetween('ts.start_time', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->selectRaw('COUNT(*) as total_rows')
            ->selectRaw("SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) as present_count")
            ->selectRaw('SUM(CASE WHEN sa.id IS NULL THEN 1 ELSE 0 END) as no_response_count')
            ->first();

        $total = (int) ($row->total_rows ?? 0);
        $present = (int) ($row->present_count ?? 0);
        $noResponse = (int) ($row->no_response_count ?? 0);

        return [
            'attendance_rate' => $total > 0 ? round(($present / $total) * 100, 2) : 0,
            'no_response' => $noResponse,
        ];
    }

    private function attendanceTrend(CarbonInterface $start, CarbonInterface $end): array
    {
        $rows = DB::table('team_schedules as ts')
            ->join('team_players as tp', 'tp.team_id', '=', 'ts.team_id')
            ->leftJoin('schedule_attendances as sa', function ($join) {
                $join->on('sa.schedule_id', '=', 'ts.id')
                    ->on('sa.student_id', '=', 'tp.student_id');
            })
            ->whereBetween('ts.start_time', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->selectRaw('DATE(ts.start_time) as schedule_date')
            ->selectRaw("SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) as present_count")
            ->selectRaw("SUM(CASE WHEN sa.status = 'late' THEN 1 ELSE 0 END) as late_count")
            ->selectRaw("SUM(CASE WHEN sa.status = 'absent' THEN 1 ELSE 0 END) as absent_count")
            ->selectRaw('SUM(CASE WHEN sa.id IS NULL THEN 1 ELSE 0 END) as no_response_count')
            ->groupByRaw('DATE(ts.start_time)')
            ->orderByRaw('DATE(ts.start_time)')
            ->get()
            ->keyBy('schedule_date');

        $labels = [];
        $series = [
            'present' => [],
            'late' => [],
            'absent' => [],
            'no_response' => [],
        ];

        $cursor = $start->copy()->startOfDay();
        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $labels[] = $cursor->format('M j');
            $series['present'][] = (int) ($rows[$key]->present_count ?? 0);
            $series['late'][] = (int) ($rows[$key]->late_count ?? 0);
            $series['absent'][] = (int) ($rows[$key]->absent_count ?? 0);
            $series['no_response'][] = (int) ($rows[$key]->no_response_count ?? 0);
            $cursor = $cursor->addDay();
        }

        return [
            'labels' => $labels,
            'series' => $series,
        ];
    }

    private function healthDistribution(): array
    {
        $today = now()->toDateString();
        $statusCaseSql = \App\Models\AthleteHealthClearance::statusCaseSql('ahc');

        $rows = DB::table('athlete_health_clearances as ahc')
            ->join(DB::raw('(SELECT student_id, MAX(id) as latest_id FROM athlete_health_clearances GROUP BY student_id) latest'), 'latest.latest_id', '=', 'ahc.id')
            ->selectRaw(
                "{$statusCaseSql} as status_key",
                [$today]
            )
            ->selectRaw('COUNT(*) as total_count')
            ->groupBy('status_key')
            ->get()
            ->keyBy('status_key');

        return [
            'fit' => (int) ($rows['fit']->total_count ?? 0),
            'fit_with_restrictions' => (int) ($rows['fit_with_restrictions']->total_count ?? 0),
            'not_fit' => (int) ($rows['not_fit']->total_count ?? 0),
            'expired' => (int) ($rows['expired']->total_count ?? 0),
        ];
    }

    private function academicByTeam(): array
    {
        $periodId = DB::table('academic_periods')->orderByDesc('starts_on')->value('id');
        if (!$periodId) {
            return [
                'rows' => [],
                'totals' => ['eligible' => 0, 'probation' => 0, 'ineligible' => 0],
            ];
        }

        $eligibleMax = \App\Models\AcademicEligibilityEvaluation::GPA_ELIGIBLE_MAX;
        $probationMax = \App\Models\AcademicEligibilityEvaluation::GPA_PROBATION_MAX;

        $rows = DB::table('academic_eligibility_evaluations as e')
            ->join('students as s', 's.id', '=', 'e.student_id')
            ->leftJoin('teams as t', function ($join) {
                $join->on('t.id', '=', DB::raw('(SELECT tp.team_id FROM team_players tp WHERE tp.student_id = s.id ORDER BY tp.id ASC LIMIT 1)'));
            })
            ->where('e.academic_period_id', $periodId)
            ->selectRaw("COALESCE(t.team_name, 'Unassigned') as team_name")
            ->selectRaw("SUM(CASE WHEN e.gpa IS NOT NULL AND e.gpa <= ? THEN 1 ELSE 0 END) as eligible_count", [$eligibleMax])
            ->selectRaw("SUM(CASE WHEN e.gpa IS NOT NULL AND e.gpa > ? AND e.gpa <= ? THEN 1 ELSE 0 END) as probation_count", [$eligibleMax, $probationMax])
            ->selectRaw("SUM(CASE WHEN e.gpa IS NOT NULL AND e.gpa > ? THEN 1 ELSE 0 END) as ineligible_count", [$probationMax])
            ->selectRaw("SUM(CASE WHEN e.gpa IS NOT NULL AND e.gpa > ? THEN 1 ELSE 0 END) as risk_count", [$eligibleMax])
            ->groupBy('team_name')
            ->orderByDesc('risk_count')
            ->orderBy('team_name')
            ->limit(8)
            ->get();

        return [
            'rows' => $rows->map(fn ($row) => [
                'team_name' => $row->team_name,
                'eligible' => (int) $row->eligible_count,
                'probation' => (int) $row->probation_count,
                'ineligible' => (int) $row->ineligible_count,
                'total' => (int) $row->eligible_count + (int) $row->probation_count + (int) $row->ineligible_count,
            ])->values(),
            'totals' => [
                'eligible' => (int) $rows->sum('eligible_count'),
                'probation' => (int) $rows->sum('probation_count'),
                'ineligible' => (int) $rows->sum('ineligible_count'),
            ],
        ];
    }

    private function attendanceHeatmap(CarbonInterface $start, CarbonInterface $end): array
    {
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            $dayExpr = 'EXTRACT(DOW FROM ts.start_time) + 1';
            $hourExpr = 'EXTRACT(HOUR FROM ts.start_time)';
        } elseif ($driver === 'sqlite') {
            $dayExpr = "CAST(strftime('%w', ts.start_time) AS INTEGER) + 1";
            $hourExpr = "CAST(strftime('%H', ts.start_time) AS INTEGER)";
        } else {
            $dayExpr = 'DAYOFWEEK(ts.start_time)';
            $hourExpr = 'HOUR(ts.start_time)';
        }

        $rows = DB::table('team_schedules as ts')
            ->join('team_players as tp', 'tp.team_id', '=', 'ts.team_id')
            ->leftJoin('schedule_attendances as sa', function ($join) {
                $join->on('sa.schedule_id', '=', 'ts.id')
                    ->on('sa.student_id', '=', 'tp.student_id');
            })
            ->whereBetween('ts.start_time', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->selectRaw("{$dayExpr} as day_index")
            ->selectRaw("{$hourExpr} as hour_key")
            ->selectRaw("SUM(CASE WHEN sa.status = 'late' THEN 1 ELSE 0 END) as late_count")
            ->selectRaw('SUM(CASE WHEN sa.id IS NULL THEN 1 ELSE 0 END) as no_response_count')
            ->groupByRaw("{$dayExpr}, {$hourExpr}")
            ->get();

        $hours = range(6, 21);
        $dayMap = [2 => 'Mon', 3 => 'Tue', 4 => 'Wed', 5 => 'Thu', 6 => 'Fri', 7 => 'Sat', 1 => 'Sun'];

        $cells = collect($rows)->map(fn ($row) => [
            'day' => $dayMap[(int) $row->day_index] ?? 'Mon',
            'hour' => (int) $row->hour_key,
            'late' => (int) $row->late_count,
            'no_response' => (int) $row->no_response_count,
            'value' => (int) $row->late_count + (int) $row->no_response_count,
        ])->values();

        return [
            'days' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            'hours' => $hours,
            'cells' => $cells,
        ];
    }

    private function todaySchedules(): array
    {
        return DB::table('team_schedules as ts')
            ->join('teams as t', 't.id', '=', 'ts.team_id')
            ->join('team_players as tp', 'tp.team_id', '=', 't.id')
            ->leftJoin('schedule_attendances as sa', function ($join) {
                $join->on('sa.schedule_id', '=', 'ts.id')
                    ->on('sa.student_id', '=', 'tp.student_id');
            })
            ->whereDate('ts.start_time', now()->toDateString())
            ->select([
                'ts.id',
                'ts.title',
                't.team_name',
                'ts.start_time',
            ])
            ->selectRaw('COUNT(tp.id) as roster_total')
            ->selectRaw("SUM(CASE WHEN sa.status = 'late' THEN 1 ELSE 0 END) as late_count")
            ->selectRaw("SUM(CASE WHEN sa.status = 'absent' THEN 1 ELSE 0 END) as absent_count")
            ->selectRaw('SUM(CASE WHEN sa.id IS NULL THEN 1 ELSE 0 END) as no_response_count')
            ->groupBy('ts.id', 'ts.title', 't.team_name', 'ts.start_time')
            ->orderBy('ts.start_time')
            ->limit(6)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'title' => $row->title,
                'team_name' => $row->team_name,
                'start_time' => Carbon::parse($row->start_time)->format('M j, g:i A'),
                'roster_total' => (int) $row->roster_total,
                'late' => (int) $row->late_count,
                'absent' => (int) $row->absent_count,
                'no_response' => (int) $row->no_response_count,
            ])
            ->values()
            ->all();
    }

    private function needsAttentionQueue(): array
    {
        $today = now()->toDateString();
        $statusCaseSql = \App\Models\AthleteHealthClearance::statusCaseSql('ahc');
        $eligibleMax = \App\Models\AcademicEligibilityEvaluation::GPA_ELIGIBLE_MAX;
        $probationMax = \App\Models\AcademicEligibilityEvaluation::GPA_PROBATION_MAX;

        $expired = DB::table('athlete_health_clearances as ahc')
            ->join(DB::raw('(SELECT student_id, MAX(id) as latest_id FROM athlete_health_clearances GROUP BY student_id) latest'), 'latest.latest_id', '=', 'ahc.id')
            ->join('students as s', 's.id', '=', 'ahc.student_id')
            ->join('users as su', 'su.id', '=', 's.user_id')
            ->whereRaw("{$statusCaseSql} = 'expired'", [$today])
            ->select([
                's.id as student_id',
                'su.first_name',
                'su.last_name',
            ])
            ->limit(4)
            ->get()
            ->map(fn ($row) => [
                'type' => 'health',
                'title' => trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')),
                'subtitle' => 'Expired health clearance',
                'action_label' => 'Review',
                'action_url' => '/health?tab=clearance',
                'priority' => 100,
            ]);

        $academic = DB::table('academic_eligibility_evaluations as e')
            ->join('students as s', 's.id', '=', 'e.student_id')
            ->join('users as su', 'su.id', '=', 's.user_id')
            ->whereNotNull('e.gpa')
            ->where('e.gpa', '>', $eligibleMax)
            ->orderByRaw("CASE WHEN e.gpa > {$probationMax} THEN 0 ELSE 1 END")
            ->orderByDesc('e.evaluated_at')
            ->limit(4)
            ->get([
                'su.first_name',
                'su.last_name',
                'e.gpa',
            ])
            ->map(fn ($row) => [
                'type' => 'academic',
                'title' => trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')),
                'subtitle' => strtoupper(\App\Models\AcademicEligibilityEvaluation::statusForGpa(
                    $row->gpa !== null ? (float) $row->gpa : null
                ) ?? 'pending') . ' academic status',
                'action_label' => 'Evaluate',
                'action_url' => '/academics',
                'priority' => ($row->gpa !== null && (float) $row->gpa > $probationMax) ? 95 : 85,
            ]);

        $pendingApprovals = User::query()
            ->where('status', 'pending')
            ->whereIn('role', ['student-athlete', 'student', 'coach'])
            ->latest('created_at')
            ->limit(4)
            ->get(['first_name', 'last_name'])
            ->map(fn ($row) => [
                'type' => 'people',
                'title' => trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')),
                'subtitle' => 'Pending account approval',
                'action_label' => 'Open Queue',
                'action_url' => '/people/queue',
                'priority' => 75,
            ]);

        return $expired
            ->concat($academic)
            ->concat($pendingApprovals)
            ->sortByDesc('priority')
            ->take(10)
            ->values()
            ->all();
    }

    private function recentActivityLog(): array
    {
        $roleScope = ['student', 'student-athlete', 'coach'];
        $driver = DB::getDriverName();
        $injuryExpr = $driver === 'pgsql' ? 'wl.injury_observed IS TRUE' : 'wl.injury_observed = 1';

        $attendance = DB::table('schedule_attendances as sa')
            ->join('users as actor', 'actor.id', '=', 'sa.recorded_by')
            ->leftJoin('students as st', 'st.id', '=', 'sa.student_id')
            ->leftJoin('users as su', 'su.id', '=', 'st.user_id')
            ->leftJoin('team_schedules as ts', 'ts.id', '=', 'sa.schedule_id')
            ->whereIn('actor.role', $roleScope)
            ->whereNotNull('sa.recorded_by')
            ->select([
                'sa.id as source_id',
                'actor.id as actor_id',
                DB::raw("TRIM(CONCAT(COALESCE(actor.first_name, ''), ' ', COALESCE(actor.last_name, ''))) as actor_name"),
                'actor.role as actor_role',
                DB::raw("'attendance' as action_type"),
                DB::raw("CONCAT('Recorded ', COALESCE(sa.status, 'attendance'), ' for ', COALESCE(su.first_name, ''), ' ', COALESCE(su.last_name, ''), CASE WHEN ts.title IS NOT NULL THEN CONCAT(' (', ts.title, ')') ELSE '' END) as description"),
                DB::raw('COALESCE(sa.recorded_at, sa.updated_at, sa.created_at) as happened_at'),
            ])
            ->limit(80)
            ->get();

        $wellness = DB::table('wellness_logs as wl')
            ->join('users as actor', 'actor.id', '=', 'wl.logged_by')
            ->leftJoin('students as st', 'st.id', '=', 'wl.student_id')
            ->leftJoin('users as su', 'su.id', '=', 'st.user_id')
            ->whereIn('actor.role', $roleScope)
            ->select([
                'wl.id as source_id',
                'actor.id as actor_id',
                DB::raw("TRIM(CONCAT(COALESCE(actor.first_name, ''), ' ', COALESCE(actor.last_name, ''))) as actor_name"),
                'actor.role as actor_role',
                DB::raw("'wellness' as action_type"),
                DB::raw("CONCAT('Logged wellness for ', COALESCE(su.first_name, ''), ' ', COALESCE(su.last_name, ''), CASE WHEN {$injuryExpr} THEN ' (injury observed)' ELSE '' END) as description"),
                DB::raw('COALESCE(wl.updated_at, wl.created_at) as happened_at'),
            ])
            ->limit(80)
            ->get();

        $academics = DB::table('academic_documents as ad')
            ->join('users as actor', 'actor.id', '=', 'ad.uploaded_by')
            ->leftJoin('students as st', 'st.id', '=', 'ad.student_id')
            ->leftJoin('users as su', 'su.id', '=', 'st.user_id')
            ->whereIn('actor.role', $roleScope)
            ->whereNotNull('ad.uploaded_by')
            ->select([
                'ad.id as source_id',
                'actor.id as actor_id',
                DB::raw("TRIM(CONCAT(COALESCE(actor.first_name, ''), ' ', COALESCE(actor.last_name, ''))) as actor_name"),
                'actor.role as actor_role',
                DB::raw("'academics' as action_type"),
                DB::raw("CONCAT('Uploaded ', REPLACE(ad.document_type, '_', ' '), ' for ', COALESCE(su.first_name, ''), ' ', COALESCE(su.last_name, '')) as description"),
                DB::raw('COALESCE(ad.uploaded_at, ad.updated_at, ad.created_at) as happened_at'),
            ])
            ->limit(80)
            ->get();

        $combined = $attendance
            ->concat($wellness)
            ->concat($academics)
            ->filter(fn ($row) => !empty($row->happened_at))
            ->sortByDesc('happened_at')
            ->take(60)
            ->values();

        $byRole = [
            'students' => $combined->filter(fn ($row) => in_array((string) $row->actor_role, ['student', 'student-athlete'], true))->count(),
            'coaches' => $combined->where('actor_role', 'coach')->count(),
        ];

        return [
            'items' => $combined->map(function ($row) {
                return [
                    'id' => (string) $row->action_type . '-' . (string) $row->source_id,
                    'actor_id' => (int) $row->actor_id,
                    'actor_name' => (string) $row->actor_name,
                    'actor_role' => (string) $row->actor_role,
                    'action_type' => (string) $row->action_type,
                    'description' => (string) $row->description,
                    'happened_at' => Carbon::parse($row->happened_at)->toIso8601String(),
                ];
            })->all(),
            'summary' => [
                'total' => $combined->count(),
                'students' => $byRole['students'],
                'coaches' => $byRole['coaches'],
            ],
        ];
    }

    private function sendAccountStatusMail(User $user, Mailable $mailable): void
    {
        if (!$this->announcements->shouldSendEmailNotification($user, 'notify_approvals', Auth::id())) {
            return;
        }

        try {
            Mail::to($user->email)->send($mailable);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
