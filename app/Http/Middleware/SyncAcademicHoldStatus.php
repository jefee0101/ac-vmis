<?php

namespace App\Http\Middleware;

use App\Services\AcademicHoldService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
            try {
                $this->holdService->syncStudentStatus($user->student);
            } catch (\Throwable $e) {
                Log::warning('Academic hold sync failed.', [
                    'user_id' => $user->id,
                    'student_id' => $user->student?->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $next($request);
    }
}
