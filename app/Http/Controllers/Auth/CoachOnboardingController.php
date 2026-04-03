<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;

class CoachOnboardingController extends Controller
{
    public function show(Request $request)
    {
        $email = (string) $request->query('email', '');
        $token = (string) $request->query('token', '');

        if ($email === '' || $token === '') {
            return redirect('/Login')->withErrors([
                'message' => 'Invalid activation link.',
            ]);
        }

        return Inertia::render('Auth/CoachActivate', [
            'email' => $email,
            'token' => $token,
        ]);
    }

    public function activate(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $status = Password::broker()->reset(
            [
                'email' => $validated['email'],
                'token' => $validated['token'],
                'password' => $validated['password'],
                'password_confirmation' => $validated['password_confirmation'],
            ],
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'must_change_password' => false,
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors([
                'password' => __($status),
            ]);
        }

        return redirect('/Login')->with('success', 'Password set successfully. You can now sign in.');
    }
}
