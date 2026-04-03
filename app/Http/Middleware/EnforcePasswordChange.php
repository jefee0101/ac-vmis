<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->must_change_password) {
            return $next($request);
        }

        if ($request->isMethod('GET') && $request->is('account/account-settings')) {
            return $next($request);
        }

        if ($request->isMethod('PUT') && $request->is('account/password')) {
            return $next($request);
        }

        if ($request->isMethod('POST') && $request->is('logout')) {
            return $next($request);
        }

        return redirect('/account/account-settings?force=1')
            ->with('error', 'You must update your password before continuing.');
    }
}
