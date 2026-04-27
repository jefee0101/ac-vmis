<?php

namespace App\Services;

use App\Models\AcademicDocument;
use App\Models\AcademicEligibilityEvaluation;
use App\Models\AcademicPeriod;
use App\Models\TeamPlayer;
use App\Models\WellnessLog;

class TeamPlayerStatusService
{
    public function sync(TeamPlayer $teamPlayer): string
    {
        $teamPlayer->loadMissing('team');

        $status = $this->determineStatus($teamPlayer);

        if ((string) ($teamPlayer->player_status ?? TeamPlayer::STATUS_ACTIVE) !== $status) {
            $teamPlayer->forceFill([
                'player_status' => $status,
            ])->save();
        }

        return $status;
    }

    public function syncStudent(int $studentId): void
    {
        TeamPlayer::query()
            ->where('student_id', $studentId)
            ->get()
            ->each(fn (TeamPlayer $teamPlayer) => $this->sync($teamPlayer));
    }

    public function syncAll(): void
    {
        TeamPlayer::query()
            ->with('team')
            ->chunkById(100, function ($players) {
                $players->each(fn (TeamPlayer $teamPlayer) => $this->sync($teamPlayer));
            });
    }

    public function setInactiveOverride(TeamPlayer $teamPlayer, bool $inactive): string
    {
        $teamPlayer->forceFill([
            'manual_inactive' => $inactive,
            'player_status' => $inactive ? TeamPlayer::STATUS_INACTIVE : TeamPlayer::STATUS_ACTIVE,
        ])->save();

        if (!$inactive) {
            return $this->sync($teamPlayer->fresh(['team']));
        }

        return TeamPlayer::STATUS_INACTIVE;
    }

    private function determineStatus(TeamPlayer $teamPlayer): string
    {
        if ((bool) $teamPlayer->manual_inactive) {
            return TeamPlayer::STATUS_INACTIVE;
        }

        if ($this->isAcademicallySuspended((int) $teamPlayer->student_id)) {
            return TeamPlayer::STATUS_SUSPENDED;
        }

        if ($this->hasConfirmedInjury((int) $teamPlayer->student_id, (int) $teamPlayer->team_id)) {
            return TeamPlayer::STATUS_INJURED;
        }

        return TeamPlayer::STATUS_ACTIVE;
    }

    private function hasConfirmedInjury(int $studentId, int $teamId): bool
    {
        return WellnessLog::query()
            ->where('student_id', $studentId)
            ->where('injury_observed', true)
            ->whereNull('injury_resolved_at')
            ->whereHas('schedule', fn ($query) => $query->where('team_id', $teamId))
            ->exists();
    }

    private function isAcademicallySuspended(int $studentId): bool
    {
        $openPeriodIds = AcademicPeriod::query()
            ->open()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($openPeriodIds)) {
            return false;
        }

        $submittedPeriodIds = AcademicDocument::query()
            ->periodSubmission()
            ->where('student_id', $studentId)
            ->whereIn('academic_period_id', $openPeriodIds)
            ->pluck('academic_period_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        if (count(array_diff($openPeriodIds, $submittedPeriodIds)) > 0) {
            return true;
        }

        return AcademicEligibilityEvaluation::query()
            ->where('student_id', $studentId)
            ->whereIn('academic_period_id', $openPeriodIds)
            ->where('final_status', 'ineligible')
            ->exists();
    }
}
