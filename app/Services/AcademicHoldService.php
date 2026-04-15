<?php

namespace App\Services;

use App\Models\AcademicHold;
use App\Models\AcademicPeriod;
use App\Models\AcademicDocument;
use App\Models\Student;
use App\Models\TeamPlayer;

class AcademicHoldService
{
    public function evaluate(Student $student): array
    {
        $openPeriodIds = AcademicPeriod::query()
            ->open()
            ->pluck('id');
        $hasActiveWindow = $openPeriodIds->isNotEmpty();
        $hasTeam = TeamPlayer::query()->where('student_id', $student->id)->exists();
        $hasSubmittedAll = false;

        if ($hasActiveWindow) {
            $submittedCount = AcademicDocument::query()
                ->periodSubmission()
                ->where('student_id', $student->id)
                ->whereIn('academic_period_id', $openPeriodIds)
                ->distinct('academic_period_id')
                ->count('academic_period_id');
            $hasSubmittedAll = $submittedCount >= $openPeriodIds->count();
        }

        $status = null;
        $reason = null;
        $sourcePeriodId = $hasActiveWindow ? (int) ($openPeriodIds->sortDesc()->first() ?? 0) : null;
        if ($hasActiveWindow && !$hasSubmittedAll) {
            $status = $hasTeam ? 'Suspended' : 'Unenrolled';
            $reason = AcademicHold::REASON_MISSING_SUBMISSIONS;
        }

        return [
            'hasActiveWindow' => $hasActiveWindow,
            'hasTeam' => $hasTeam,
            'hasSubmittedAll' => $hasSubmittedAll,
            'status' => $status,
            'reason' => $reason,
            'source_period_id' => $sourcePeriodId ?: null,
        ];
    }

    public function syncStudentStatus(Student $student): array
    {
        $state = $this->evaluate($student);
        $openHold = AcademicHold::query()
            ->where('student_id', $student->id)
            ->whereIn('status', ['suspended', 'unenrolled'])
            ->orderByDesc('started_at')
            ->first();

        if ($state['hasActiveWindow'] && !$state['hasSubmittedAll']) {
            $target = strtolower((string) ($state['status'] ?? ''));

            if ($target !== '') {
                AcademicHold::query()->updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'source_period_id' => $state['source_period_id'],
                        'status' => $target,
                        'resolved_at' => null,
                    ],
                    [
                        'reason' => $state['reason'] ?? AcademicHold::REASON_MISSING_SUBMISSIONS,
                        'started_at' => $openHold?->started_at ?? now(),
                    ]
                );
            }
        } elseif ($openHold) {
            $openHold->update([
                'status' => 'resolved',
                'resolved_at' => now(),
            ]);
        }

        return $state;
    }
}
