<?php

namespace App\Services;

use App\Data\AgentStatusResult;
use App\Jobs\PublishCampaignPostJob;
use App\Models\Admin;
use App\Models\Admin\Campaign;
use App\Models\Admin\CampaignDomain;
use App\Models\Admin\CampaignDomainReplacement;
use App\Models\Admin\CampaignPost;
use App\Models\Admin\Domain;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CampaignDomainReplacementService
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

    public function ineligibilityReason(
        Campaign $campaign,
        CampaignPost $post,
        Admin $admin,
        Domain $domain,
        bool $manual = false,
    ): ?string {
        $currentDomainId = (int) ($post->campaignDomain?->domain_id ?? 0);

        if ((int) $domain->id === $currentDomainId) {
            return 'This is already the current domain for this post.';
        }

        if (! $manual && (int) $domain->status !== 1) {
            return 'This domain is not connected. Reconnect it from the Domains page first.';
        }

        if (! $admin->isSuperAdmin() && (int) $domain->admin_id !== (int) $admin->id) {
            return 'You do not have access to this domain.';
        }

        if (CampaignDomain::query()
            ->where('campaign_id', $campaign->id)
            ->where('domain_id', $domain->id)
            ->exists()) {
            return 'This domain is already attached to this campaign.';
        }

        if (! $manual && ! $this->hasUsableApiKey($domain)) {
            return 'This domain does not have an API key configured.';
        }

        return null;
    }

    public function resolveReplacementDomainId(
        Campaign $campaign,
        CampaignPost $post,
        Admin $admin,
        ?int $domainId,
        ?string $domainName,
        bool $manual = false,
    ): int {
        if ($domainId !== null && $domainId > 0) {
            $domain = Domain::find($domainId);

            if (! $domain) {
                throw ValidationException::withMessages([
                    'new_domain_id' => 'The selected domain could not be found.',
                ]);
            }

            if ($reason = $this->ineligibilityReason($campaign, $post, $admin, $domain, $manual)) {
                throw ValidationException::withMessages([
                    'new_domain_id' => $reason,
                ]);
            }

            return $domainId;
        }

        $domain = $this->lookupDomain($domainName);

        if (! $domain) {
            throw ValidationException::withMessages([
                'new_domain_name' => 'No domain found with that name. Add it under Domains first.',
            ]);
        }

        if ($reason = $this->ineligibilityReason($campaign, $post, $admin, $domain, true)) {
            throw ValidationException::withMessages([
                'new_domain_name' => $reason,
            ]);
        }

        return (int) $domain->id;
    }

    /**
     * @return Collection<int, Domain>
     */
    public function candidates(Campaign $campaign, CampaignPost $post, Admin $admin, ?string $search = null): Collection
    {
        $this->authorize($campaign, $post, $admin);

        $attachedDomainIds = CampaignDomain::query()
            ->where('campaign_id', $campaign->id)
            ->pluck('domain_id');

        return Domain::query()
            ->where('status', 1)
            ->whereNotIn('id', $attachedDomainIds)
            ->when(! $admin->isSuperAdmin(), fn ($query) => $query->where('admin_id', $admin->id))
            ->when(trim((string) $search) !== '', function ($query) use ($search) {
                $query->where('name', 'like', '%'.trim((string) $search).'%');
            })
            ->orderBy('name')
            ->get()
            ->filter(fn (Domain $domain) => $this->hasUsableApiKey($domain))
            ->values();
    }

    public function hasNextAutoReplacementDomain(CampaignPost $post): bool
    {
        $post->loadMissing('campaign', 'campaignDomain.domain');

        return $post->campaign
            && $this->nextAutoReplacementDomain($post->campaign, $post) !== null;
    }

    public function attemptAutomaticReplacement(CampaignPost $post): bool
    {
        $post->loadMissing('campaign', 'campaignDomain.domain');

        if (! $post->auto_replace_domains || ! $post->campaign || ! $post->campaignDomain?->domain) {
            return false;
        }

        $candidate = $this->nextAutoReplacementDomain($post->campaign, $post);

        if (! $candidate) {
            return false;
        }

        $admin = Admin::find($post->campaign->admin_id);

        if (! $admin) {
            return false;
        }

        try {
            $this->replace(
                $post,
                $admin,
                (int) $candidate->id,
                (int) $post->campaignDomain->domain_id,
                (string) Str::uuid(),
                'Automatic replacement after publish failure',
                manual: true,
                automatic: true,
            );

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    /**
     * @return array<int, int>
     */
    public function triedDomainIdsForPost(CampaignPost $post): array
    {
        $ids = CampaignDomainReplacement::query()
            ->where('campaign_post_id', $post->id)
            ->get(['old_domain_id', 'new_domain_id'])
            ->flatMap(fn (CampaignDomainReplacement $row) => [$row->old_domain_id, $row->new_domain_id])
            ->all();

        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }

    public function nextAutoReplacementDomain(Campaign $campaign, CampaignPost $post): ?Domain
    {
        $triedIds = $this->triedDomainIdsForPost($post);
        $currentId = (int) ($post->campaignDomain?->domain_id ?? 0);

        if ($currentId > 0) {
            $triedIds[] = $currentId;
        }

        $attachedIds = CampaignDomain::query()
            ->where('campaign_id', $campaign->id)
            ->pluck('domain_id')
            ->all();

        return Domain::query()
            ->whereNotIn('id', array_unique($triedIds))
            ->whereNotIn('id', $attachedIds)
            ->orderBy('name')
            ->first();
    }

    public function replace(
        CampaignPost $post,
        Admin $admin,
        int $newDomainId,
        int $expectedOldDomainId,
        string $requestUuid,
        string $reason,
        bool $manual = false,
        bool $automatic = false,
    ): CampaignDomainReplacement {
        $post->loadMissing('campaign', 'campaignDomain.domain');
        $campaign = $post->campaign;

        if (! $campaign) {
            throw ValidationException::withMessages(['campaign_post' => 'Campaign post is no longer available.']);
        }

        $this->authorize($campaign, $post, $admin);

        if ($existing = CampaignDomainReplacement::query()->where('request_uuid', $requestUuid)->first()) {
            return $this->resolveIdempotentRequest(
                $existing,
                $post,
                $admin,
                $expectedOldDomainId,
                $newDomainId
            );
        }

        $campaignDomain = $post->campaignDomain;
        $oldDomain = $campaignDomain?->domain;
        $candidate = Domain::find($newDomainId);

        $this->validateUnlockedEligibility(
            $campaign,
            $post,
            $campaignDomain,
            $oldDomain,
            $candidate,
            $admin,
            $expectedOldDomainId,
            $manual,
            $automatic
        );

        $health = $this->probeReplacementDomain($candidate);

        if (! $manual && (! $health->ok || $health->code !== 'online')) {
            throw ValidationException::withMessages([
                'new_domain_id' => 'The selected domain did not pass the live agent health check.',
            ]);
        }

        $healthSnapshot = $this->healthSnapshot($health);
        $nextGeneration = ((int) $post->dispatch_generation) + 1;

        try {
            $audit = CampaignDomainReplacement::create([
                'request_uuid' => $requestUuid,
                'campaign_id' => $campaign->id,
                'campaign_post_id' => $post->id,
                'campaign_domain_id' => $campaignDomain->id,
                'old_domain_id' => $oldDomain->id,
                'new_domain_id' => $candidate->id,
                'old_hostname' => (string) $oldDomain->name,
                'new_hostname' => (string) $candidate->name,
                'admin_id' => $admin->id,
                'reason' => trim($reason) !== '' ? trim($reason) : null,
                'previous_status' => (string) $post->status,
                'health_snapshot' => $healthSnapshot,
                'dispatch_generation' => $nextGeneration,
                'state' => 'pending',
            ]);
        } catch (QueryException $exception) {
            $existing = CampaignDomainReplacement::query()
                ->where('request_uuid', $requestUuid)
                ->first();

            if (! $existing) {
                throw $exception;
            }

            return $this->resolveIdempotentRequest(
                $existing,
                $post,
                $admin,
                $expectedOldDomainId,
                $newDomainId
            );
        }

        try {
            DB::transaction(function () use (
                $audit,
                $admin,
                $expectedOldDomainId,
                $newDomainId,
                $healthSnapshot,
                $manual,
                $automatic
            ) {
                $campaign = Campaign::query()->lockForUpdate()->find($audit->campaign_id);
                $post = CampaignPost::query()->lockForUpdate()->find($audit->campaign_post_id);
                $campaignDomain = CampaignDomain::query()->lockForUpdate()->find($audit->campaign_domain_id);
                $candidate = Domain::query()->lockForUpdate()->find($newDomainId);
                $oldDomain = $campaignDomain ? Domain::find($campaignDomain->domain_id) : null;

                if (! $campaign || ! $post) {
                    throw ValidationException::withMessages(['campaign_post' => 'Campaign post is no longer available.']);
                }

                $this->authorize($campaign, $post, $admin);
                $this->validateUnlockedEligibility(
                    $campaign,
                    $post,
                    $campaignDomain,
                    $oldDomain,
                    $candidate,
                    $admin,
                    $expectedOldDomainId,
                    $manual,
                    $automatic
                );

                if (! $manual && ((int) $candidate->status !== 1 || $candidate->last_status_code !== 'online')) {
                    throw ValidationException::withMessages([
                        'new_domain_id' => 'The selected domain is no longer healthy.',
                    ]);
                }

                $campaignDomain->domain_id = $candidate->id;
                $campaignDomain->save();

                $generation = ((int) $post->dispatch_generation) + 1;
                $post->forceFill([
                    'status' => 'queued',
                    'attempt_count' => 0,
                    'last_error' => null,
                    'last_failure_code' => null,
                    'next_retry_at' => null,
                    'locked_at' => null,
                    'locked_until' => null,
                    'lock_token' => null,
                    'remote_id' => null,
                    'remote_title' => null,
                    'remote_url' => null,
                    'published_at' => null,
                    'content_updated_at' => null,
                    'delivery_state' => CampaignPost::DELIVERY_NOT_ATTEMPTED,
                    'dispatch_generation' => $generation,
                ])->save();

                $this->recalculateCampaign($campaign);

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

        return $this->dispatchCommittedReplacement($audit->fresh());
    }

    /**
     * Re-queue and dispatch publish jobs for eligible posts on a campaign domain slot,
     * excluding a post already handled by replace().
     */
    public function requeueEligiblePostsOnCampaignDomain(int $campaignDomainId, ?int $exceptPostId = null): int
    {
        $posts = CampaignPost::query()
            ->where('campaign_domain_id', $campaignDomainId)
            ->when($exceptPostId !== null, fn ($query) => $query->where('id', '!=', $exceptPostId))
            ->whereIn('status', ['queued', 'failed'])
            ->whereIn('delivery_state', [
                CampaignPost::DELIVERY_NOT_ATTEMPTED,
                CampaignPost::DELIVERY_REMOTE_ABSENT,
            ])
            ->whereNull('remote_id')
            ->whereNull('remote_url')
            ->whereNull('published_at')
            ->whereNull('locked_at')
            ->whereNull('locked_until')
            ->whereNull('lock_token')
            ->get();

        $dispatched = 0;

        foreach ($posts as $post) {
            if (self::ineligibleReason($post) !== null) {
                continue;
            }

            $generation = ((int) $post->dispatch_generation) + 1;

            $post->forceFill([
                'status' => 'queued',
                'attempt_count' => 0,
                'last_error' => null,
                'last_failure_code' => null,
                'next_retry_at' => null,
                'locked_at' => null,
                'locked_until' => null,
                'lock_token' => null,
                'remote_id' => null,
                'remote_title' => null,
                'remote_url' => null,
                'published_at' => null,
                'content_updated_at' => null,
                'delivery_state' => CampaignPost::DELIVERY_NOT_ATTEMPTED,
                'dispatch_generation' => $generation,
            ])->save();

            try {
                PublishCampaignPostJob::dispatch($post->id, $generation)
                    ->onQueue('campaigns');
                $dispatched++;
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $dispatched;
    }

    public static function ineligibleReason(CampaignPost $post): ?string
    {
        if (! in_array($post->status, ['queued', 'failed'], true)) {
            return 'Only queued or failed posts can be replaced.';
        }

        if ($post->locked_at || $post->locked_until || $post->lock_token) {
            return 'This post is currently locked for publishing.';
        }

        if ($post->remote_id || $post->remote_url || $post->published_at) {
            return 'A remote post may already exist.';
        }

        if ($post->delivery_state === CampaignPost::DELIVERY_REMOTE_CREATED) {
            return 'A remote post already exists.';
        }

        return null;
    }

    private function authorize(Campaign $campaign, CampaignPost $post, Admin $admin): void
    {
        if (! $admin->canCreateCampaigns()) {
            throw new AuthorizationException('You do not have permission to replace campaign domains.');
        }

        if ((int) $post->campaign_id !== (int) $campaign->id) {
            throw new AuthorizationException('Campaign post does not belong to this campaign.');
        }

        if (! $admin->isSuperAdmin() && (int) $campaign->admin_id !== (int) $admin->id) {
            throw new AuthorizationException('You do not have access to this campaign.');
        }
    }

    private function validateUnlockedEligibility(
        Campaign $campaign,
        CampaignPost $post,
        ?CampaignDomain $campaignDomain,
        ?Domain $oldDomain,
        ?Domain $candidate,
        Admin $admin,
        int $expectedOldDomainId,
        bool $manual = false,
        bool $automatic = false,
    ): void {
        if (! $campaignDomain
            || (int) $campaignDomain->campaign_id !== (int) $campaign->id
            || (int) $post->campaign_domain_id !== (int) $campaignDomain->id
            || ! $oldDomain
            || (int) $oldDomain->id !== $expectedOldDomainId) {
            throw ValidationException::withMessages([
                'expected_old_domain_id' => 'The campaign domain changed. Refresh and try again.',
            ]);
        }

        if (! $automatic && ($reason = self::ineligibleReason($post))) {
            throw ValidationException::withMessages(['campaign_post' => $reason]);
        }

        if (! $candidate) {
            throw ValidationException::withMessages([
                'new_domain_id' => 'The selected domain could not be found.',
            ]);
        }

        if ($reason = $this->ineligibilityReason($campaign, $post, $admin, $candidate, $manual)) {
            throw ValidationException::withMessages([
                'new_domain_id' => $reason,
            ]);
        }

        if ((int) $candidate->id === $expectedOldDomainId) {
            throw ValidationException::withMessages([
                'new_domain_id' => 'Choose a different domain than the one currently assigned.',
            ]);
        }
    }

    private function resolveIdempotentRequest(
        CampaignDomainReplacement $existing,
        CampaignPost $post,
        Admin $admin,
        int $oldDomainId,
        int $newDomainId,
    ): CampaignDomainReplacement {
        $sameRequest = (int) $existing->campaign_post_id === (int) $post->id
            && (int) $existing->old_domain_id === $oldDomainId
            && (int) $existing->new_domain_id === $newDomainId
            && (int) $existing->admin_id === (int) $admin->id;

        if (! $sameRequest) {
            throw ValidationException::withMessages([
                'request_uuid' => 'This request identifier was already used for a different replacement.',
            ]);
        }

        if (in_array($existing->state, ['dispatch_pending', 'dispatch_failed'], true)) {
            return $this->dispatchCommittedReplacement($existing);
        }

        if ($existing->state !== 'completed') {
            throw ValidationException::withMessages([
                'request_uuid' => 'This replacement request has already been submitted.',
            ]);
        }

        return $existing;
    }

    private function dispatchCommittedReplacement(
        CampaignDomainReplacement $replacement
    ): CampaignDomainReplacement {
        $claimed = CampaignDomainReplacement::query()
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

        $post = CampaignPost::query()->find($replacement->campaign_post_id);

        if (! $post
            || (int) $post->dispatch_generation !== (int) $replacement->dispatch_generation
            || (int) $post->campaign_domain_id !== (int) $replacement->campaign_domain_id
            || (int) $post->campaignDomain?->domain_id !== (int) $replacement->new_domain_id) {
            $replacement->forceFill([
                'state' => 'dispatch_failed',
                'error' => 'Replacement was committed, but its publish job can no longer be safely dispatched.',
            ])->save();

            return $replacement->fresh();
        }

        try {
            PublishCampaignPostJob::dispatch($post->id, $replacement->dispatch_generation)
                ->onQueue('campaigns');

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
            // The status endpoint is deliberately public: never pass or append the API key.
            $health = $this->statusService->probe((string) $candidate->name);
            $candidate->persistAgentHealth($health);

            return $health;
        } catch (Throwable $exception) {
            report($exception);

            return new AgentStatusResult(
                false,
                'probe_error',
                $exception->getMessage(),
            );
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

    private function recalculateCampaign(Campaign $campaign): void
    {
        $counts = CampaignPost::query()
            ->where('campaign_id', $campaign->id)
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
}
