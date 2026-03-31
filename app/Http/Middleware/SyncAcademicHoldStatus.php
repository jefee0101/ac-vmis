<?php

namespace App\Http\Middleware;

use App\Services\AcademicHoldService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SyncAcademicHoldStatus
{
    public function __construct(private AcademicHoldService $holdService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && in_array($user->role, ['student-athlete', 'student'], true) && $user->student) {
            $this->holdService->syncStudentStatus($user->student);
        }

        return $next($request);
    }
}
