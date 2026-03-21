<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TeamSchedule;
use Carbon\Carbon;
use Inertia\Inertia;

class ScheduleOverviewController extends Controller
{
    public function index()
    {
        $schedules = TeamSchedule::with(['team.sport'])
            ->orderBy('start_time')
            ->get()
            ->map(function ($schedule) {
                return [
                    'id' => $schedule->id,
                    'title' => $schedule->title,
                    'type' => $schedule->type,
                    'venue' => $schedule->venue,
                    'team_name' => $schedule->team?->team_name,
                    'sport' => $schedule->team?->sport?->name ?? 'Unknown',
                    'start' => Carbon::parse($schedule->start_time)->toIso8601String(),
                    'end' => Carbon::parse($schedule->end_time)->toIso8601String(),
                    'notes' => $schedule->notes,
                ];
            });

        return Inertia::render('Admin/OperationsSchedule', [
            'schedules' => $schedules,
        ]);
    }
}
