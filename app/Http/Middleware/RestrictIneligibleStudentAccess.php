<?php

namespace App\Http\Middleware;

use App\Services\AcademicEligibilityAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictIneligibleStudentAccess
{
    public function __construct(private AcademicEligibilityAccessService $eligibilityAccess)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !in_array($user->role, ['student-athlete', 'student'], true) || !$user->student) {
            return $next($request);
        }

        $state = $this->eligibilityAccess->evaluate($user->student);
        if (!$state['is_restricted']) {
            return $next($request);
        }

        $message = (string) ($state['message'] ?? 'Academic access is currently restricted.');

        if ($request->expectsJson() || !$request->isMethod('GET')) {
            abort(403, $message);
        }

        return redirect()->route('AcademicSubmissions')->with('error', $message);
    }
}
