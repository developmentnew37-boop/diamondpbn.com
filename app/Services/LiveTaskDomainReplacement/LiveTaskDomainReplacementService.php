<?php

namespace App\Services\LiveTaskDomainReplacement;

use App\Data\AgentStatusResult;
use App\Jobs\CleanupReplacedDomainRemoteContentJob;
use App\Models\Admin;
use App\Models\Admin\Domain;
use App\Services\LocalClientBillingService;
use App\Services\WordPressAgentStatusService;
use App\Support\ConvertedLivePostSlot;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

class LiveTaskDomainReplacementService
{
    public function __construct(
        private readonly WordPressAgentStatusService $statusService,
    ) {}

    public function lookupDomain(?string $input): ?Domain
    {
        $normalized = normalizeDomainName($input);

        if ($normalized === '') {
            return null;
        }

        return Domain::query()->where('name', $normalized)->first();
    }

    public function ineligibleReason(LiveTaskReplacementProfile $profile, Model $task): ?string
    {
        if (! empty($task->is_converted_live)) {
            if (! $profile->supportsConvertedLive) {
                return 'Converted live tasks must be managed from the conversion workflow.';
            }

            return $this->convertedIneligibleReason($profile, $task);
        }

        if (! in_array((string) $task->status, ['queued', 'failed'], true)) {
            return 'Only queued or failed tasks can be replaced.';
        }

        if ($task->locked_at || ($this->taskHasLockedUntil($profile) && $task->locked_until) || $task->lock_token) {
            return 'This task is currently locked for publishing.';
        }

        if (filled($task->remote_id) || filled($task->remote_url) || filled($task->published_at)) {
            return 'A remote link may already exist on the current domain.';
        }

        return null;
    }

    private function convertedIneligibleReason(LiveTaskReplacementProfile $profile, Model $task): ?string
    {
        if ((string) ($task->conversion_phase ?? '') === 'published') {
            return 'Published converted tasks cannot be replaced.';
        }

        if (ConvertedLivePostSlot::isPubliclyLive($task->schedule_at, $task->remote_status ?? null)) {
            return 'This converted task is already live on its slot.';
        }

        $phaseOk = in_array((string) ($task->conversion_phase ?? ''), ['failed', 'pending_draft', 'drafted'], true);
        $statusOk = in_array((string) $task->status, ['queued', 'failed'], true);

        if (! $phaseOk && ! $statusOk) {
            return 'Only failed or in-progress converted tasks can be replaced.';
        }

        if ($task->locked_at || ($this->taskHasLockedUntil($profile) && $task->locked_until) || $task->lock_token) {
            return 'This task is currently locked for publishing.';
        }

        if ($profile->label === 'converted_schedule_post' && ! filled($task->source_campaign_post_id)) {
            return 'Converted post is missing its source live post reference.';
        }

        if ($profile->label === 'converted_schedule_sidebar' && ! filled($task->source_sidebar_campaign_task_id)) {
            return 'Converted task is missing its source live sidebar task reference.';
        }

        return null;
    }

    public function ineligibilityReasonForDomain(
        LiveTaskReplacementProfile $profile,
        Model $campaign,
        Model $task,
        Admin $admin,
        Domain $domain,
        bool $manual = false,
    ): ?string {
        $domainRow = $task->{$profile->taskDomainRelation};
        $currentDomainId = (int) ($domainRow?->domain_id ?? 0);

        if ((int) $domain->id === $currentDomainId) {
            return 'This is already the current domain for this task.';
        }

        if (! $manual && (int) $domain->status !== 1) {
            return 'This domain is not connected. Reconnect it from the Domains page first.';
        }

        $attached = $profile->campaignDomainModel::query()
            ->where($profile->domainRowCampaignIdColumn, $campaign->id)
            ->where('domain_id', $domain->id)
            ->exists();

        if ($attached) {
            return 'This domain is already attached to this campaign.';
        }

        if (! $manual && ! $this->hasUsableApiKey($domain)) {
            return 'This domain does not have an API key configured.';
        }

        return null;
    }

    public function resolveReplacementDomainId(
        LiveTaskReplacementProfile $profile,
        Model $campaign,
        Model $task,
        Admin $admin,
        ?string $domainName,
    ): int {
        $domain = $this->lookupDomain($domainName);

        if (! $domain) {
            throw ValidationException::withMessages([
                'new_domain_name' => 'No domain found with that name. Add it under Domains first.',
            ]);
        }

        if ($reason = $this->ineligibilityReasonForDomain($profile, $campaign, $task, $admin, $domain, manual: true)) {
            throw ValidationException::withMessages([
                'new_domain_name' => $reason,
            ]);
        }

        return (int) $domain->id;
    }

    public function replace(
        LiveTaskReplacementProfile $profile,
        Model $task,
        Admin $admin,
        int $newDomainId,
        int $expectedOldDomainId,
        string $requestUuid,
        string $reason,
    ): Model {
        $task->loadMissing('campaign', $profile->taskDomainRelation.'.domain');
        $campaign = $task->campaign;

        if (! $campaign) {
            throw ValidationException::withMessages(['task' => 'Campaign task is no longer available.']);
        }

        $this->authorize($profile, $campaign, $task, $admin);

        $replacementModel = $profile->replacementModel;
        $existing = $replacementModel::query()->where('request_uuid', $requestUuid)->first();

        if ($existing) {
            return $this->resolveIdempotentRequest($profile, $existing, $task, $admin, $expectedOldDomainId, $newDomainId);
        }

        $domainRow = $task->{$profile->taskDomainRelation};
        $oldDomain = $domainRow?->domain;
        $candidate = Domain::find($newDomainId);

        $this->validateEligibility($profile, $campaign, $task, $domainRow, $oldDomain, $candidate, $admin, $expectedOldDomainId);

        $health = $this->probeReplacementDomain($candidate);
        $healthSnapshot = $this->healthSnapshot($health);

        $auditData = [
            'request_uuid' => $requestUuid,
            $profile->auditCampaignIdColumn => $campaign->id,
            $profile->auditTaskIdColumn => $task->id,
            $profile->auditDomainRowIdColumn => $domainRow->id,
            'old_domain_id' => $oldDomain->id,
            'new_domain_id' => $candidate->id,
            'old_hostname' => (string) $oldDomain->name,
            'new_hostname' => (string) $candidate->name,
            'admin_id' => $admin->id,
            'reason' => trim($reason) !== '' ? trim($reason) : null,
            'previous_status' => (string) $task->status,
            'health_snapshot' => $healthSnapshot,
            'dispatch_generation' => ((int) $task->dispatch_generation) + 1,
            'state' => 'pending',
        ];

        if ($profile->supportsConvertedLive && $this->replacementHasCleanupColumns($profile)) {
            $previousRemoteId = filled($task->remote_id) ? (string) $task->remote_id : null;
            $previousRemoteUrl = filled($task->remote_url) ? (string) $task->remote_url : null;
            $auditData['previous_remote_id'] = $previousRemoteId;
            $auditData['previous_remote_url'] = $previousRemoteUrl;
            $auditData['old_remote_cleanup_status'] = $previousRemoteId ? 'pending' : 'skipped';
            $auditData['old_remote_cleanup_error'] = null;
            $auditData['old_remote_cleaned_at'] = null;
        }

        try {
            $audit = $replacementModel::create($auditData);
        } catch (QueryException $exception) {
            $existing = $replacementModel::query()->where('request_uuid', $requestUuid)->first();
            if (! $existing) {
                throw $exception;
            }

            return $this->resolveIdempotentRequest($profile, $existing, $task, $admin, $expectedOldDomainId, $newDomainId);
        }

        try {
            DB::transaction(function () use ($profile, $audit, $admin, $expectedOldDomainId, $newDomainId, $healthSnapshot) {
                $campaign = $profile->campaignModel::query()->lockForUpdate()->find($audit->{$profile->auditCampaignIdColumn});
                $task = $profile->taskModel::query()->lockForUpdate()->find($audit->{$profile->auditTaskIdColumn});
                $domainRow = $profile->campaignDomainModel::query()->lockForUpdate()->find($audit->{$profile->auditDomainRowIdColumn});
                $candidate = Domain::query()->lockForUpdate()->find($newDomainId);
                $oldDomain = $domainRow ? Domain::find($domainRow->domain_id) : null;

                if (! $campaign || ! $task) {
                    throw ValidationException::withMessages(['task' => 'Campaign task is no longer available.']);
                }

                $this->authorize($profile, $campaign, $task, $admin);
                $this->validateEligibility($profile, $campaign, $task, $domainRow, $oldDomain, $candidate, $admin, $expectedOldDomainId);

                $domainRow->domain_id = $candidate->id;
                $domainRow->save();

                $generation = ((int) $task->dispatch_generation) + 1;
                $resetFields = [
                    'status' => 'queued',
                    'attempt_count' => 0,
                    'last_error' => null,
                    'next_retry_at' => null,
                    'locked_at' => null,
                    'lock_token' => null,
                    'remote_id' => null,
                    'remote_url' => null,
                    'published_at' => null,
                    'dispatch_generation' => $generation,
                ];

                foreach (['locked_until', 'content_updated_at', 'remote_title', 'http_status', 'remote_status', 'remote_response'] as $field) {
                    if (in_array($field, $task->getFillable(), true)) {
                        $resetFields[$field] = null;
                    }
                }

                if ($profile->supportsConvertedLive) {
                    $resetFields['conversion_phase'] = 'pending_draft';
                    foreach (['last_conversion_error', 'last_conversion_attempt_at'] as $field) {
                        if (in_array($field, $task->getFillable(), true)) {
                            $resetFields[$field] = null;
                        }
                    }
                }

                $task->forceFill($resetFields)->save();

                $this->recalculateCampaign($profile, $campaign);
                app(LocalClientBillingService::class)->syncUnpaidCampaignBilling($campaign);

                $audit->forceFill([
                    'result_status' => 'queued',
                    'health_snapshot' => $healthSnapshot,
                    'dispatch_generation' => $generation,
                    'state' => 'dispatch_pending',
                    'error' => null,
                ])->save();
            }, 3);
        } catch (Throwable $exception) {
            $audit->refresh();
            if ($audit->state === 'pending') {
                $audit->forceFill([
                    'state' => 'failed',
                    'error' => 'Replacement could not be completed.',
                ])->save();
            }

            throw $exception;
        }

        $replacement = $this->dispatchCommittedReplacement($profile, $audit->fresh());
        $this->dispatchOldRemoteCleanupIfNeeded($profile, $replacement);

        return $replacement->fresh();
    }

    private function replacementHasCleanupColumns(LiveTaskReplacementProfile $profile): bool
    {
        $table = (new ($profile->replacementModel))->getTable();

        return Schema::hasColumn($table, 'previous_remote_id')
            && Schema::hasColumn($table, 'old_remote_cleanup_status');
    }

    private function dispatchOldRemoteCleanupIfNeeded(LiveTaskReplacementProfile $profile, Model $replacement): void
    {
        if (! $profile->supportsConvertedLive || ! $this->replacementHasCleanupColumns($profile)) {
            return;
        }

        if ((string) ($replacement->old_remote_cleanup_status ?? '') !== 'pending') {
            return;
        }

        if (! filled($replacement->previous_remote_id)) {
            $replacement->forceFill([
                'old_remote_cleanup_status' => 'skipped',
                'old_remote_cleaned_at' => now(),
            ])->save();

            return;
        }

        try {
            CleanupReplacedDomainRemoteContentJob::dispatch($profile->label, (int) $replacement->id)
                ->onQueue(CleanupReplacedDomainRemoteContentJob::QUEUE);
        } catch (Throwable $exception) {
            report($exception);
            $replacement->forceFill([
                'old_remote_cleanup_status' => 'failed',
                'old_remote_cleanup_error' => 'Could not queue old remote cleanup.',
            ])->save();
        }
    }

    /**
     * Re-queue and dispatch publish jobs for eligible tasks on a domain slot,
     * excluding a task already handled by replace().
     */
    public function requeueEligibleTasksOnDomainRow(
        LiveTaskReplacementProfile $profile,
        int $domainRowId,
        ?int $exceptTaskId = null,
    ): int {
        $tasksQuery = $profile->taskModel::query()
            ->where($profile->taskDomainRowIdColumn, $domainRowId)
            ->when($exceptTaskId !== null, fn ($query) => $query->where('id', '!=', $exceptTaskId))
            ->whereIn('status', ['queued', 'failed'])
            ->whereNull('remote_id')
            ->whereNull('remote_url')
            ->whereNull('published_at')
            ->whereNull('locked_at')
            ->whereNull('lock_token');

        if ($this->taskHasLockedUntil($profile)) {
            $tasksQuery->whereNull('locked_until');
        }

        $tasks = $tasksQuery->get();

        $dispatched = 0;

        foreach ($tasks as $task) {
            if ($this->ineligibleReason($profile, $task) !== null) {
                continue;
            }

            $generation = ((int) $task->dispatch_generation) + 1;
            $resetFields = [
                'status' => 'queued',
                'attempt_count' => 0,
                'last_error' => null,
                'next_retry_at' => null,
                'locked_at' => null,
                'lock_token' => null,
                'remote_id' => null,
                'remote_url' => null,
                'published_at' => null,
                'dispatch_generation' => $generation,
            ];

            if ($this->taskHasLockedUntil($profile)) {
                $resetFields['locked_until'] = null;
            }

            foreach (['content_updated_at', 'remote_title', 'http_status', 'remote_status', 'remote_response'] as $field) {
                if (in_array($field, $task->getFillable(), true)) {
                    $resetFields[$field] = null;
                }
            }

            $task->forceFill($resetFields)->save();

            try {
                $profile->publishJobClass::dispatch($task->id, $generation)
                    ->onQueue($profile->queue);
                $dispatched++;
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $dispatched;
    }

    private function authorize(LiveTaskReplacementProfile $profile, Model $campaign, Model $task, Admin $admin): void
    {
        if (! $admin->canCreateCampaigns()) {
            throw new AuthorizationException('You do not have permission to replace campaign domains.');
        }

        if ((int) $task->{$profile->taskCampaignIdColumn} !== (int) $campaign->id) {
            throw new AuthorizationException('Task does not belong to this campaign.');
        }

        if (! $admin->isSuperAdmin() && (int) $campaign->admin_id !== (int) $admin->id) {
            throw new AuthorizationException('You do not have access to this campaign.');
        }
    }

    private function validateEligibility(
        LiveTaskReplacementProfile $profile,
        Model $campaign,
        Model $task,
        ?Model $domainRow,
        ?Domain $oldDomain,
        ?Domain $candidate,
        Admin $admin,
        int $expectedOldDomainId,
    ): void {
        if (! $domainRow
            || (int) $domainRow->{$profile->domainRowCampaignIdColumn} !== (int) $campaign->id
            || (int) $task->{$profile->taskDomainRowIdColumn} !== (int) $domainRow->id
            || ! $oldDomain
            || (int) $oldDomain->id !== $expectedOldDomainId) {
            throw ValidationException::withMessages([
                'expected_old_domain_id' => 'The campaign domain changed. Refresh and try again.',
            ]);
        }

        if ($reason = $this->ineligibleReason($profile, $task)) {
            throw ValidationException::withMessages(['task' => $reason]);
        }

        if (! $candidate) {
            throw ValidationException::withMessages([
                'new_domain_name' => 'The selected domain could not be found.',
            ]);
        }

        if ($reason = $this->ineligibilityReasonForDomain($profile, $campaign, $task, $admin, $candidate, manual: true)) {
            throw ValidationException::withMessages([
                'new_domain_name' => $reason,
            ]);
        }

        if ((int) $candidate->id === $expectedOldDomainId) {
            throw ValidationException::withMessages([
                'new_domain_name' => 'Choose a different domain than the one currently assigned.',
            ]);
        }
    }

    private function resolveIdempotentRequest(
        LiveTaskReplacementProfile $profile,
        Model $existing,
        Model $task,
        Admin $admin,
        int $oldDomainId,
        int $newDomainId,
    ): Model {
        $sameRequest = (int) $existing->{$profile->auditTaskIdColumn} === (int) $task->id
            && (int) $existing->old_domain_id === $oldDomainId
            && (int) $existing->new_domain_id === $newDomainId
            && (int) $existing->admin_id === (int) $admin->id;

        if (! $sameRequest) {
            throw ValidationException::withMessages([
                'request_uuid' => 'This request identifier was already used for a different replacement.',
            ]);
        }

        if (in_array($existing->state, ['dispatch_pending', 'dispatch_failed'], true)) {
            return $this->dispatchCommittedReplacement($profile, $existing);
        }

        if ($existing->state !== 'completed') {
            throw ValidationException::withMessages([
                'request_uuid' => 'This replacement request has already been submitted.',
            ]);
        }

        return $existing;
    }

    private function dispatchCommittedReplacement(LiveTaskReplacementProfile $profile, Model $replacement): Model
    {
        $replacementModel = $profile->replacementModel;

        $claimed = $replacementModel::query()
            ->whereKey($replacement->id)
            ->whereIn('state', ['dispatch_pending', 'dispatch_failed'])
            ->update([
                'state' => 'dispatching',
                'error' => null,
                'updated_at' => now(),
            ]);

        if ($claimed !== 1) {
            return $replacement->fresh();
        }

        $task = $profile->taskModel::query()
            ->with($profile->taskDomainRelation)
            ->find($replacement->{$profile->auditTaskIdColumn});

        $domainRow = $task?->{$profile->taskDomainRelation};

        if (! $task
            || (int) $task->dispatch_generation !== (int) $replacement->dispatch_generation
            || (int) $task->{$profile->taskDomainRowIdColumn} !== (int) $replacement->{$profile->auditDomainRowIdColumn}
            || (int) ($domainRow?->domain_id ?? 0) !== (int) $replacement->new_domain_id) {
            $replacement->forceFill([
                'state' => 'dispatch_failed',
                'error' => 'Replacement was committed, but its publish job can no longer be safely dispatched.',
            ])->save();

            return $replacement->fresh();
        }

        try {
            $profile->publishJobClass::dispatch($task->id, $replacement->dispatch_generation)
                ->onQueue($profile->queue);

            $replacement->forceFill([
                'state' => 'completed',
                'error' => null,
            ])->save();
        } catch (Throwable $exception) {
            report($exception);

            $replacement->forceFill([
                'state' => 'dispatch_failed',
                'error' => 'Replacement was committed, but queue dispatch failed.',
            ])->save();
        }

        return $replacement->fresh();
    }

    private function hasUsableApiKey(?Domain $domain): bool
    {
        if (! $domain) {
            return false;
        }

        try {
            return trim((string) $domain->api_key) !== '';
        } catch (Throwable) {
            return false;
        }
    }

    private function probeReplacementDomain(Domain $candidate): AgentStatusResult
    {
        try {
            $health = $this->statusService->probe((string) $candidate->name);
            $candidate->persistAgentHealth($health);

            return $health;
        } catch (Throwable $exception) {
            report($exception);

            return new AgentStatusResult(false, 'probe_error', $exception->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function healthSnapshot(AgentStatusResult $result): array
    {
        return [
            'ok' => $result->ok,
            'code' => $result->code,
            'plugin_version' => $result->pluginVersion,
            'probe_method' => $result->probeMethod,
            'http_status' => $result->httpStatus,
            'response_time_ms' => $result->responseTimeMs,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    private function recalculateCampaign(LiveTaskReplacementProfile $profile, Model $campaign): void
    {
        $counts = $profile->taskModel::query()
            ->where($profile->taskCampaignIdColumn, $campaign->id)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as completed")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed")
            ->selectRaw("SUM(CASE WHEN status = 'publishing' THEN 1 ELSE 0 END) as publishing")
            ->first();

        $total = (int) ($counts->total ?? 0);
        $completed = (int) ($counts->completed ?? 0);
        $failed = (int) ($counts->failed ?? 0);
        $done = $completed + $failed;

        $status = match (true) {
            $total > 0 && $done === $total && $failed === 0 => 'completed',
            $total > 0 && $done === $total && $completed > 0 => 'semi_failed',
            $total > 0 && $done === $total => 'failed',
            (int) ($counts->publishing ?? 0) > 0 || $completed > 0 || $failed > 0 => 'running',
            default => 'queued',
        };

        $campaign->forceFill([
            'total_targets' => $total,
            'completed_targets' => $completed,
            'failed_targets' => $failed,
            'status' => $status,
            'finished_at' => null,
        ])->save();
    }

    private function taskHasLockedUntil(LiveTaskReplacementProfile $profile): bool
    {
        return Schema::hasColumn((new $profile->taskModel)->getTable(), 'locked_until');
    }
}
