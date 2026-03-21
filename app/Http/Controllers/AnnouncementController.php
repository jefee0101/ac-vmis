<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $filter = $request->query('filter', 'all');

        $query = Announcement::query()
            ->where('user_id', $user->id)
            ->when($filter === 'unread', fn ($q) => $q->where('is_read', false))
            ->orderBy('is_read')
            ->latest('published_at')
            ->latest('created_at')
            ->with(['creator:id,name,role']);

        $rows = $query->paginate(12)->withQueryString();

        $rows = $rows->through(function ($a) {
                $normalizedType = \App\Models\Announcement::normalizeType($a->type);
                return [
                'id' => $a->id,
                'title' => $a->title,
                'message' => $a->message,
                'type' => $normalizedType,
                'type_label' => \App\Models\Announcement::labelForType($normalizedType),
                'is_read' => (bool) $a->is_read,
                'published_at' => optional($a->published_at)->toDateTimeString(),
                'read_at' => optional($a->read_at)->toDateTimeString(),
                'created_by' => $a->created_by,
                'created_by_name' => $a->creator?->name,
                'created_by_role' => $a->creator?->role,
                'source_label' => $a->creator
                    ? (ucfirst(str_replace('-', ' ', (string) $a->creator->role)) . ' · ' . $a->creator->name)
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

        if (!$announcement->is_read) {
            $announcement->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }

        return back();
    }

    public function markAllRead(Request $request)
    {
        $user = Auth::user();

        Announcement::query()
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return back();
    }
}
