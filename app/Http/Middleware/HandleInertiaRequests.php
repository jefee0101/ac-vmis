<?php

namespace App\Http\Middleware;

use App\Models\AcademicDocument;
use App\Models\AcademicPeriod;
use App\Models\Announcement;
use App\Models\AthleteHealthClearance;
use App\Models\ScheduleAttendance;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\TeamSchedule;
use App\Models\User;
use App\Models\UserSetting;
use App\Models\WellnessLog;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'settings' => [
                    'theme_preference' => fn () => $request->user()
                        ? (UserSetting::query()
                            ->where('user_id', $request->user()->id)
                            ->value('theme_preference') ?? 'light')
                        : null,
                ],
                'announcements' => [
                    'unread_count' => fn () => $request->user()
                        ? Cache::remember(
                            'announcements:unread:' . $request->user()->id,
                            now()->addSeconds(60),
                            fn () => Announcement::query()
                                ->where('user_id', $request->user()->id)
                                ->where('is_read', false)
                                ->count()
                        )
                        : 0,
                ],
                'admin_notifications' => fn () => $request->user() && $request->user()->role === 'admin'
                    ? Cache::remember(
                        'admin:notifications:' . $request->user()->id,
                        now()->addSeconds(60),
                        function () use ($request) {
                            $adminId = $request->user()->id;
                            $now = now();

                            $pendingAccounts = User::query()
                                ->where('status', 'pending')
                                ->whereIn('role', ['student-athlete', 'student', 'coach'])
                                ->count();

                            $teamChangeRequests = Announcement::query()
                                ->where('user_id', $adminId)
                                ->where('title', 'Team Change Request')
                                ->where('is_read', false)
                                ->count();

                            $scheduleCount = TeamSchedule::query()
                                ->whereBetween('start_time', [$now, (clone $now)->addDays(7)])
                                ->count();

                            $clearanceCount = AthleteHealthClearance::query()
                                ->whereNull('reviewed_at')
                                ->count();

                            $wellnessCount = WellnessLog::query()
                                ->whereDate('log_date', '>=', (clone $now)->subDays(7)->toDateString())
                                ->count();

                            $academicPeriodId = AcademicPeriod::query()
                                ->where('status', 'open')
                                ->orderByDesc('starts_on')
                                ->value('id');

                            $academicSubmissions = AcademicDocument::query()
                                ->when($academicPeriodId, function ($query, $periodId) {
                                    $query->where('academic_period_id', $periodId);
                                })
                                ->whereDoesntHave('evaluations')
                                ->count();

                            $periodReminder = AcademicPeriod::query()
                                ->where('status', 'open')
                                ->whereNotNull('ends_on')
                                ->whereDate('ends_on', '>=', $now->toDateString())
                                ->whereDate('ends_on', '<=', (clone $now)->addDays(14)->toDateString())
                                ->count();

                            $items = [
                                [
                                    'key' => 'pending_accounts',
                                    'label' => 'Newly pending accounts',
                                    'count' => $pendingAccounts,
                                    'href' => '/people/queue',
                                ],
                                [
                                    'key' => 'team_change_requests',
                                    'label' => 'Team change requests',
                                    'count' => $teamChangeRequests,
                                    'href' => '/teams',
                                ],
                                [
                                    'key' => 'schedules',
                                    'label' => 'Schedules',
                                    'count' => $scheduleCount,
                                    'href' => '/operations',
                                ],
                                [
                                    'key' => 'clearance_wellness',
                                    'label' => 'Clearance & wellness',
                                    'count' => $clearanceCount + $wellnessCount,
                                    'href' => '/health',
                                ],
                                [
                                    'key' => 'academic_submissions',
                                    'label' => 'Academic submissions from students',
                                    'count' => $academicSubmissions,
                                    'href' => '/academics/submissions',
                                ],
                                [
                                    'key' => 'period_reminder',
                                    'label' => 'Period nearly done reminder',
                                    'count' => $periodReminder,
                                    'href' => '/academics',
                                ],
                            ];

                            return [
                                'total' => array_sum(array_map(fn ($item) => (int) $item['count'], $items)),
                                'items' => $items,
                                'recent' => Announcement::query()
                                    ->where('user_id', $adminId)
                                    ->orderByDesc('published_at')
                                    ->orderByDesc('id')
                                    ->limit(6)
                                    ->get()
                                    ->map(function (Announcement $announcement) {
                                        return [
                                            'id' => $announcement->id,
                                            'title' => $announcement->title,
                                            'message' => Str::limit((string) $announcement->message, 140),
                                            'type' => $announcement->type,
                                            'is_read' => (bool) $announcement->is_read,
                                            'published_at' => $announcement->published_at?->diffForHumans(),
                                        ];
                                    })
                                    ->values(),
                            ];
                        }
                    )
                    : null,
                'coach_notifications' => fn () => $request->user() && $request->user()->role === 'coach'
                    ? Cache::remember(
                        'coach:notifications:' . $request->user()->id,
                        now()->addSeconds(60),
                        function () use ($request) {
                            $coach = $request->user()?->coach;
                            if (!$coach) {
                                return null;
                            }

                            $now = now();
                            $teamIds = Team::query()
                                ->where('coach_id', $coach->id)
                                ->orWhere('assistant_coach_id', $coach->id)
                                ->pluck('id')
                                ->all();

                            if (empty($teamIds)) {
                                return [
                                    'total' => 0,
                                    'items' => [],
                                    'recent' => [],
                                ];
                            }

                            $studentIds = TeamPlayer::query()
                                ->whereIn('team_id', $teamIds)
                                ->pluck('student_id')
                                ->unique()
                                ->values()
                                ->all();

                            $academicPeriodId = AcademicPeriod::query()
                                ->where('status', 'open')
                                ->orderByDesc('starts_on')
                                ->value('id');

                            $studentSubmissions = $academicPeriodId
                                ? AcademicDocument::query()
                                    ->where('academic_period_id', $academicPeriodId)
                                    ->whereIn('student_id', $studentIds)
                                    ->count()
                                : 0;

                            $recentWindow = (clone $now)->subDays(30);
                            $teamCreated = Team::query()
                                ->whereIn('id', $teamIds)
                                ->whereDate('created_at', '>=', $recentWindow->toDateString())
                                ->count();

                            $rosterWindow = (clone $now)->subDays(14);
                            $playerAdds = TeamPlayer::query()
                                ->whereIn('team_id', $teamIds)
                                ->whereDate('created_at', '>=', $rosterWindow->toDateString())
                                ->count();

                            $assistantAdds = Team::query()
                                ->whereIn('id', $teamIds)
                                ->whereNotNull('assistant_coach_id')
                                ->whereDate('updated_at', '>=', $rosterWindow->toDateString())
                                ->count();

                            $statusWindow = (clone $now)->subDays(7);
                            $statusUpdates = ScheduleAttendance::query()
                                ->whereHas('schedule', fn ($q) => $q->whereIn('team_id', $teamIds))
                                ->whereDate('updated_at', '>=', $statusWindow->toDateString())
                                ->count();

                            $items = [
                                [
                                    'key' => 'student_submissions',
                                    'label' => 'Student submissions',
                                    'count' => $studentSubmissions,
                                    'href' => '/coach/academics',
                                ],
                                [
                                    'key' => 'team_created',
                                    'label' => 'Team created for coach',
                                    'count' => $teamCreated,
                                    'href' => '/coach/team',
                                ],
                                [
                                    'key' => 'roster_updates',
                                    'label' => 'Roster updates (assistant + players)',
                                    'count' => $playerAdds + $assistantAdds,
                                    'href' => '/coach/team',
                                ],
                                [
                                    'key' => 'student_status',
                                    'label' => 'Student status from a schedule',
                                    'count' => $statusUpdates,
                                    'href' => '/coach/operations',
                                ],
                            ];

                            return [
                                'total' => array_sum(array_map(fn ($item) => (int) $item['count'], $items)),
                                'items' => $items,
                                'recent' => Announcement::query()
                                    ->where('user_id', $request->user()->id)
                                    ->orderByDesc('published_at')
                                    ->orderByDesc('id')
                                    ->limit(6)
                                    ->get()
                                    ->map(function (Announcement $announcement) {
                                        return [
                                            'id' => $announcement->id,
                                            'title' => $announcement->title,
                                            'message' => Str::limit((string) $announcement->message, 140),
                                            'type' => $announcement->type,
                                            'is_read' => (bool) $announcement->is_read,
                                            'published_at' => $announcement->published_at?->diffForHumans(),
                                        ];
                                    })
                                    ->values(),
                            ];
                        }
                    )
                    : null,
                'student_notifications' => fn () => $request->user() && in_array($request->user()->role, ['student', 'student-athlete'], true)
                    ? Cache::remember(
                        'student:notifications:' . $request->user()->id,
                        now()->addSeconds(60),
                        function () use ($request) {
                            $studentId = $request->user()->id;

                            return [
                                'recent' => Announcement::query()
                                    ->where('user_id', $studentId)
                                    ->orderByDesc('published_at')
                                    ->orderByDesc('id')
                                    ->limit(6)
                                    ->get()
                                    ->map(function (Announcement $announcement) {
                                        return [
                                            'id' => $announcement->id,
                                            'title' => $announcement->title,
                                            'message' => Str::limit((string) $announcement->message, 140),
                                            'type' => $announcement->type,
                                            'is_read' => (bool) $announcement->is_read,
                                            'published_at' => $announcement->published_at?->diffForHumans(),
                                        ];
                                    })
                                    ->values(),
                            ];
                        }
                    )
                    : null,
            ],
        ];
    }
}
