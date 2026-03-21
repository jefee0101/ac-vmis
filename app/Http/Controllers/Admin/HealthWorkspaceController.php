<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AthleteHealthClearance;
use App\Models\Team;
use App\Models\WellnessLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HealthWorkspaceController extends Controller
{
    public function index(Request $request)
    {
        $tab = (string) $request->query('tab', 'clearance');
        if (!in_array($tab, ['clearance', 'wellness'], true)) {
            $tab = 'clearance';
        }

        return Inertia::render('Admin/HealthWorkspace', [
            'tab' => $tab,
            'clearance' => $tab === 'clearance' ? $this->clearancePayload($request) : null,
            'wellness' => $tab === 'wellness' ? $this->wellnessPayload($request) : null,
        ]);
    }

    private function clearancePayload(Request $request): array
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');
        $validity = (string) $request->query('validity', 'all');
        $reviewed = (string) $request->query('reviewed', 'all');
        $perPage = max(10, min(100, (int) $request->query('per_page', 15)));

        $allowedStatuses = ['all', 'fit', 'fit_with_restrictions', 'not_fit', 'expired'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'all';
        }

        $allowedValidity = ['all', 'active', 'expiring_30', 'expired'];
        if (!in_array($validity, $allowedValidity, true)) {
            $validity = 'all';
        }

        $allowedReviewed = ['all', 'reviewed', 'unreviewed'];
        if (!in_array($reviewed, $allowedReviewed, true)) {
            $reviewed = 'all';
        }

        $today = now()->toDateString();
        $expiringLimit = now()->copy()->addDays(30)->toDateString();

        $applyFilters = function ($query) use ($search, $status, $validity, $reviewed, $today, $expiringLimit) {
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('student', function ($sq) use ($search) {
                        $sq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('student_id_number', 'like', "%{$search}%");
                    })->orWhere('physician_name', 'like', "%{$search}%");
                });
            }

            if ($status === 'expired') {
                $query->where(function ($q) use ($today) {
                    $q->where('clearance_status', 'expired')
                        ->orWhere(function ($sq) use ($today) {
                            $sq->whereNotNull('valid_until')
                                ->whereDate('valid_until', '<', $today);
                        });
                });
            } elseif ($status !== 'all') {
                $query->where('clearance_status', $status);
            }

            if ($validity === 'expired') {
                $query->whereNotNull('valid_until')->whereDate('valid_until', '<', $today);
            } elseif ($validity === 'expiring_30') {
                $query->whereNotNull('valid_until')
                    ->whereDate('valid_until', '>=', $today)
                    ->whereDate('valid_until', '<=', $expiringLimit);
            } elseif ($validity === 'active') {
                $query->where(function ($q) use ($today) {
                    $q->whereNull('valid_until')
                        ->orWhereDate('valid_until', '>=', $today);
                });
            }

            if ($reviewed === 'reviewed') {
                $query->whereNotNull('reviewed_at');
            } elseif ($reviewed === 'unreviewed') {
                $query->whereNull('reviewed_at');
            }
        };

        $base = AthleteHealthClearance::query()
            ->whereHas('student.user', function ($q) {
                $q->where('status', 'approved')
                    ->whereIn('role', ['student-athlete', 'student']);
            });
        $applyFilters($base);

        $stats = (clone $base)
            ->selectRaw('COUNT(*) as total_records')
            ->selectRaw("SUM(CASE WHEN (clearance_status = 'expired' OR (valid_until IS NOT NULL AND valid_until < ?)) THEN 1 ELSE 0 END) as expired_count", [$today])
            ->selectRaw("SUM(CASE WHEN (valid_until IS NOT NULL AND valid_until >= ? AND valid_until <= ?) THEN 1 ELSE 0 END) as expiring_30_count", [$today, $expiringLimit])
            ->selectRaw('SUM(CASE WHEN reviewed_at IS NOT NULL THEN 1 ELSE 0 END) as reviewed_count')
            ->first();

        $paginator = (clone $base)
            ->with(['student', 'reviewer'])
            ->latest('clearance_date')
            ->paginate($perPage)
            ->withQueryString();

        $rows = collect($paginator->items())
            ->map(function ($record) use ($today) {
                $student = $record->student;
                $isExpiredByDate = $record->valid_until && $record->valid_until->toDateString() < $today;
                $effectiveStatus = $isExpiredByDate ? 'expired' : $record->clearance_status;

                return [
                    'id' => $record->id,
                    'student_name' => trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')),
                    'student_id_number' => $student->student_id_number ?? null,
                    'clearance_date' => optional($record->clearance_date)->toDateString(),
                    'valid_until' => optional($record->valid_until)->toDateString(),
                    'physician_name' => $record->physician_name,
                    'clearance_status' => $effectiveStatus,
                    'certificate_url' => $record->id ? route('files.clearance', $record->id) : null,
                    'reviewed_at' => optional($record->reviewed_at)->toDateTimeString(),
                    'reviewed_by' => $record->reviewer?->name,
                ];
            })
            ->values();

        return [
            'records' => [
                'data' => $rows,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ],
            'filters' => [
                'search' => $search,
                'status' => $status,
                'validity' => $validity,
                'reviewed' => $reviewed,
                'per_page' => $perPage,
            ],
            'kpis' => [
                'total_records' => (int) ($stats->total_records ?? 0),
                'expired_count' => (int) ($stats->expired_count ?? 0),
                'expiring_30_count' => (int) ($stats->expiring_30_count ?? 0),
                'reviewed_count' => (int) ($stats->reviewed_count ?? 0),
            ],
        ];
    }

    private function wellnessPayload(Request $request): array
    {
        $search = trim((string) $request->query('search', ''));
        $injuryOnly = $request->boolean('injury_only', false);
        $fatigueMin = $request->filled('fatigue_min') ? (int) $request->query('fatigue_min') : null;
        $teamId = $request->filled('team_id') ? (int) $request->query('team_id') : null;
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $perPage = max(10, min(100, (int) $request->query('per_page', 15)));

        $applyFilters = function ($query) use ($search, $injuryOnly, $fatigueMin, $teamId, $startDate, $endDate) {
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('student', function ($sq) use ($search) {
                        $sq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('student_id_number', 'like', "%{$search}%");
                    })->orWhereHas('team', function ($tq) use ($search) {
                        $tq->where('team_name', 'like', "%{$search}%");
                    })->orWhereHas('schedule', function ($schq) use ($search) {
                        $schq->where('title', 'like', "%{$search}%");
                    });
                });
            }

            if ($injuryOnly) {
                $query->where('injury_observed', true);
            }

            if ($fatigueMin !== null && $fatigueMin >= 1 && $fatigueMin <= 5) {
                $query->where('fatigue_level', '>=', $fatigueMin);
            }

            if ($teamId) {
                $query->where('team_id', $teamId);
            }

            if (!empty($startDate)) {
                $query->whereDate('log_date', '>=', $startDate);
            }

            if (!empty($endDate)) {
                $query->whereDate('log_date', '<=', $endDate);
            }
        };

        $baseQuery = WellnessLog::query();
        $applyFilters($baseQuery);

        $stats = (clone $baseQuery)
            ->selectRaw('COUNT(*) as total_logs')
            ->selectRaw('SUM(CASE WHEN injury_observed = 1 THEN 1 ELSE 0 END) as injury_observed_count')
            ->selectRaw('AVG(fatigue_level) as avg_fatigue')
            ->first();

        $uniqueAthletes = (clone $baseQuery)
            ->distinct('student_id')
            ->count('student_id');

        $injuryObservedCount = (int) ($stats->injury_observed_count ?? 0);
        $injuryRatePercent = $uniqueAthletes > 0
            ? round(($injuryObservedCount / max(1, $uniqueAthletes)) * 100, 2)
            : 0.0;

        $injurySeverity = $this->injurySeverity($injuryRatePercent);
        $avgFatigue = round((float) ($stats->avg_fatigue ?? 0), 2);
        $fatigueSeverity = $this->fatigueSeverity($avgFatigue);

        $paginator = WellnessLog::query()
            ->with(['student', 'team', 'schedule', 'logger'])
            ->tap($applyFilters)
            ->latest('log_date')
            ->paginate($perPage)
            ->withQueryString();

        $logs = collect($paginator->items())
            ->map(function ($log) {
                $student = $log->student;
                return [
                    'id' => $log->id,
                    'log_date' => optional($log->log_date)->toDateString(),
                    'student_name' => trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')),
                    'student_id_number' => $student->student_id_number ?? null,
                    'team_name' => $log->team?->team_name,
                    'team_id' => $log->team?->id,
                    'schedule_title' => $log->schedule?->title,
                    'schedule_type' => $log->schedule?->type,
                    'injury_observed' => (bool) $log->injury_observed,
                    'fatigue_level' => $log->fatigue_level,
                    'performance_condition' => $log->performance_condition,
                    'logged_by' => $log->logger?->name,
                ];
            })
            ->values();

        return [
            'logs' => [
                'data' => $logs,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ],
            'filters' => [
                'search' => $search,
                'injury_only' => $injuryOnly,
                'fatigue_min' => $fatigueMin,
                'team_id' => $teamId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'per_page' => $perPage,
            ],
            'kpis' => [
                'total_logs' => (int) ($stats->total_logs ?? 0),
                'injury_observed_count' => $injuryObservedCount,
                'avg_fatigue' => $avgFatigue,
                'unique_athletes' => (int) $uniqueAthletes,
                'injury_severity' => $injurySeverity,
                'fatigue_severity' => $fatigueSeverity,
            ],
            'options' => [
                'teams' => Team::query()->orderBy('team_name')->get(['id', 'team_name']),
            ],
        ];
    }

    private function injurySeverity(float $injuryRatePercent): array
    {
        if ($injuryRatePercent >= 60) {
            return [
                'score' => (int) min(100, round($injuryRatePercent)),
                'label' => 'Critical',
                'description' => 'Injury observations are critically high versus active athletes. Immediate intervention recommended.',
            ];
        }

        if ($injuryRatePercent >= 40) {
            return [
                'score' => (int) min(100, round($injuryRatePercent)),
                'label' => 'High',
                'description' => 'Injury observations are high. Prioritize targeted recovery and workload adjustments.',
            ];
        }

        if ($injuryRatePercent >= 20) {
            return [
                'score' => (int) min(100, round($injuryRatePercent)),
                'label' => 'Moderate',
                'description' => 'Injury observations are moderate. Monitor athletes closely and review training load.',
            ];
        }

        return [
            'score' => (int) max(0, round($injuryRatePercent)),
            'label' => 'Low',
            'description' => 'Injury observations are currently low relative to active athletes.',
        ];
    }

    private function fatigueSeverity(float $avgFatigue): array
    {
        if ($avgFatigue >= 4.5) {
            return [
                'score' => 100,
                'label' => 'Critical',
                'description' => 'Average fatigue is at a critical level. Recovery blocks and intensity reduction are needed.',
                'scale' => '4.5 - 5.0',
            ];
        }

        if ($avgFatigue >= 3.5) {
            return [
                'score' => 80,
                'label' => 'High',
                'description' => 'Average fatigue is high. Schedule adjustments should be considered.',
                'scale' => '3.5 - 4.49',
            ];
        }

        if ($avgFatigue >= 2.5) {
            return [
                'score' => 55,
                'label' => 'Moderate',
                'description' => 'Average fatigue is moderate. Continue monitoring and individualize training loads.',
                'scale' => '2.5 - 3.49',
            ];
        }

        return [
            'score' => 30,
            'label' => 'Low',
            'description' => 'Average fatigue remains in a low and manageable range.',
            'scale' => '1.0 - 2.49',
        ];
    }
}
