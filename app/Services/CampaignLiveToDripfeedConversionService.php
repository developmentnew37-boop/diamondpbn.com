<?php

namespace App\Services;

use App\Jobs\DraftConvertedLivePostsJob;
use App\Jobs\ProcessCampaignConversionExchangeJob;
use App\Models\Admin\Campaign;
use App\Models\Admin\CampaignArticle;
use App\Models\Admin\CampaignDomain;
use App\Models\Admin\ScheduleCampaign;
use App\Models\Admin\ScheduleCampaignArticle;
use App\Models\Admin\ScheduleCampaignDate;
use App\Models\Admin\ScheduleCampaignDomain;
use App\Models\Admin\ScheduleCampaignPost;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CampaignLiveToDripfeedConversionService
{
    public function __construct(
        private readonly CampaignConversionEligibilityService $eligibility,
    ) {}

    /**
     * @param  array<int, array{date: string, quantity: int}>  $dateQuantities
     * @param  array<int, array{campaign_post_id: int, new_domain_id: int}>  $exchangeMappings
     */
    public function convert(
        Campaign $source,
        int $adminId,
        string $campaignNoInput,
        array $dateQuantities,
        string $conversionMode,
        array $exchangeMappings = [],
        bool $acknowledgeRisk = false,
    ): ScheduleCampaign {
        if ($source->converted_to_schedule_campaign_id) {
            throw ValidationException::withMessages([
                'campaign_id' => 'This campaign was already converted to a dripfeed campaign.',
            ]);
        }

        $summary = $this->eligibility->summarize($source);
        if ($summary['in_progress'] > 0) {
            throw ValidationException::withMessages([
                'campaign_id' => 'Wait until all posts finish publishing before converting.',
            ]);
        }

        $convertiblePosts = $this->eligibility->convertiblePosts($source);
        $convertibleCount = $convertiblePosts->count();

        if ($conversionMode === 'partial' && $convertibleCount < 1) {
            throw ValidationException::withMessages([
                'campaign_id' => 'No live posts with remote IDs are available to convert.',
            ]);
        }

        if ($conversionMode === 'exchange' && $convertibleCount < 1 && empty($exchangeMappings)) {
            throw ValidationException::withMessages([
                'exchange' => 'Configure domain exchange for failed posts or retry until at least one post is live.',
            ]);
        }

        $totalQty = array_sum(array_column($dateQuantities, 'quantity'));
        if ($totalQty !== $convertibleCount) {
            throw ValidationException::withMessages([
                'date_quantities' => "Date quantities must total {$convertibleCount} posts (currently {$totalQty}).",
            ]);
        }

        $runDate = now()->startOfDay();
        $campaignNo = $this->generateUniqueScheduleCampaignNo($campaignNoInput);

        $scheduleCampaign = DB::transaction(function () use (
            $source,
            $adminId,
            $campaignNo,
            $dateQuantities,
            $conversionMode,
            $convertiblePosts,
            $runDate,
        ) {
            $schedule = ScheduleCampaign::create([
                'campaign_no' => $campaignNo,
                'domain_category_id' => $source->domain_category_id,
                'article_category_id' => $source->article_category_id,
                'admin_id' => $adminId,
                'schedule_from_date' => $this->minDate($dateQuantities),
                'schedule_to_date' => $this->maxDate($dateQuantities),
                'status' => 'queued',
                'is_sticky_campaign' => (bool) $source->is_sticky_campaign,
                'total_targets' => $convertiblePosts->count(),
                'completed_targets' => 0,
                'failed_targets' => 0,
                'converted_from_campaign_id' => $source->id,
                'conversion_run_date' => $runDate,
                'conversion_mode' => $conversionMode,
                'conversion_pipeline_status' => 'pending',
            ]);

            $domainMap = $this->copyDomains($source, $schedule);
            $articleMap = $this->copyArticles($source, $schedule);

            foreach ($dateQuantities as $row) {
                ScheduleCampaignDate::create([
                    'schedule_campaign_id' => $schedule->id,
                    'schedule_date' => $row['date'],
                    'quantity' => $row['quantity'],
                ]);
            }

            $slotDates = $this->expandSlotDates($dateQuantities);
            $postRows = [];
            $now = now();

            foreach ($convertiblePosts->values() as $index => $livePost) {
                $slotDate = Carbon::parse($slotDates[$index])->startOfDay();
                $publishDate = $this->resolvePublishDate($slotDate, $runDate);

                $scheduleDomainId = $domainMap[(int) $livePost->campaign_domain_id] ?? null;
                $scheduleArticleId = $articleMap[(int) $livePost->campaign_article_id] ?? null;

                if (! $scheduleDomainId || ! $scheduleArticleId) {
                    throw ValidationException::withMessages([
                        'campaign_id' => 'Could not map domain/article for post #'.$livePost->id,
                    ]);
                }

                $postRows[] = [
                    'schedule_campaign_id' => $schedule->id,
                    'source_campaign_post_id' => $livePost->id,
                    'is_converted_live' => true,
                    'conversion_phase' => 'pending_draft',
                    'conversion_publish_date' => $publishDate->toDateString(),
                    'schedule_campaign_domain_id' => $scheduleDomainId,
                    'schedule_campaign_article_id' => $scheduleArticleId,
                    'schedule_at' => $slotDate,
                    'remote_id' => $livePost->remote_id,
                    'remote_title' => $livePost->remote_title,
                    'remote_url' => $livePost->remote_url,
                    'status' => 'queued',
                    'attempt_count' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($postRows, 200) as $chunk) {
                ScheduleCampaignPost::insert($chunk);
            }

            $source->update([
                'converted_to_schedule_campaign_id' => $schedule->id,
                'conversion_locked_at' => now(),
            ]);

            if ($conversionMode === 'partial' && $this->eligibility->summarize($source)['failed'] > 0) {
                // partial: link exists but source may still have failed posts
            }

            return $schedule;
        });

        if ($conversionMode === 'exchange' && ! empty($exchangeMappings)) {
            ProcessCampaignConversionExchangeJob::dispatch(
                (int) $source->id,
                (int) $scheduleCampaign->id,
                $exchangeMappings,
            )->onQueue('campaigns');

            $scheduleCampaign->update(['conversion_pipeline_status' => 'awaiting_exchange']);
        } else {
            $this->dispatchDraftPhase($scheduleCampaign);
        }

        return $scheduleCampaign->fresh();
    }

    public function dispatchDraftPhase(ScheduleCampaign $scheduleCampaign): void
    {
        $ids = ScheduleCampaignPost::query()
            ->where('schedule_campaign_id', $scheduleCampaign->id)
            ->where('is_converted_live', true)
            ->where('conversion_phase', 'pending_draft')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        foreach (array_chunk($ids, 40) as $chunk) {
            DraftConvertedLivePostsJob::dispatch($chunk, (int) $scheduleCampaign->id)
                ->onQueue(DraftConvertedLivePostsJob::QUEUE);
        }

        $scheduleCampaign->update(['conversion_pipeline_status' => 'drafting']);
    }

    public function resolvePublishDate(Carbon $slotDate, Carbon $runDate): Carbon
    {
        if ($slotDate->lte($runDate)) {
            return $runDate->copy();
        }

        return $slotDate->copy();
    }

    /**
     * @param  array<int, array{date: string, quantity: int}>  $dateQuantities
     * @return string[]
     */
    private function expandSlotDates(array $dateQuantities): array
    {
        $ordered = collect($dateQuantities)
            ->sortBy('date')
            ->values();

        $dates = [];
        foreach ($ordered as $row) {
            for ($i = 0; $i < (int) $row['quantity']; $i++) {
                $dates[] = $row['date'];
            }
        }

        return $dates;
    }

    /**
     * @param  array<int, array{date: string, quantity: int}>  $dateQuantities
     */
    private function minDate(array $dateQuantities): ?string
    {
        if ($dateQuantities === []) {
            return null;
        }

        return collect($dateQuantities)->min('date');
    }

    /**
     * @param  array<int, array{date: string, quantity: int}>  $dateQuantities
     */
    private function maxDate(array $dateQuantities): ?string
    {
        if ($dateQuantities === []) {
            return null;
        }

        return collect($dateQuantities)->max('date');
    }

    /**
     * @return array<int, int> campaign_domain_id => schedule_campaign_domain_id
     */
    private function copyDomains(Campaign $source, ScheduleCampaign $schedule): array
    {
        $map = [];
        CampaignDomain::query()
            ->where('campaign_id', $source->id)
            ->orderBy('id')
            ->each(function (CampaignDomain $cd) use ($schedule, &$map) {
                $row = ScheduleCampaignDomain::create([
                    'schedule_campaign_id' => $schedule->id,
                    'domain_id' => $cd->domain_id,
                ]);
                $map[(int) $cd->id] = (int) $row->id;
            });

        return $map;
    }

    /**
     * @return array<int, int> campaign_article_id => schedule_campaign_article_id
     */
    private function copyArticles(Campaign $source, ScheduleCampaign $schedule): array
    {
        $map = [];
        CampaignArticle::query()
            ->where('campaign_id', $source->id)
            ->orderBy('id')
            ->each(function (CampaignArticle $ca) use ($schedule, &$map) {
                $row = ScheduleCampaignArticle::create([
                    'schedule_campaign_id' => $schedule->id,
                    'article_id' => $ca->article_id,
                    'article_title_snapshot' => $ca->article_title_snapshot,
                    'article_body_snapshot' => $ca->article_body_snapshot,
                    'keyword' => $ca->keyword,
                    'url' => $ca->url,
                    'keyword_type' => $ca->keyword_type,
                    'url_type' => $ca->url_type,
                    'media' => $ca->media,
                    'nofollow' => $ca->nofollow,
                    'sponsored' => $ca->sponsored,
                    'ugc' => $ca->ugc,
                    'noopener' => $ca->noopener,
                    'noreferrer' => $ca->noreferrer,
                    'raw_rel_attr' => $ca->raw_rel_attr,
                ]);
                $map[(int) $ca->id] = (int) $row->id;
            });

        return $map;
    }

    private function generateUniqueScheduleCampaignNo(string $input): string
    {
        $base = Str::slug($input);
        if ($base === '') {
            $base = 'converted-'.now()->timestamp;
        }
        $slug = $base;
        $counter = 1;
        while (ScheduleCampaign::where('campaign_no', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
