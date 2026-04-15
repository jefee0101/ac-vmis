<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $filter = $request->query('filter', 'all');

        $query = Announcement::query()
            ->join('announcement_events as ae', 'ae.id', '=', 'announcement_recipients.event_id')
            ->select('announcement_recipients.*')
            ->where('user_id', $user->id)
            ->when($filter === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->orderByRaw('announcement_recipients.read_at IS NULL DESC')
            ->orderByDesc('ae.published_at')
            ->orderByDesc('announcement_recipients.id')
            ->with(['event.creator:id,first_name,middle_name,last_name,role']);

        $rows = $query->paginate(12)->withQueryString();

        $rows = $rows->through(function ($a) {
                $normalizedType = \App\Models\Announcement::normalizeType($a->type);
                return [
                'id' => $a->id,
                'title' => $a->title,
                'message' => $a->message,
                'type' => $normalizedType,
                'type_label' => \App\Models\Announcement::labelForType($normalizedType),
                'is_read' => !empty($a->read_at),
                'published_at' => optional($a->published_at)->toDateTimeString(),
                'read_at' => optional($a->read_at)->toDateTimeString(),
                'created_by' => $a->created_by,
                'created_by_name' => $a->event?->creator?->name,
                'created_by_role' => $a->event?->creator?->role,
                'source_label' => $a->event?->creator
                    ? (ucfirst(str_replace('-', ' ', (string) $a->event->creator->role)) . ' · ' . $a->event->creator->name)
                    : 'System',
            ];
        });

        $component = match ($user->role) {
            'admin' => 'Admin/Announcements',
            'coach' => 'Coaches/Announcements',
            'student-athlete', 'student' => 'StudentAthletes/Announcements',
            default => 'Public/Welcome',
        };

        return Inertia::render($component, [
            'announcements' => $rows,
            'filters' => [
                'filter' => $filter,
            ],
        ]);
    }

    public function markRead(Announcement $announcement)
    {
        $user = Auth::user();
        abort_unless($announcement->user_id === $user->id, 403, 'Unauthorized announcement action.');

        if (empty($announcement->read_at)) {
            $announcement->update([
                'read_at' => now(),
            ]);
        }

        Cache::forget('announcements:unread:' . $user->id);
        Cache::forget('admin:notifications:' . $user->id);

        return back();
    }

    public function markAllRead(Request $request)
    {
        $user = Auth::user();

        Announcement::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
            ]);

        Cache::forget('announcements:unread:' . $user->id);
        Cache::forget('admin:notifications:' . $user->id);

        return back();
    }
}
