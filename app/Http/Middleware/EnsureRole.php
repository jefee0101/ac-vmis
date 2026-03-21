<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = Auth::user();

        if (!$user) {
            return redirect('/Login');
        }

        if ($user->status !== 'approved') {
            return redirect(match ($user->status) {
                'rejected' => '/rejected',
                'deactivated' => '/deactivated',
                default => '/pending-approval',
            });
        }

        if (!in_array($user->role, $roles, true)) {
            return redirect($this->dashboardPath((string) $user->role));
        }

        return $next($request);
    }

    private function dashboardPath(string $role): string
    {
        return match ($role) {
            'admin' => '/AdminDashboard',
            'coach' => '/coach/dashboard',
            'student-athlete', 'student' => '/StudentAthleteDashboard',
            default => '/',
        };
    }
}
