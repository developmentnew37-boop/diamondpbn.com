<?php

namespace App\Services;

use App\Models\Admin\ScheduleCampaign;
use App\Models\Admin\ScheduleCampaignPost;
use App\Support\ConvertedLivePostSlot;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class ConvertedPostRemoteSyncService
{
    public function __construct(
        private readonly PostStatusApiService $api,
        private readonly ScheduleCampaignTargetCounterService $counters,
    ) {}

    public function needsRemoteSync(ScheduleCampaignPost $post): bool
    {
        if (! $post->is_converted_live || ! filled($post->remote_id)) {
            return false;
        }

        if (! $post->campaignDomain?->domain?->api_key) {
            return false;
        }

        $remote = strtolower(trim((string) ($post->remote_status ?? '')));

        if ($remote === '') {
            return true;
        }

        if ($post->status === 'success' && ! in_array($remote, ['publish', 'future', 'draft'], true)) {
            return true;
        }

        if (in_array($post->status, ['queued', 'publishing'], true) && in_array($remote, ['publish', 'future'], true)) {
            return true;
        }

        return false;
    }

    /**
     * Align local row from stored remote_status without HTTP (fixes success tab vs queued mismatch).
     */
    public function alignLocalStatusFromStoredRemote(ScheduleCampaignPost $post): bool
    {
        if (! $post->is_converted_live) {
            return false;
        }

        $remote = strtolower(trim((string) ($post->remote_status ?? '')));
        if ($remote === '') {
            return false;
        }

        return $this->applyRemoteSnapshot($post, [
            'ok' => true,
            'status' => $remote,
            'remote_url' => $post->remote_url,
        ]);
    }

    public function syncCampaignPosts(ScheduleCampaign $campaign): int
    {
        if (! filled($campaign->converted_from_campaign_id)) {
            return 0;
        }

        $allPosts = ScheduleCampaignPost::query()
            ->with(['campaignDomain.domain', 'campaign'])
            ->where('schedule_campaign_id', $campaign->id)
            ->where('is_converted_live', true)
            ->orderBy('id')
            ->get();

        $updated = 0;

        foreach ($allPosts as $post) {
            if ($this->alignLocalStatusFromStoredRemote($post)) {
                $post->save();
                $updated++;
            }
        }

        $toFetch = ScheduleCampaignPost::query()
            ->with(['campaignDomain.domain', 'campaign'])
            ->where('schedule_campaign_id', $campaign->id)
            ->where('is_converted_live', true)
            ->orderBy('id')
            ->get()
            ->filter(fn (ScheduleCampaignPost $post) => $this->needsRemoteSync($post));

        $updated += $this->syncPostCollection($toFetch);

        if ($updated > 0) {
            $this->counters->syncCampaignFromPosts($campaign->fresh());
        }

        return $updated;
    }

    /**
     * @param  Collection<int, ScheduleCampaignPost>  $posts
     */
    public function syncPostCollection(Collection $posts): int
    {
        if ($posts->isEmpty()) {
            return 0;
        }

        $responses = Http::pool(function ($pool) use ($posts) {
            foreach ($posts as $post) {
                $domain = $post->campaignDomain?->domain;
                if (! $domain || ! filled($domain->api_key)) {
                    continue;
                }

                $baseUrl = $this->api->normalizeBaseUrl((string) $domain->name);
                $url = $baseUrl.'/wp-json/external/v1/posts/'.$post->remote_id
                    .'?api_key='.urlencode((string) $domain->api_key);

                $pool->as('post_'.$post->id)
                    ->withoutVerifying()
                    ->timeout(90)
                    ->acceptJson()
                    ->get($url);
            }
        });

        $updated = 0;

        foreach ($posts as $post) {
            $key = 'post_'.$post->id;
            if (! isset($responses[$key])) {
                continue;
            }

            $response = $responses[$key];
            if (! $response instanceof Response) {
                continue;
            }

            $json = $response->json();
            $body = is_array($json) ? $json : null;

            if (! $response->successful() || ! is_array($body)) {
                continue;
            }

            $snapshot = [
                'ok' => true,
                'status' => $this->api->extractPostStatus($body),
                'remote_url' => $this->api->extractRemoteUrl($body),
                'body' => $body,
            ];

            if ($this->applyRemoteSnapshot($post, $snapshot)) {
                $post->save();
                $updated++;
            }
        }

        return $updated;
    }

    public function syncPost(ScheduleCampaignPost $post): bool
    {
        if (! $this->needsRemoteSync($post)) {
            return false;
        }

        $domain = $post->campaignDomain?->domain;
        if (! $domain || ! filled($domain->api_key)) {
            return false;
        }

        $snapshot = $this->api->fetchPost(
            (string) $domain->name,
            (string) $domain->api_key,
            (string) $post->remote_id,
        );

        if (! $snapshot['ok']) {
            return false;
        }

        if (! $this->applyRemoteSnapshot($post, $snapshot)) {
            return false;
        }

        $post->save();

        if ($post->campaign) {
            $this->counters->syncCampaignFromPosts($post->campaign);
        }

        return true;
    }

    /**
     * @param  array{ok: bool, status: string|null, remote_url: string|null, body?: array<string, mixed>|null}  $snapshot
     */
    public function applyRemoteSnapshot(ScheduleCampaignPost $post, array $snapshot): bool
    {
        $remoteStatus = $snapshot['status'] ?? null;
        if (! is_string($remoteStatus) || $remoteStatus === '') {
            return false;
        }

        $remoteStatus = strtolower(trim($remoteStatus));
        $changed = false;

        if ($snapshot['remote_url'] && $snapshot['remote_url'] !== $post->remote_url) {
            $post->remote_url = $snapshot['remote_url'];
            $changed = true;
        }

        if ($remoteStatus !== $post->remote_status) {
            $post->remote_status = $remoteStatus;
            $changed = true;
        }

        if (isset($snapshot['body']) && is_array($snapshot['body'])) {
            $post->remote_response = $snapshot['body'];
            $changed = true;
        }

        if ($remoteStatus === 'draft' && $post->status === 'success') {
            $post->status = 'failed';
            $post->conversion_phase = 'failed';
            $post->last_error = 'WordPress post is still draft (not public).';
            $post->last_conversion_error = $post->last_error;
            $changed = true;
        } elseif ($remoteStatus === 'publish') {
            $slotDue = ConvertedLivePostSlot::isDue($post->schedule_at);

            if ($slotDue) {
                if ($post->status !== 'success') {
                    $post->status = 'success';
                    $changed = true;
                }
                if ($post->conversion_phase !== 'published') {
                    $post->conversion_phase = 'published';
                    $changed = true;
                }
                $post->last_error = null;
                $post->last_conversion_error = null;
                if (! $post->published_at) {
                    $post->published_at = now();
                    $changed = true;
                }
            } elseif ($post->status === 'success') {
                $post->status = 'queued';
                $post->conversion_phase = in_array($post->conversion_phase, ['published', 'scheduled_publish'], true)
                    ? 'drafted'
                    : $post->conversion_phase;
                $changed = true;
            }
        } elseif ($remoteStatus === 'future') {
            $slotDue = ConvertedLivePostSlot::isDue($post->schedule_at);

            if ($slotDue) {
                if ($post->status !== 'success') {
                    $post->status = 'success';
                    $changed = true;
                }
                if ($post->conversion_phase !== 'scheduled_publish') {
                    $post->conversion_phase = 'scheduled_publish';
                    $changed = true;
                }
            } elseif ($post->status === 'success') {
                $post->status = 'queued';
                $changed = true;
            }
        }

        return $changed;
    }
}
