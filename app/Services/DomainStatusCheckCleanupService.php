<?php

namespace App\Services;

use App\Models\Admin\DomainStatusCheck;

class DomainStatusCheckCleanupService
{
    /**
     * Batched cleanup safe for production (chunked deletes + per-run cap).
     *
     * @return array{stale: int, expired: int, trimmed: int, total: int, capped: bool}
     */
    public function runBatchedCleanup(?int $adminId = null): array
    {
        $budget = $this->maxDeletesPerRun();
        $stale = $this->deleteStaleInProgressBatched($adminId, $budget);
        $budget -= $stale;

        $expired = 0;
        if ($budget > 0) {
            $expired = $this->deleteExpiredFinishedBatched($budget);
            $budget -= $expired;
        }

        $trimmed = 0;
        if ($budget > 0) {
            $trimmed = $adminId !== null
                ? $this->trimAdminHistoryBatched($adminId, $budget)
                : $this->trimAllAdminHistoriesBatched($budget);
        }

        $total = $stale + $expired + $trimmed;

        return [
            'stale' => $stale,
            'expired' => $expired,
            'trimmed' => $trimmed,
            'total' => $total,
            'capped' => $total >= $this->maxDeletesPerRun(),
        ];
    }

    /**
     * Queue a background prune after starting a new check (non-blocking).
     */
    public function queueLightweightPrune(int $adminId): void
    {
        \App\Jobs\PruneDomainStatusChecksJob::dispatch($adminId);
    }

    /**
     * @deprecated Use runBatchedCleanup via queue job instead.
     */
    public function runFullCleanup(?int $adminId = null): array
    {
        return $this->runBatchedCleanup($adminId);
    }

    public function deleteStaleInProgressBatched(?int $adminId, int $budget): int
    {
        if ($budget < 1) {
            return 0;
        }

        $cutoff = now()->subHours($this->staleHours());
        $deleted = 0;

        while ($deleted < $budget) {
            $limit = min($this->deleteChunkSize(), $budget - $deleted);

            $ids = DomainStatusCheck::query()
                ->whereIn('status', ['queued', 'processing'])
                ->where('created_at', '<', $cutoff)
                ->when($adminId !== null, fn ($q) => $q->where('admin_id', $adminId))
                ->orderBy('id')
                ->limit($limit)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted += $this->deleteChecksByIds($ids->all());
        }

        return $deleted;
    }

    public function deleteExpiredFinishedBatched(int $budget): int
    {
        if ($budget < 1) {
            return 0;
        }

        $cutoff = now()->subDays($this->retentionDays());
        $deleted = 0;

        while ($deleted < $budget) {
            $limit = min($this->deleteChunkSize(), $budget - $deleted);

            $ids = DomainStatusCheck::query()
                ->whereIn('status', ['completed', 'failed'])
                ->where(function ($query) use ($cutoff) {
                    $query->where('completed_at', '<', $cutoff)
                        ->orWhere(function ($inner) use ($cutoff) {
                            $inner->whereNull('completed_at')
                                ->where('updated_at', '<', $cutoff);
                        });
                })
                ->orderBy('id')
                ->limit($limit)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted += $this->deleteChecksByIds($ids->all());
        }

        return $deleted;
    }

    public function trimAdminHistoryBatched(int $adminId, int $budget): int
    {
        if ($budget < 1) {
            return 0;
        }

        $keep = $this->keepLastPerAdmin();
        $deleted = 0;

        while ($deleted < $budget) {
            $limit = min($this->deleteChunkSize(), $budget - $deleted);

            $query = DomainStatusCheck::query()
                ->where('admin_id', $adminId)
                ->whereIn('status', ['completed', 'failed']);

            if ($keep > 0) {
                $cutoffId = (clone $query)
                    ->orderByDesc('id')
                    ->offset($keep - 1)
                    ->limit(1)
                    ->value('id');

                if ($cutoffId === null) {
                    break;
                }

                $query->where('id', '<', $cutoffId);
            }

            $ids = $query->orderBy('id')->limit($limit)->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted += $this->deleteChecksByIds($ids->all());
        }

        return $deleted;
    }

    public function trimAllAdminHistoriesBatched(int $budget): int
    {
        $adminIds = DomainStatusCheck::query()
            ->whereIn('status', ['completed', 'failed'])
            ->select('admin_id')
            ->distinct()
            ->orderBy('admin_id')
            ->limit($this->maxAdminsPerRun())
            ->pluck('admin_id');

        $deleted = 0;

        foreach ($adminIds as $adminId) {
            if ($deleted >= $budget) {
                break;
            }

            $deleted += $this->trimAdminHistoryBatched((int) $adminId, $budget - $deleted);
        }

        return $deleted;
    }

    /**
     * Delete parent rows; child rows cascade in DB.
     *
     * @param  array<int>  $ids
     */
    private function deleteChecksByIds(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        return DomainStatusCheck::query()->whereIn('id', $ids)->delete();
    }

    private function deleteChunkSize(): int
    {
        return max(5, (int) config('domain_status_checker.delete_chunk_size', 25));
    }

    private function maxDeletesPerRun(): int
    {
        return max(10, (int) config('domain_status_checker.max_deletes_per_run', 100));
    }

    private function maxAdminsPerRun(): int
    {
        return max(1, (int) config('domain_status_checker.max_admins_per_run', 20));
    }

    private function keepLastPerAdmin(): int
    {
        return max(0, (int) config('domain_status_checker.keep_last_per_admin', 5));
    }

    private function retentionDays(): int
    {
        return max(1, (int) config('domain_status_checker.retention_days', 7));
    }

    private function staleHours(): int
    {
        return max(1, (int) config('domain_status_checker.stale_hours', 24));
    }
}
