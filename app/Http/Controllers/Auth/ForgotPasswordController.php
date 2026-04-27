<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;

class ForgotPasswordController extends Controller
{
    public function showLinkRequestForm()
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function sendResetLinkEmail(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::broker()->sendResetLink([
            'email' => $validated['email'],
        ]);

        if ($status !== Password::RESET_LINK_SENT) {
            return back()->withErrors([
                'email' => __($status),
            ]);
        }

        return back()->with('success', __($status));
    }

    public function showResetForm(Request $request, string $token)
    {
        $email = (string) $request->query('email', '');

        if ($token === '' || $email === '') {
            return redirect('/Login')->withErrors([
                'message' => 'The password reset link is invalid.',
            ]);
        }

        return Inertia::render('Auth/ResetPassword', [
            'email' => $email,
            'token' => $token,
        ]);
    }

    public function reset(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $status = Password::broker()->reset(
            [
                'token' => $validated['token'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'password_confirmation' => $validated['password_confirmation'],
            ],
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'must_change_password' => false,
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors([
                'password' => __($status),
            ]);
        }

        return redirect('/Login')->with('success', 'Your password has been reset successfully. You may now sign in.');
    }
}
