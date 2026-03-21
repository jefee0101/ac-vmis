<?php

namespace App\Http\Middleware;

use App\Models\Announcement;
use App\Models\UserSetting;
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
            ],
        ];
    }
}
