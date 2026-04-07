<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\AcademicEligibilityEvaluation;
use App\Models\Student;

class AcademicEligibilityAccessService
{
    public function evaluate(Student $student): array
    {
        $relevantPeriod = $this->resolveRelevantPeriod();
        $latestEvaluation = null;

        if ($relevantPeriod) {
            $latestEvaluation = AcademicEligibilityEvaluation::query()
                ->with('academicPeriod:id,school_year,term,starts_on')
                ->where('student_id', $student->id)
                ->where('academic_period_id', $relevantPeriod->id)
                ->whereNotNull('gpa')
                ->orderByDesc('evaluated_at')
                ->first();
        }

        $status = $latestEvaluation?->status;
        $isRestricted = $status === 'ineligible';
        $period = $latestEvaluation?->academicPeriod ?? $relevantPeriod;
        $periodLabel = $period
            ? trim(sprintf('%s %s', (string) $period->school_year, (string) $period->term))
            : null;

        return [
            'is_restricted' => $isRestricted,
            'status' => $status,
            'message' => $isRestricted
                ? sprintf(
                    'You are currently academically ineligible%s. Varsity features are temporarily limited. Please review your Academics module.',
                    $periodLabel ? " for {$periodLabel}" : ''
                )
                : null,
            'evaluation' => $latestEvaluation ? [
                'id' => $latestEvaluation->id,
                'academic_period_id' => $latestEvaluation->academic_period_id,
                'gpa' => $latestEvaluation->gpa,
                'status' => $status,
                'remarks' => $latestEvaluation->remarks,
                'evaluated_at' => optional($latestEvaluation->evaluated_at)->toDateTimeString(),
                'period_label' => $periodLabel,
            ] : null,
        ];
    }

    private function resolveRelevantPeriod(): ?AcademicPeriod
    {
        $openPeriod = AcademicPeriod::query()
            ->open()
            ->orderByDesc('starts_on')
            ->first();

        if ($openPeriod) {
            return $openPeriod;
        }

        return AcademicPeriod::query()
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->first();
    }
}
