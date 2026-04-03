<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        // Validate
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'remember' => 'nullable|boolean',
        ]);

        $remember = (bool) ($credentials['remember'] ?? false);
        unset($credentials['remember']);

        // Attempt login
        if (!Auth::attempt($credentials, $remember)) {
            return back()->withErrors([
                'message' => 'Invalid email or password.',
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::user();

        // Approval check
        if ($user->status !== 'approved') {
            Auth::logout();
            return redirect(match ($user->status) {
                'rejected' => '/rejected',
                'deactivated' => '/deactivated',
                default => '/pending-approval',
            });
        }

        if ($user->must_change_password) {
            return redirect('/account/account-settings?force=1')
                ->with('error', 'Please update your password before accessing other pages.');
        }

        // Redirect by role
        if ($user->role === 'admin') {
            return redirect('/AdminDashboard');
        } elseif ($user->role === 'coach') {
            return redirect('/coach/dashboard');
        } elseif (in_array($user->role, ['student-athlete', 'student'], true)) {
            return redirect('/StudentAthleteDashboard');
        } else {
            Auth::logout();
            return redirect('/Login')->withErrors([
                'message' => 'Your account role is not supported.',
            ]);
        }
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}
