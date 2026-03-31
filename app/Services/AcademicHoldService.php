<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\AcademicDocument;
use App\Models\Student;
use App\Models\TeamPlayer;

class AcademicHoldService
{
    public function evaluate(Student $student): array
    {
        $openPeriodIds = AcademicPeriod::query()
            ->where('status', 'open')
            ->pluck('id');
        $hasActiveWindow = $openPeriodIds->isNotEmpty();
        $hasTeam = TeamPlayer::query()->where('student_id', $student->id)->exists();
        $hasSubmittedAll = false;

        if ($hasActiveWindow) {
            $submittedCount = AcademicDocument::query()
                ->where('student_id', $student->id)
                ->whereIn('academic_period_id', $openPeriodIds)
                ->distinct('academic_period_id')
                ->count('academic_period_id');
            $hasSubmittedAll = $submittedCount >= $openPeriodIds->count();
        }

        $status = null;
        if ($hasActiveWindow && !$hasSubmittedAll) {
            $status = $hasTeam ? 'Suspended' : 'Unenrolled';
        }

        return [
            'hasActiveWindow' => $hasActiveWindow,
            'hasTeam' => $hasTeam,
            'hasSubmittedAll' => $hasSubmittedAll,
            'status' => $status,
        ];
    }

    public function syncStudentStatus(Student $student): array
    {
        $state = $this->evaluate($student);

        if ($state['hasActiveWindow'] && !$state['hasSubmittedAll']) {
            $target = $state['status'];
            if ($target && !in_array($student->student_status, ['Dropped', 'Graduated'], true) && $student->student_status !== $target) {
                $student->update(['student_status' => $target]);
            }
        } else {
            if (in_array($student->student_status, ['Suspended', 'Unenrolled'], true)) {
                $student->update(['student_status' => 'Enrolled']);
            }
        }

        return $state;
    }
}
