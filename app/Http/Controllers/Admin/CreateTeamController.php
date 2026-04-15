<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Coach;
use App\Models\Sport;
use App\Models\Student;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\TeamSchedule;
use App\Services\AnnouncementService;
use App\Services\SecureUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CreateTeamController extends Controller
{
    public function __construct(
        private SecureUploadService $secureUpload,
        private AnnouncementService $announcements,
    ) {
    }

    public function create()
    {
        return Inertia::render('Admin/TeamsForm', $this->buildFormPayload());
    }

    public function store(Request $request)
    {
        $this->authorizeMutation();

        $validated = $request->validate([
            'team_name' => 'required|string|max:255',
            'team_avatar' => 'nullable|image|max:4096',
            'sport_id' => 'required|exists:sports,id',
            'year' => 'required|digits:4',
            'coach_id' => 'required|exists:coaches,id',
            'assistant_coach_id' => 'nullable|exists:coaches,id|different:coach_id',
            'players' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $playerIds = $this->decodePlayerIds($validated['players'] ?? '[]');
        $maxPlayers = $this->maxPlayersForSport((int) $validated['sport_id']);
        if ($playerIds->count() > $maxPlayers) {
            throw ValidationException::withMessages([
                'players' => "Selected players exceed the max allowed for this sport ({$maxPlayers}).",
            ]);
        }

        $this->validateCoachAssignmentConflicts(
            (int) $validated['coach_id'],
            isset($validated['assistant_coach_id']) ? (int) $validated['assistant_coach_id'] : null,
            null
        );

        $this->validatePlayerConflicts($playerIds, null);

        $team = DB::transaction(function () use ($request, $validated, $playerIds) {
            $avatarPath = null;
            if ($request->hasFile('team_avatar')) {
                $avatarPath = $this->secureUpload->storePublic(
                    $request->file('team_avatar'),
                    'team_avatars',
                    'team_avatar'
                );
            }

            $team = Team::create([
                'team_name' => $validated['team_name'],
                'team_avatar' => $avatarPath,
                'sport_id' => $validated['sport_id'],
                'year' => $validated['year'],
                'description' => $validated['description'] ?? null,
                'archived_at' => null,
                'archived_by' => null,
            ]);

            $team->syncStaffAssignments(
                (int) $validated['coach_id'],
                isset($validated['assistant_coach_id']) ? (int) $validated['assistant_coach_id'] : null,
                auth()->id()
            );

            foreach ($playerIds as $studentId) {
                TeamPlayer::create([
                    'team_id' => $team->id,
                    'student_id' => $studentId,
                    'jersey_number' => null,
                    'athlete_position' => null,
                ]);
            }

            return $team;
        });

        $team->load('sport');
        $this->notifyTeamAssignmentAdded($team, (int) $validated['coach_id'], 'head coach');
        $this->notifyTeamAssignmentAdded($team, $validated['assistant_coach_id'] ? (int) $validated['assistant_coach_id'] : null, 'assistant coach');
        $this->notifyPlayersAdded($team, $playerIds->all());
        return redirect('/teams')->with('success', 'Team created successfully.');
    }

    public function edit(Team $team)
    {
        return Inertia::render('Admin/TeamsForm', $this->buildFormPayload($team));
    }

    public function update(Request $request, Team $team)
    {
        $this->authorizeMutation();

        $oldCoachId = (int) $team->coach_id;
        $oldAssistantCoachId = $team->assistant_coach_id ? (int) $team->assistant_coach_id : null;
        $existingPlayerIds = TeamPlayer::where('team_id', $team->id)
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $validated = $request->validate([
            'team_name' => 'required|string|max:255',
            'team_avatar' => 'nullable|image|max:4096',
            'sport_id' => 'required|exists:sports,id',
            'year' => 'required|digits:4',
            'coach_id' => 'required|exists:coaches,id',
            'assistant_coach_id' => 'nullable|exists:coaches,id|different:coach_id',
            'players' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $playerIds = $this->decodePlayerIds($validated['players'] ?? '[]');
        $maxPlayers = $this->maxPlayersForSport((int) $validated['sport_id']);
        if ($playerIds->count() > $maxPlayers) {
            throw ValidationException::withMessages([
                'players' => "Selected players exceed the max allowed for this sport ({$maxPlayers}).",
            ]);
        }

        $this->validateCoachAssignmentConflicts(
            (int) $validated['coach_id'],
            isset($validated['assistant_coach_id']) ? (int) $validated['assistant_coach_id'] : null,
            $team->id
        );

        $this->validatePlayerConflicts($playerIds, $team->id);

        DB::transaction(function () use ($request, $validated, $playerIds, $team) {
            $avatarPath = $team->team_avatar;
            if ($request->hasFile('team_avatar')) {
                $newAvatarPath = $this->secureUpload->storePublic(
                    $request->file('team_avatar'),
                    'team_avatars',
                    'team_avatar'
                );

                if (!empty($team->team_avatar) && $team->team_avatar !== $newAvatarPath) {
                    Storage::disk('public')->delete($team->team_avatar);
                }
                $avatarPath = $newAvatarPath;
            }

            $team->update([
                'team_name' => $validated['team_name'],
                'team_avatar' => $avatarPath,
                'sport_id' => $validated['sport_id'],
                'year' => $validated['year'],
                'description' => $validated['description'] ?? null,
            ]);

            $team->syncStaffAssignments(
                (int) $validated['coach_id'],
                isset($validated['assistant_coach_id']) ? (int) $validated['assistant_coach_id'] : null,
                auth()->id()
            );

            TeamPlayer::where('team_id', $team->id)
                ->whereNotIn('student_id', $playerIds->all())
                ->delete();

            $existingIds = TeamPlayer::where('team_id', $team->id)
                ->pluck('student_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $missingIds = $playerIds->diff($existingIds)->all();
            foreach ($missingIds as $studentId) {
                TeamPlayer::create([
                    'team_id' => $team->id,
                    'student_id' => $studentId,
                    'jersey_number' => null,
                    'athlete_position' => null,
                ]);
            }
        });

        $newCoachId = (int) $validated['coach_id'];
        $newAssistantCoachId = $validated['assistant_coach_id'] ? (int) $validated['assistant_coach_id'] : null;
        $addedPlayerIds = array_values(array_diff($playerIds->all(), $existingPlayerIds));
        $removedPlayerIds = array_values(array_diff($existingPlayerIds, $playerIds->all()));

        $team->refresh()->load('sport');
        if ($oldCoachId !== $newCoachId) {
            $this->notifyTeamAssignmentRemoved($team, $oldCoachId, 'head coach');
            $this->notifyTeamAssignmentAdded($team, $newCoachId, 'head coach');
        }

        if ($oldAssistantCoachId !== $newAssistantCoachId) {
            $this->notifyTeamAssignmentRemoved($team, $oldAssistantCoachId, 'assistant coach');
            $this->notifyTeamAssignmentAdded($team, $newAssistantCoachId, 'assistant coach');
        }

        $this->notifyPlayersAdded($team, $addedPlayerIds);
        $this->notifyPlayersRemoved($team, $removedPlayerIds);

        return redirect('/teams')->with('success', 'Team updated successfully.');
    }

    public function teamSetup(Request $request)
    {
        $sportMaxMap = Sport::query()
            ->get(['id', 'name'])
            ->mapWithKeys(fn ($sport) => [$sport->id => $this->maxPlayersForSport((int) $sport->id)]);

        $search = trim((string) $request->string('search', ''));
        $sportId = $request->filled('sport_id') ? (int) $request->input('sport_id') : null;
        $year = $request->filled('year') ? (string) $request->input('year') : null;
        $coachStatus = (string) $request->input('coach_status', 'all');
        $rosterStatus = (string) $request->input('roster_status', 'all');
        $sort = (string) $request->input('sort', 'updated_at');
        $direction = strtolower((string) $request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $baseQuery = Team::query()
            ->with([
                'sport:id,name',
                'coach.user',
                'assistantCoach.user',
                'headCoachAssignment',
                'assistantCoachAssignment',
            ])
            ->withCount('players')
            ->whereNull('archived_at');

        if ($search !== '') {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('team_name', 'like', "%{$search}%")
                    ->orWhere('year', 'like', "%{$search}%")
                    ->orWhereHas('sport', fn ($sportQuery) => $sportQuery->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('coach.user', fn ($coachQuery) => $coachQuery
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%"))
                    ->orWhereHas('assistantCoach.user', fn ($assistantQuery) => $assistantQuery
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%"));
            });
        }

        if ($sportId) {
            $baseQuery->where('sport_id', $sportId);
        }

        if ($year) {
            $baseQuery->where('year', $year);
        }

        if ($coachStatus === 'missing_assistant') {
            $baseQuery->missingAssistant();
        }

        if ($coachStatus === 'complete_staff') {
            $baseQuery->withAssistant();
        }

        if ($rosterStatus !== 'all') {
            $teamIds = (clone $baseQuery)
                ->get(['id', 'sport_id', 'players_count'])
                ->filter(function ($team) use ($sportMaxMap, $rosterStatus) {
                    $status = $this->rosterStatusForCounts(
                        (int) $team->players_count,
                        (int) $team->sport_id,
                        $sportMaxMap
                    );

                    return $status['key'] === $rosterStatus;
                })
                ->pluck('id')
                ->all();

            if (empty($teamIds)) {
                $baseQuery->whereRaw('1 = 0');
            } else {
                $baseQuery->whereIn('id', $teamIds);
            }
        }

        $sortMap = [
            'team_name' => 'team_name',
            'sport' => 'sport_id',
            'year' => 'year',
            'players' => 'players_count',
            'updated_at' => 'updated_at',
        ];

        $sortColumn = $sortMap[$sort] ?? 'updated_at';
        $baseQuery->orderBy($sortColumn, $direction)->orderBy('id', 'desc');

        $teams = $baseQuery->get();

        $coachConflictData = $this->detectCoachConflicts();
        $playerConflictData = $this->detectPlayerConflicts();
        $teamIssueCounts = $this->buildTeamIssueCounts($coachConflictData, $playerConflictData);

        $teams = $teams->map(
            fn (Team $team) => $this->serializeTeamSummary($team, $sportMaxMap, $teamIssueCounts)
        );

        $requestTitles = [
            'Team Change Request',
            'Assistant Coach Request',
            'Assistant Coach Removal Request',
            'Athlete Add Request',
            'Athlete Removal Request',
        ];
        $teamChangeRequests = Announcement::query()
            ->join('announcement_events as ae', 'ae.id', '=', 'announcement_recipients.event_id')
            ->select('announcement_recipients.*')
            ->with('event.creator:id,first_name,middle_name,last_name')
            ->where('user_id', auth()->id())
            ->whereIn('ae.title', $requestTitles)
            ->orderByDesc('ae.published_at')
            ->limit(12)
            ->get()
            ->map(function (Announcement $announcement) {
                return [
                    'id' => $announcement->id,
                    'title' => $announcement->title,
                    'message' => $announcement->message,
                    'is_read' => !empty($announcement->read_at),
                    'published_at' => $announcement->published_at?->toDateTimeString(),
                    'requested_by' => $announcement->event?->creator?->name,
                ];
            })
            ->values();

        return Inertia::render('Admin/TeamsIndex', [
            'teams' => [
                'data' => $teams,
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $teams->count(),
                    'total' => $teams->count(),
                ],
            ],
            'filters' => [
                'search' => $search,
                'sport_id' => $sportId,
                'year' => $year,
                'coach_status' => $coachStatus,
                'roster_status' => $rosterStatus,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $teams->count(),
                'tab' => (string) $request->input('tab', 'all-teams'),
            ],
            'options' => [
                'sports' => Sport::query()->orderBy('name')->get(['id', 'name']),
                'years' => Team::query()->select('year')->whereNotNull('year')->distinct()->orderByDesc('year')->pluck('year'),
            ],
            'conflicts' => [
                'coach' => $coachConflictData,
                'player' => $playerConflictData,
            ],
            'readOnly' => auth()->user()?->role !== 'admin',
            'teamChangeRequests' => $teamChangeRequests,
        ]);
    }

    public function archivedTeams(Request $request)
    {
        $sportMaxMap = Sport::query()
            ->get(['id', 'name'])
            ->mapWithKeys(fn ($sport) => [$sport->id => $this->maxPlayersForSport((int) $sport->id)]);

        $search = trim((string) $request->string('search', ''));
        $sportId = $request->filled('sport_id') ? (int) $request->input('sport_id') : null;
        $year = $request->filled('year') ? (string) $request->input('year') : null;
        $sort = (string) $request->input('sort', 'updated_at');
        $direction = strtolower((string) $request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = Team::query()
            ->with([
                'sport:id,name',
                'coach.user',
                'assistantCoach.user',
                'headCoachAssignment',
                'assistantCoachAssignment',
            ])
            ->withCount('players')
            ->whereNotNull('archived_at');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('team_name', 'like', "%{$search}%")
                    ->orWhere('year', 'like', "%{$search}%")
                    ->orWhereHas('sport', fn ($sportQuery) => $sportQuery->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('coach.user', fn ($coachQuery) => $coachQuery
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%"))
                    ->orWhereHas('assistantCoach.user', fn ($assistantQuery) => $assistantQuery
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%"));
            });
        }

        if ($sportId) {
            $query->where('sport_id', $sportId);
        }

        if ($year) {
            $query->where('year', $year);
        }

        $sortMap = [
            'team_name' => 'team_name',
            'sport' => 'sport_id',
            'year' => 'year',
            'players' => 'players_count',
            'updated_at' => 'updated_at',
        ];

        $sortColumn = $sortMap[$sort] ?? 'updated_at';
        $query->orderBy($sortColumn, $direction)->orderByDesc('archived_at');

        $teams = $query->get()->map(
            fn (Team $team) => $this->serializeTeamSummary($team, $sportMaxMap, [])
        );

        return Inertia::render('Admin/TeamsArchived', [
            'teams' => [
                'data' => $teams,
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $teams->count(),
                    'total' => $teams->count(),
                ],
            ],
            'filters' => [
                'search' => $search,
                'sport_id' => $sportId,
                'year' => $year,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'options' => [
                'sports' => Sport::query()->orderBy('name')->get(['id', 'name']),
                'years' => Team::query()
                    ->whereNotNull('archived_at')
                    ->select('year')
                    ->whereNotNull('year')
                    ->distinct()
                    ->orderByDesc('year')
                    ->pluck('year'),
            ],
            'readOnly' => auth()->user()?->role !== 'admin',
        ]);
    }

    public function roster(Team $team): JsonResponse
    {
        $team->load(['players.student:id,user_id,student_id_number']);
        return response()->json([
            'team_id' => $team->id,
            'players' => $team->players
                ->map(fn ($player) => [
                    'id' => $player->id,
                    'student_id' => $player->student_id,
                    'name' => trim(($player->student->first_name ?? '') . ' ' . ($player->student->last_name ?? '')),
                    'student_id_number' => $player->student->student_id_number ?? null,
                    'jersey_number' => $player->jersey_number,
                    'athlete_position' => $player->athlete_position,
                ])
                ->values(),
        ]);
    }

    public function printRoster(Team $team)
    {
        $team->load([
            'sport:id,name',
            'coach.user',
            'assistantCoach.user',
            'players.student:id,user_id,student_id_number',
        ]);

        $players = $team->players
            ->sortBy(function ($player) {
                return strtolower(($player->student?->last_name ?? '') . ' ' . ($player->student?->first_name ?? ''));
            })
            ->values()
            ->map(fn ($player) => [
                'name' => trim(($player->student?->first_name ?? '') . ' ' . ($player->student?->last_name ?? '')),
                'student_id_number' => $player->student?->student_id_number,
                'jersey_number' => $player->jersey_number,
                'athlete_position' => $player->athlete_position,
                'player_status' => $player->player_status ?? 'active',
            ])
            ->all();

        return view('print.team-roster', [
            'contextLabel' => 'Admin Report',
            'generatedAt' => now()->format('M j, Y g:i A'),
            'team' => [
                'team_name' => $team->team_name,
                'sport' => $team->sport?->name ?? 'Unknown',
                'year' => $team->year,
                'coach' => trim(($team->coach?->first_name ?? '') . ' ' . ($team->coach?->last_name ?? '')) ?: 'Unassigned',
                'assistant' => trim(($team->assistantCoach?->first_name ?? '') . ' ' . ($team->assistantCoach?->last_name ?? '')) ?: 'Unassigned',
            ],
            'players' => $players,
        ]);
    }

    public function archive(Team $team)
    {
        $this->authorizeMutation();

        if ($team->archived_at) {
            return back()->with('warning', 'Team is already archived.');
        }

        $team->update([
            'archived_at' => now(),
            'archived_by' => auth()->id(),
        ]);

        return back()->with('success', 'Team archived.');
    }

    public function reactivate(Team $team)
    {
        $this->authorizeMutation();

        if (!$team->archived_at) {
            return back()->with('warning', 'Team is already active.');
        }

        $team->update([
            'archived_at' => null,
            'archived_by' => null,
        ]);

        return back()->with('success', 'Team reactivated.');
    }

    private function buildFormPayload(?Team $team = null): array
    {
        $editingTeamId = $team?->id;

        $coachTeamLoad = Team::query()
            ->whereNull('archived_at')
            ->with(['sport:id,name', 'headCoachAssignment', 'assistantCoachAssignment'])
            ->get(['id', 'team_name', 'sport_id', 'year']);

        $coachWorkloads = [];
        $coachAvailabilityMap = [];
        foreach ($coachTeamLoad as $t) {
            if ($editingTeamId && $t->id === $editingTeamId) {
                continue;
            }

            $teamLabel = $t->team_name;
            if ($t->sport?->name) {
                $teamLabel .= " ({$t->sport->name})";
            }
            if (!empty($t->year)) {
                $teamLabel .= " - {$t->year}";
            }

            if ($t->coach_id) {
                $coachWorkloads[$t->coach_id][] = [
                    'team_id' => $t->id,
                    'role' => 'Head Coach',
                    'team_name' => $t->team_name,
                    'sport_id' => $t->sport_id,
                    'year' => $t->year,
                ];

                if (!isset($coachAvailabilityMap[$t->coach_id])) {
                    $coachAvailabilityMap[$t->coach_id] = [
                        'is_available' => false,
                        'assigned_team_id' => (int) $t->id,
                        'assigned_role' => 'Head Coach',
                        'unavailable_reason' => "Assigned as Head Coach to {$teamLabel}.",
                    ];
                }
            }
            if ($t->assistant_coach_id) {
                $coachWorkloads[$t->assistant_coach_id][] = [
                    'team_id' => $t->id,
                    'role' => 'Assistant Coach',
                    'team_name' => $t->team_name,
                    'sport_id' => $t->sport_id,
                    'year' => $t->year,
                ];

                if (!isset($coachAvailabilityMap[$t->assistant_coach_id])) {
                    $coachAvailabilityMap[$t->assistant_coach_id] = [
                        'is_available' => false,
                        'assigned_team_id' => (int) $t->id,
                        'assigned_role' => 'Assistant Coach',
                        'unavailable_reason' => "Assigned as Assistant Coach to {$teamLabel}.",
                    ];
                }
            }
        }

        $playerAssignmentRows = TeamPlayer::query()
            ->join('teams', 'teams.id', '=', 'team_players.team_id')
            ->leftJoin('sports', 'sports.id', '=', 'teams.sport_id')
            ->whereNull('teams.archived_at')
            ->when($editingTeamId, fn ($query) => $query->where('teams.id', '!=', $editingTeamId))
            ->orderBy('teams.id')
            ->get([
                'team_players.student_id',
                'teams.id as team_id',
                'teams.team_name',
                'teams.year',
                'sports.name as sport_name',
            ]);

        $playerAvailabilityMap = [];
        foreach ($playerAssignmentRows as $row) {
            $studentId = (int) $row->student_id;
            if (isset($playerAvailabilityMap[$studentId])) {
                continue;
            }

            $teamLabel = (string) $row->team_name;
            if (!empty($row->sport_name)) {
                $teamLabel .= " ({$row->sport_name})";
            }
            if (!empty($row->year)) {
                $teamLabel .= " - {$row->year}";
            }

            $playerAvailabilityMap[$studentId] = [
                'is_available' => false,
                'assigned_team_id' => (int) $row->team_id,
                'unavailable_reason' => "Assigned to {$teamLabel}.",
            ];
        }

        $coaches = Coach::query()
            ->with('user')
            ->select('id', 'user_id', 'coach_status')
            ->orderBy('id')
            ->get()
            ->map(function ($c) use ($coachAvailabilityMap) {
                $availability = $coachAvailabilityMap[$c->id] ?? null;
                return [
                    'id' => $c->id,
                    'name' => $this->resolveCoachDisplayName($c),
                    'status' => $c->coach_status,
                    'email' => $c->user?->email,
                    'is_available' => $availability['is_available'] ?? true,
                    'assigned_team_id' => $availability['assigned_team_id'] ?? null,
                    'assigned_role' => $availability['assigned_role'] ?? null,
                    'unavailable_reason' => $availability['unavailable_reason'] ?? null,
                ];
            })->values();

        $players = Student::query()
            ->with('user')
            ->select('id', 'user_id', 'student_id_number', 'current_grade_level')
            ->orderBy('id')
            ->get()
            ->map(function ($p) use ($playerAvailabilityMap) {
                $availability = $playerAvailabilityMap[$p->id] ?? null;
                return [
                    'id' => $p->id,
                    'name' => $this->resolveStudentDisplayName($p),
                    'student_id_number' => $p->student_id_number,
                    'academic_level_label' => $p->academic_level_label,
                    'email' => $p->user?->email,
                    'is_available' => $availability['is_available'] ?? true,
                    'assigned_team_id' => $availability['assigned_team_id'] ?? null,
                    'unavailable_reason' => $availability['unavailable_reason'] ?? null,
                ];
            })->values();

        $selectedTeam = null;
        if ($team) {
            $team->load(['players.student', 'coach', 'assistantCoach', 'headCoachAssignment', 'assistantCoachAssignment']);

            if ($team->coach && !$coaches->contains('id', $team->coach->id)) {
                $coaches->push([
                    'id' => $team->coach->id,
                    'name' => $this->resolveCoachDisplayName($team->coach),
                    'status' => $team->coach->coach_status ?? null,
                    'email' => $team->coach->user?->email,
                    'is_available' => true,
                    'assigned_team_id' => null,
                    'assigned_role' => null,
                    'unavailable_reason' => null,
                ]);
            }
            if ($team->assistantCoach && !$coaches->contains('id', $team->assistantCoach->id)) {
                $coaches->push([
                    'id' => $team->assistantCoach->id,
                    'name' => $this->resolveCoachDisplayName($team->assistantCoach),
                    'status' => $team->assistantCoach->coach_status ?? null,
                    'email' => $team->assistantCoach->user?->email,
                    'is_available' => true,
                    'assigned_team_id' => null,
                    'assigned_role' => null,
                    'unavailable_reason' => null,
                ]);
            }

            foreach ($team->players as $player) {
                if ($player->student && !$players->contains('id', $player->student->id)) {
                    $players->push([
                        'id' => $player->student->id,
                        'name' => $this->resolveStudentDisplayName($player->student),
                        'student_id_number' => $player->student->student_id_number,
                        'academic_level_label' => $player->student->academic_level_label,
                        'email' => $player->student->user?->email,
                        'is_available' => true,
                        'assigned_team_id' => null,
                        'unavailable_reason' => null,
                    ]);
                }
            }

            $selectedTeam = [
                'id' => $team->id,
                'team_name' => $team->team_name,
                'team_avatar' => $team->team_avatar,
                'sport_id' => $team->sport_id,
                'year' => (string) $team->year,
                'head_coach' => $team->coach ? [
                    'id' => $team->coach->id,
                    'name' => $this->resolveCoachDisplayName($team->coach),
                ] : null,
                'assistant_coach' => $team->assistantCoach ? [
                    'id' => $team->assistantCoach->id,
                    'name' => $this->resolveCoachDisplayName($team->assistantCoach),
                ] : null,
                'description' => $team->description,
                'player_ids' => $team->players->pluck('student_id')->values(),
            ];
        }

        $sports = Sport::orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($sport) => [
                'id' => $sport->id,
                'name' => $sport->name,
                'max_players' => $this->maxPlayersForSport((int) $sport->id),
            ])->values();

        return [
            'coaches' => $coaches->values(),
            'players' => $players->values(),
            'sports' => $sports,
            'selectedTeam' => $selectedTeam,
            'coachWorkloads' => $coachWorkloads,
        ];
    }

    private function resolveCoachDisplayName(Coach $coach): string
    {
        $profileName = trim((string) ($coach->full_name ?? ''));
        if ($profileName !== '') {
            return $profileName;
        }

        $user = $coach->user;
        $userName = trim((string) ($user?->full_name ?? ''));
        if ($userName !== '') {
            return $userName;
        }

        return (string) ($user?->email ?: "Coach #{$coach->id}");
    }

    private function resolveStudentDisplayName(Student $student): string
    {
        $profileName = trim((string) ($student->full_name ?? ''));
        if ($profileName !== '') {
            return $profileName;
        }

        $user = $student->user;
        $userName = trim((string) ($user?->full_name ?? ''));
        if ($userName !== '') {
            return $userName;
        }

        if (!empty($student->student_id_number)) {
            return "Student {$student->student_id_number}";
        }

        return (string) ($user?->email ?: "Student #{$student->id}");
    }

    private function maxPlayersForSport(int $sportId): int
    {
        $sportName = strtolower((string) Sport::where('id', $sportId)->value('name'));

        return match ($sportName) {
            'basketball' => 15,
            'volleyball' => 14,
            'football' => 30,
            'badminton' => 8,
            'table tennis' => 6,
            default => 25,
        };
    }

    private function notifyTeamAssignmentAdded(Team $team, ?int $coachId, string $roleLabel): void
    {
        if (!$coachId) {
            return;
        }

        $userId = Coach::where('id', $coachId)->value('user_id');
        if (!$userId) {
            return;
        }

        $this->createAnnouncement(
            (int) $userId,
            'Team Assignment',
            "You were assigned as {$roleLabel} for team {$this->teamLabel($team)}.",
            'notify_schedule_changes'
        );
    }

    private function notifyTeamAssignmentRemoved(Team $team, ?int $coachId, string $roleLabel): void
    {
        if (!$coachId) {
            return;
        }

        $userId = Coach::where('id', $coachId)->value('user_id');
        if (!$userId) {
            return;
        }

        $this->createAnnouncement(
            (int) $userId,
            'Team Assignment Updated',
            "You were removed as {$roleLabel} from team {$this->teamLabel($team)}.",
            'notify_schedule_changes'
        );
    }

    private function notifyPlayersAdded(Team $team, array $studentIds): void
    {
        if (empty($studentIds)) {
            return;
        }

        $userIds = Student::whereIn('id', $studentIds)->pluck('user_id')->filter()->unique()->values();
        foreach ($userIds as $userId) {
            $this->createAnnouncement(
                (int) $userId,
                'Team Assignment',
                "You were added to team {$this->teamLabel($team)}.",
                'notify_attendance_exceptions'
            );
        }

        $coachUserIds = collect([
            ...Coach::whereIn('id', $team->activeCoachIds())->pluck('user_id')->filter()->all(),
        ])->filter()->unique()->values();

        if ($coachUserIds->isNotEmpty()) {
            $count = count($studentIds);
            $label = $count === 1 ? 'athlete' : 'athletes';
            foreach ($coachUserIds as $coachUserId) {
                $this->createAnnouncement(
                    (int) $coachUserId,
                    'Roster Updated',
                    "{$count} {$label} were added to {$this->teamLabel($team)}.",
                    'notify_attendance_exceptions'
                );
            }
        }
    }

    private function notifyPlayersRemoved(Team $team, array $studentIds): void
    {
        if (empty($studentIds)) {
            return;
        }

        $userIds = Student::whereIn('id', $studentIds)->pluck('user_id')->filter()->unique()->values();
        foreach ($userIds as $userId) {
            $this->createAnnouncement(
                (int) $userId,
                'Team Assignment Updated',
                "You were removed from team {$this->teamLabel($team)}.",
                'notify_attendance_exceptions'
            );
        }

        $coachUserIds = collect([
            ...Coach::whereIn('id', $team->activeCoachIds())->pluck('user_id')->filter()->all(),
        ])->filter()->unique()->values();

        if ($coachUserIds->isNotEmpty()) {
            $count = count($studentIds);
            $label = $count === 1 ? 'athlete' : 'athletes';
            foreach ($coachUserIds as $coachUserId) {
                $this->createAnnouncement(
                    (int) $coachUserId,
                    'Roster Updated',
                    "{$count} {$label} were removed from {$this->teamLabel($team)}.",
                    'notify_attendance_exceptions'
                );
            }
        }
    }

    private function createAnnouncement(
        int $userId,
        string $title,
        string $message,
        ?string $notificationPreference = null
    ): void
    {
        $this->announcements->announce(
            $userId,
            $title,
            $message,
            'system',
            auth()->id(),
            $notificationPreference
        );
    }

    private function teamLabel(Team $team): string
    {
        $sportName = $team->sport?->name ? " ({$team->sport->name})" : '';
        return "{$team->team_name}{$sportName}";
    }

    private function decodePlayerIds(string $playersJson): Collection
    {
        $players = json_decode($playersJson, true);
        if (!is_array($players)) {
            throw ValidationException::withMessages([
                'players' => 'Invalid player selection payload.',
            ]);
        }

        $playerIds = collect($players)
            ->pluck('student_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        return $playerIds;
    }

    private function validateCoachAssignmentConflicts(int $headCoachId, ?int $assistantCoachId, ?int $ignoreTeamId): void
    {
        $coachIds = array_filter([$headCoachId, $assistantCoachId]);
        if (empty($coachIds)) {
            return;
        }

        $query = Team::query()
            ->whereNull('archived_at')
            ->whereHas('activeStaffAssignments', function ($q) use ($coachIds) {
                $q->whereIn('coach_id', $coachIds);
            });

        if ($ignoreTeamId) {
            $query->where('id', '!=', $ignoreTeamId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'coach_id' => 'Coach assignment conflict: one selected coach is already assigned to another active team.',
            ]);
        }
    }

    private function validatePlayerConflicts(Collection $playerIds, ?int $ignoreTeamId): void
    {
        if ($playerIds->isEmpty()) {
            return;
        }

        $query = TeamPlayer::query()
            ->join('teams', 'teams.id', '=', 'team_players.team_id')
            ->whereNull('teams.archived_at')
            ->whereIn('team_players.student_id', $playerIds->all());

        if ($ignoreTeamId) {
            $query->where('teams.id', '!=', $ignoreTeamId);
        }

        $conflictingStudentIds = $query
            ->distinct()
            ->pluck('team_players.student_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (!empty($conflictingStudentIds)) {
            $names = Student::query()
                ->join('users', 'users.id', '=', 'students.user_id')
                ->whereIn('students.id', $conflictingStudentIds)
                ->get(['users.first_name', 'users.last_name'])
                ->map(fn ($student) => trim($student->first_name . ' ' . $student->last_name))
                ->values()
                ->all();

            throw ValidationException::withMessages([
                'players' => 'Player conflict: one or more selected players are already assigned to another active team. ' . implode(', ', array_slice($names, 0, 4)),
            ]);
        }
    }

    private function serializeTeamSummary(Team $team, Collection $sportMaxMap, array $issueCounts): array
    {
        $playersCount = (int) ($team->players_count ?? 0);
        $sportId = (int) $team->sport_id;
        $roster = $this->rosterStatusForCounts($playersCount, $sportId, $sportMaxMap);

        return [
            'id' => $team->id,
            'team_name' => $team->team_name,
            'team_avatar' => $team->team_avatar,
            'sport' => $team->sport,
            'year' => $team->year,
            'head_coach' => $team->coach ? [
                'id' => $team->coach->id,
                'name' => $this->resolveCoachDisplayName($team->coach),
            ] : null,
            'assistant_coach_member' => $team->assistantCoach ? [
                'id' => $team->assistantCoach->id,
                'name' => $this->resolveCoachDisplayName($team->assistantCoach),
            ] : null,
            'coach' => $team->coach,
            'assistantCoach' => $team->assistantCoach,
            'players_count' => $playersCount,
            'max_players' => $roster['max_players'],
            'roster_health' => $roster,
            'is_archived' => !is_null($team->archived_at),
            'archived_at' => $team->archived_at,
            'issue_count' => $issueCounts[$team->id] ?? 0,
            'updated_at' => $team->updated_at,
        ];
    }

    private function rosterStatusForCounts(int $playersCount, int $sportId, Collection $sportMaxMap): array
    {
        $maxPlayers = (int) ($sportMaxMap[$sportId] ?? 25);

        if ($playersCount > $maxPlayers) {
            return [
                'key' => 'over_limit',
                'label' => 'Over Limit',
                'tone' => 'danger',
                'max_players' => $maxPlayers,
            ];
        }

        if ($playersCount < $maxPlayers) {
            return [
                'key' => 'needs_players',
                'label' => 'Needs Players',
                'tone' => 'warning',
                'max_players' => $maxPlayers,
            ];
        }

        return [
            'key' => 'complete',
            'label' => 'Complete',
            'tone' => 'success',
            'max_players' => $maxPlayers,
        ];
    }

    private function detectCoachConflicts(): array
    {
        $schedules = TeamSchedule::query()
            ->with(['team.activeStaffAssignments'])
            ->whereHas('team', fn ($query) => $query->whereNull('archived_at'))
            ->whereNotNull('team_schedules.start_time')
            ->whereNotNull('team_schedules.end_time')
            ->get([
                'team_schedules.team_id',
                'team_schedules.start_time',
                'team_schedules.end_time',
            ]);

        $byCoach = [];
        foreach ($schedules as $schedule) {
            $start = $schedule->start_time;
            $end = $schedule->end_time;
            if (!$start || !$end) {
                continue;
            }

            $team = $schedule->team;
            if (!$team) {
                continue;
            }

            foreach ($team->activeStaffAssignments as $assignment) {
                if (!$assignment->coach_id) {
                    continue;
                }

                $roleLabel = $assignment->role === 'head' ? 'Head Coach' : 'Assistant Coach';
                $byCoach[$assignment->coach_id][] = [
                    'team_id' => (int) $schedule->team_id,
                    'team_name' => $team->team_name,
                    'role' => $roleLabel,
                    'start_time' => $start,
                    'end_time' => $end,
                ];
            }
        }

        $coachNames = Coach::query()
            ->join('users', 'users.id', '=', 'coaches.user_id')
            ->whereIn('coaches.id', array_keys($byCoach))
            ->get(['coaches.id', 'users.first_name', 'users.last_name'])
            ->mapWithKeys(fn ($coach) => [$coach->id => trim($coach->first_name . ' ' . $coach->last_name)]);

        $conflicts = [];
        foreach ($byCoach as $coachId => $slots) {
            $count = count($slots);
            if ($count < 2) {
                continue;
            }

            for ($i = 0; $i < $count - 1; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $a = $slots[$i];
                    $b = $slots[$j];
                    if ($a['team_id'] === $b['team_id']) {
                        continue;
                    }

                    if ($a['start_time'] < $b['end_time'] && $b['start_time'] < $a['end_time']) {
                        $conflicts[] = [
                            'coach_id' => (int) $coachId,
                            'coach_name' => $coachNames[$coachId] ?? 'Unknown Coach',
                            'team_a_id' => $a['team_id'],
                            'team_a_name' => $a['team_name'],
                            'team_b_id' => $b['team_id'],
                            'team_b_name' => $b['team_name'],
                            'window' => date('M d, Y h:i A', strtotime((string) $a['start_time'])) . ' - ' . date('M d, Y h:i A', strtotime((string) $a['end_time'])),
                        ];
                    }
                }
            }
        }

        return collect($conflicts)
            ->unique(fn ($item) => $item['coach_id'] . '-' . min($item['team_a_id'], $item['team_b_id']) . '-' . max($item['team_a_id'], $item['team_b_id']) . '-' . $item['window'])
            ->values()
            ->all();
    }

    private function detectPlayerConflicts(): array
    {
        $duplicates = TeamPlayer::query()
            ->join('teams', 'teams.id', '=', 'team_players.team_id')
            ->whereNull('teams.archived_at')
            ->select('team_players.student_id', 'teams.sport_id', DB::raw('COUNT(DISTINCT teams.id) as teams_count'))
            ->groupBy('team_players.student_id', 'teams.sport_id')
            ->havingRaw('COUNT(DISTINCT teams.id) > 1')
            ->get();

        if ($duplicates->isEmpty()) {
            return [];
        }

        $studentIds = $duplicates->pluck('student_id')->unique()->all();
        $sportIds = $duplicates->pluck('sport_id')->unique()->all();

        $studentNames = Student::query()
            ->join('users', 'users.id', '=', 'students.user_id')
            ->whereIn('students.id', $studentIds)
            ->get(['students.id', 'users.first_name', 'users.last_name'])
            ->mapWithKeys(fn ($student) => [$student->id => trim($student->first_name . ' ' . $student->last_name)]);

        $sportNames = Sport::query()
            ->whereIn('id', $sportIds)
            ->get(['id', 'name'])
            ->mapWithKeys(fn ($sport) => [$sport->id => $sport->name]);

        $teamRows = TeamPlayer::query()
            ->join('teams', 'teams.id', '=', 'team_players.team_id')
            ->whereNull('teams.archived_at')
            ->whereIn('team_players.student_id', $studentIds)
            ->whereIn('teams.sport_id', $sportIds)
            ->get([
                'team_players.student_id',
                'teams.sport_id',
                'teams.id as team_id',
                'teams.team_name',
            ]);

        $teamsByKey = [];
        foreach ($teamRows as $row) {
            $key = $row->student_id . ':' . $row->sport_id;
            $teamsByKey[$key][] = [
                'team_id' => (int) $row->team_id,
                'team_name' => $row->team_name,
            ];
        }

        return $duplicates
            ->map(function ($item) use ($studentNames, $sportNames, $teamsByKey) {
                $key = $item->student_id . ':' . $item->sport_id;
                return [
                    'student_id' => (int) $item->student_id,
                    'student_name' => $studentNames[$item->student_id] ?? 'Unknown Player',
                    'sport_id' => (int) $item->sport_id,
                    'sport_name' => $sportNames[$item->sport_id] ?? 'Unknown Sport',
                    'teams_count' => (int) $item->teams_count,
                    'teams' => collect($teamsByKey[$key] ?? [])->unique('team_id')->values(),
                ];
            })
            ->values()
            ->all();
    }

    private function buildTeamIssueCounts(array $coachConflicts, array $playerConflicts): array
    {
        $issueCounts = [];

        foreach ($coachConflicts as $item) {
            $issueCounts[$item['team_a_id']] = ($issueCounts[$item['team_a_id']] ?? 0) + 1;
            $issueCounts[$item['team_b_id']] = ($issueCounts[$item['team_b_id']] ?? 0) + 1;
        }

        foreach ($playerConflicts as $item) {
            foreach ($item['teams'] as $team) {
                $teamId = (int) ($team['team_id'] ?? 0);
                if ($teamId > 0) {
                    $issueCounts[$teamId] = ($issueCounts[$teamId] ?? 0) + 1;
                }
            }
        }

        return $issueCounts;
    }

    private function authorizeMutation(): void
    {
        if (auth()->user()?->role !== 'admin') {
            abort(403, 'Only admins can mutate team data.');
        }
    }
}
