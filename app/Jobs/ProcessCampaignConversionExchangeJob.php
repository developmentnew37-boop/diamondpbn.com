<?php

namespace App\Jobs;

use App\Models\Admin\Campaign;
use App\Models\Admin\CampaignPost;
use App\Models\Admin\ScheduleCampaign;
use App\Services\CampaignConversionEligibilityService;
use App\Services\CampaignDomainReplacementService;
use App\Services\CampaignLiveToDripfeedConversionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessCampaignConversionExchangeJob implements ShouldQueue
{
    use Concerns\ShowsLiveToDripfeedPostJobLabel;
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 30;

    public int $timeout = 3600;

    /**
     * @param  array<int, array{campaign_post_id: int, new_domain_id: int}>  $exchangeMappings
     */
    public function __construct(
        public int $sourceCampaignId,
        public int $scheduleCampaignId,
        public array $exchangeMappings,
    ) {
        $this->onQueue('campaigns');
    }

    public function displayName(): string
    {
        $count = count($this->exchangeMappings);
        $dripfeed = $this->dripfeedPostCampaignLabel($this->scheduleCampaignId);

        return "Post Live→Dripfeed: Domain exchange ({$count} mapping(s)) — live #{$this->sourceCampaignId} → {$dripfeed}";
    }

    public function handle(
        CampaignDomainReplacementService $replacementService,
        CampaignLiveToDripfeedConversionService $conversionService,
        CampaignConversionEligibilityService $eligibility,
    ): void {
        $source = Campaign::find($this->sourceCampaignId);
        $schedule = ScheduleCampaign::find($this->scheduleCampaignId);

        if (! $source || ! $schedule) {
            return;
        }

        $admin = \App\Models\Admin::find($source->admin_id);
        if (! $admin) {
            Log::warning('ProcessCampaignConversionExchangeJob: missing admin', [
                'campaign_id' => $this->sourceCampaignId,
            ]);

            return;
        }

        foreach ($this->exchangeMappings as $mapping) {
            $postId = (int) ($mapping['campaign_post_id'] ?? 0);
            $domainId = (int) ($mapping['new_domain_id'] ?? 0);
            if ($postId < 1 || $domainId < 1) {
                continue;
            }

            $post = CampaignPost::with('campaign')->find($postId);
            if (! $post || (int) $post->campaign_id !== (int) $source->id) {
                continue;
            }

            try {
                $oldDomainId = (int) ($post->campaignDomain?->domain_id ?? 0);
                $replacementService->replace(
                    $post,
                    $admin,
                    $domainId,
                    $oldDomainId,
                    (string) Str::uuid(),
                    'Live to dripfeed conversion exchange',
                    manual: true,
                );
            } catch (\Throwable $e) {
                Log::warning('ProcessCampaignConversionExchangeJob: replace failed', [
                    'post_id' => $postId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $exchangePostIds = collect($this->exchangeMappings)
            ->pluck('campaign_post_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        $pendingPublish = CampaignPost::query()
            ->whereIn('id', $exchangePostIds)
            ->where(function ($q) {
                $q->where('status', '!=', 'success')
                    ->orWhereNull('remote_id')
                    ->orWhere('remote_id', '=', '');
            })
            ->exists();

        if ($pendingPublish) {
            $this->release(60);

            return;
        }

        $conversionService->dispatchDraftPhase($schedule);
    }
}
