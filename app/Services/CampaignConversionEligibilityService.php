<?php

namespace App\Services;

use App\Models\Admin\Campaign;
use App\Models\Admin\CampaignPost;
use Illuminate\Database\Eloquent\Builder;

class CampaignConversionEligibilityService
{
    public const MIN_AGENT_VERSION = '8.3.0';

    public function campaignQueryForAdmin(int $adminId, bool $isSuperAdmin): Builder
    {
        $q = Campaign::query()
            ->whereNull('converted_to_schedule_campaign_id')
            ->whereIn('status', ['completed', 'semi_failed', 'running', 'failed', 'queued', 'paused']);

        if (! $isSuperAdmin) {
            $q->where('admin_id', $adminId);
        }

        return $q;
    }

    /**
     * @return array{
     *   convertible: int,
     *   failed: int,
     *   in_progress: int,
     *   total_posts: int,
     *   failed_post_ids: int[],
     *   all_live: bool,
     *   can_skip_recover: bool
     * }
     */
    public function summarize(Campaign $campaign): array
    {
        $posts = CampaignPost::query()
            ->where('campaign_id', $campaign->id)
            ->get(['id', 'status', 'remote_id', 'delivery_state']);

        $convertible = 0;
        $failed = 0;
        $inProgress = 0;
        $failedIds = [];

        foreach ($posts as $post) {
            if ($this->isConvertiblePost($post)) {
                $convertible++;
            } elseif (in_array($post->status, ['queued', 'publishing'], true)) {
                $inProgress++;
            } elseif ($post->status === 'failed') {
                $failed++;
                $failedIds[] = (int) $post->id;
            }
        }

        $allLive = $failed === 0 && $inProgress === 0 && $convertible > 0;

        return [
            'convertible' => $convertible,
            'failed' => $failed,
            'in_progress' => $inProgress,
            'total_posts' => $posts->count(),
            'failed_post_ids' => $failedIds,
            'all_live' => $allLive,
            'can_skip_recover' => $allLive,
        ];
    }

    public function isConvertiblePost(CampaignPost $post): bool
    {
        return $post->status === 'success'
            && filled($post->remote_id);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, CampaignPost>
     */
    public function convertiblePosts(Campaign $campaign)
    {
        return CampaignPost::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', 'success')
            ->whereNotNull('remote_id')
            ->where('remote_id', '!=', '')
            ->orderBy('id')
            ->get();
    }
}
