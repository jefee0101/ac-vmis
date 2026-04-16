<?php

namespace App\Http\Middleware;

use App\Services\AcademicEligibilityAccessService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

        try {
            $state = $this->eligibilityAccess->evaluate($user->student);
        } catch (\Throwable $e) {
            Log::warning('Academic eligibility gate failed.', [
                'user_id' => $user->id,
                'student_id' => $user->student?->id,
                'message' => $e->getMessage(),
            ]);

            return $next($request);
        }

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
