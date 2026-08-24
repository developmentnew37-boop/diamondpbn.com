<?php

namespace App\Services;

use App\Models\Admin\ScheduleCampaign;
use App\Models\Admin\ScheduleCampaignPost;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ScheduleCampaignFailedPostCarryService
{
    public function __construct(
        private ScheduleCampaignTargetCounterService $counters,
    ) {}

    /**
     * Re-queue failed schedule posts for campaigns still inside
     * schedule_to_date + grace days. Returns summary counts.
     *
     * @return array{campaigns: int, posts: int}
     */
    public function carryFailedPosts(?Carbon $today = null): array
    {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();
        $graceDays = max(0, (int) config('campaign.schedule.failed_carry_grace_days', 2));
        $minToDate = $today->copy()->subDays($graceDays)->toDateString();

        $campaignIds = ScheduleCampaign::query()
            ->whereNotNull('schedule_to_date')
            ->whereDate('schedule_to_date', '>=', $minToDate)
            ->whereNotIn('status', ['paused', 'cancelled'])
            ->whereHas('posts', function ($q) {
                $q->where('status', 'failed')
                    ->where(function ($q2) {
                        $q2->where('is_converted_live', false)->orWhereNull('is_converted_live');
                    });
            })
            ->orderBy('id')
            ->pluck('id');

        $campaignsTouched = 0;
        $postsRequeued = 0;
        $scheduleAt = $today->copy()->startOfDay();

        foreach ($campaignIds as $campaignId) {
            $result = $this->carryCampaign((int) $campaignId, $scheduleAt);
            if ($result > 0) {
                $campaignsTouched++;
                $postsRequeued += $result;
            }
        }

        Log::info('ScheduleCampaignFailedPostCarryService completed', [
            'today' => $today->toDateString(),
            'grace_days' => $graceDays,
            'campaigns' => $campaignsTouched,
            'posts' => $postsRequeued,
        ]);

        return [
            'campaigns' => $campaignsTouched,
            'posts' => $postsRequeued,
        ];
    }

    /**
     * @return int Number of posts requeued
     */
    public function carryCampaign(int $campaignId, ?Carbon $scheduleAt = null): int
    {
        $scheduleAt = ($scheduleAt ?? Carbon::today())->copy()->startOfDay();

        return (int) DB::transaction(function () use ($campaignId, $scheduleAt) {
            $campaign = ScheduleCampaign::query()->lockForUpdate()->find($campaignId);
            if (! $campaign) {
                return 0;
            }

            if (in_array((string) $campaign->status, ['paused', 'cancelled'], true)) {
                return 0;
            }

            if (! $this->isWithinGraceWindow($campaign, $scheduleAt)) {
                return 0;
            }

            $failedPosts = ScheduleCampaignPost::query()
                ->where('schedule_campaign_id', $campaign->id)
                ->where('status', 'failed')
                ->where(function ($q) {
                    $q->where('is_converted_live', false)->orWhereNull('is_converted_live');
                })
                ->lockForUpdate()
                ->get();

            $count = $failedPosts->count();
            if ($count < 1) {
                return 0;
            }

            foreach ($failedPosts as $post) {
                $post->update([
                    'status' => 'queued',
                    'attempt_count' => 0,
                    'last_error' => null,
                    'next_retry_at' => null,
                    'locked_at' => null,
                    'lock_token' => null,
                    'schedule_at' => $scheduleAt,
                ]);
            }

            $this->counters->accountForFailedPostRetries($campaign, $count);
            $this->counters->syncCampaignFromPosts($campaign->fresh());

            return $count;
        });
    }

    public function isWithinGraceWindow(ScheduleCampaign $campaign, ?Carbon $today = null): bool
    {
        if (blank($campaign->schedule_to_date)) {
            return false;
        }

        $today = ($today ?? Carbon::today())->copy()->startOfDay();
        $graceDays = max(0, (int) config('campaign.schedule.failed_carry_grace_days', 2));
        $deadline = Carbon::parse($campaign->schedule_to_date)->startOfDay()->addDays($graceDays);

        return $today->lte($deadline);
    }
}
