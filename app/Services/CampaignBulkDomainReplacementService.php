<?php

namespace App\Services;

use App\Data\BulkReplaceResult;
use App\Models\Admin;
use App\Models\Admin\Campaign;
use App\Models\Admin\CampaignDomain;
use App\Models\Admin\CampaignPost;
use App\Models\Admin\Domain;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CampaignBulkDomainReplacementService
{
    public function __construct(
        private readonly CampaignDomainReplacementService $replacementService,
    ) {}

    /**
     * @return array<int, string>
     */
    public function parseLines(string $text): array
    {
        $lines = preg_split('/\R/', $text) ?: [];
        $parsed = [];

        foreach ($lines as $line) {
            $normalized = normalizeDomainName($line);

            if ($normalized !== '') {
                $parsed[] = $normalized;
            }
        }

        return $parsed;
    }

    /**
     * Unique failed domain names on a campaign that have at least one replaceable post.
     *
     * @return array<int, string>
     */
    public function failedDomainNamesForCampaign(Campaign $campaign): array
    {
        return CampaignDomain::query()
            ->where('campaign_domains.campaign_id', $campaign->id)
            ->join('domains', 'domains.id', '=', 'campaign_domains.domain_id')
            ->join('campaign_posts', 'campaign_posts.campaign_domain_id', '=', 'campaign_domains.id')
            ->whereIn('campaign_posts.status', ['queued', 'failed'])
            ->whereNull('campaign_posts.remote_id')
            ->whereNull('campaign_posts.remote_url')
            ->whereNull('campaign_posts.published_at')
            ->whereNull('campaign_posts.locked_at')
            ->whereNull('campaign_posts.locked_until')
            ->whereNull('campaign_posts.lock_token')
            ->orderBy('campaign_domains.sort_order')
            ->orderBy('domains.name')
            ->select('domains.name')
            ->distinct()
            ->pluck('name')
            ->filter(fn ($name) => is_string($name) && $name !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $campaignIds
     * @return array<int, int>
     */
    public function replaceableCampaignIds(array $campaignIds): array
    {
        if ($campaignIds === []) {
            return [];
        }

        return CampaignDomain::query()
            ->whereIn('campaign_domains.campaign_id', $campaignIds)
            ->join('campaign_posts', 'campaign_posts.campaign_domain_id', '=', 'campaign_domains.id')
            ->whereIn('campaign_posts.status', ['queued', 'failed'])
            ->whereNull('campaign_posts.remote_id')
            ->whereNull('campaign_posts.remote_url')
            ->whereNull('campaign_posts.published_at')
            ->whereNull('campaign_posts.locked_at')
            ->whereNull('campaign_posts.locked_until')
            ->whereNull('campaign_posts.lock_token')
            ->distinct()
            ->pluck('campaign_domains.campaign_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function campaignHasReplaceablePosts(Campaign $campaign): bool
    {
        return $this->failedDomainNamesForCampaign($campaign) !== [];
    }

    /**
     * @param  array<int, string>  $failedLines
     * @param  array<int, string>  $replacementLines
     * @return array<int, array{
     *   line: int,
     *   failed: string,
     *   replacement: ?string,
     *   post_count: int,
     *   campaign_domain_id: ?int
     * }>
     */
    public function buildPreview(Campaign $campaign, Admin $admin, array $failedLines, array $replacementLines): array
    {
        $this->validateMappings($campaign, $admin, $failedLines, $replacementLines);

        $preview = [];
        $pairCount = min(count($failedLines), count($replacementLines));

        for ($i = 0; $i < $pairCount; $i++) {
            $failedName = $failedLines[$i];
            $replacementName = $replacementLines[$i];
            $campaignDomain = $this->findCampaignDomainByName($campaign, $failedName);

            $preview[] = [
                'line' => $i + 1,
                'failed' => $failedName,
                'replacement' => $replacementName,
                'post_count' => $campaignDomain
                    ? $this->countEligiblePostsOnCampaignDomain($campaignDomain->id)
                    : 0,
                'campaign_domain_id' => $campaignDomain?->id,
            ];
        }

        return $preview;
    }

    /**
     * @param  array<int, string>  $failedLines
     * @param  array<int, string>  $replacementLines
     */
    public function validateMappings(
        Campaign $campaign,
        Admin $admin,
        array $failedLines,
        array $replacementLines,
    ): void {
        $errors = [];

        if ($failedLines === []) {
            $errors['failed_domains'][] = 'Enter at least one failed domain.';
        }

        if (count($replacementLines) > count($failedLines)) {
            $errors['replacement_domains'][] = 'Too many replacement domains ('.count($replacementLines).') for '.count($failedLines).' failed domain(s).';
        }

        if (in_array($campaign->status ?? '', ['paused', 'cancelled'], true)) {
            $errors['campaign'][] = 'Cannot replace domains: campaign is paused or cancelled.';
        }

        $seenFailed = [];
        foreach ($failedLines as $index => $failedName) {
            $line = $index + 1;

            if (isset($seenFailed[$failedName])) {
                $errors['failed_domains'][] = "Duplicate failed domain on line {$line}: {$failedName}";

                continue;
            }

            $seenFailed[$failedName] = true;

            $failedDomain = $this->replacementService->lookupDomain($failedName);

            if (! $failedDomain) {
                $errors['failed_domains'][] = "Line {$line}: domain not found in inventory ({$failedName}).";

                continue;
            }

            $campaignDomain = $this->findCampaignDomainByDomainId($campaign, (int) $failedDomain->id);

            if (! $campaignDomain) {
                $errors['failed_domains'][] = "Line {$line}: domain is not on this campaign ({$failedName}).";

                continue;
            }

            if ($this->countEligiblePostsOnCampaignDomain($campaignDomain->id) < 1) {
                $errors['failed_domains'][] = "Line {$line}: no replaceable posts on this domain ({$failedName}).";
            }
        }

        $seenReplacement = [];
        $attachedDomainIds = CampaignDomain::query()
            ->where('campaign_id', $campaign->id)
            ->pluck('domain_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $pairCount = min(count($failedLines), count($replacementLines));

        for ($i = 0; $i < $pairCount; $i++) {
            $line = $i + 1;
            $replacementName = $replacementLines[$i];
            $failedName = $failedLines[$i];

            if (isset($seenReplacement[$replacementName])) {
                $errors['replacement_domains'][] = "Duplicate replacement domain on line {$line}: {$replacementName}";

                continue;
            }

            $seenReplacement[$replacementName] = true;

            $replacementDomain = $this->replacementService->lookupDomain($replacementName);

            if (! $replacementDomain) {
                $errors['replacement_domains'][] = "Line {$line}: replacement not found in inventory ({$replacementName}).";

                continue;
            }

            if (in_array((int) $replacementDomain->id, $attachedDomainIds, true)) {
                $errors['replacement_domains'][] = "Line {$line}: domain already on campaign ({$replacementName}).";

                continue;
            }

            if ($replacementName === $failedName) {
                $errors['replacement_domains'][] = "Line {$line}: replacement must differ from failed domain ({$replacementName}).";

                continue;
            }

            $representativePost = $this->findRepresentativePost($campaign, $failedName);

            if (! $representativePost) {
                continue;
            }

            if ($reason = $this->replacementService->ineligibilityReason(
                $campaign,
                $representativePost,
                $admin,
                $replacementDomain,
                manual: true,
            )) {
                $errors['replacement_domains'][] = "Line {$line}: {$reason}";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<int, string>  $failedLines
     * @param  array<int, string>  $replacementLines
     */
    public function execute(
        Campaign $campaign,
        Admin $admin,
        array $failedLines,
        array $replacementLines,
        string $reason = '',
    ): BulkReplaceResult {
        $this->validateMappings($campaign, $admin, $failedLines, $replacementLines);

        $pairCount = min(count($failedLines), count($replacementLines));
        $result = new BulkReplaceResult;
        $attachedDomainIds = CampaignDomain::query()
            ->where('campaign_id', $campaign->id)
            ->pluck('domain_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        for ($i = 0; $i < $pairCount; $i++) {
            $line = $i + 1;
            $failedName = $failedLines[$i];
            $replacementName = $replacementLines[$i];

            $failedDomain = $this->replacementService->lookupDomain($failedName);
            $replacementDomain = $this->replacementService->lookupDomain($replacementName);
            $representativePost = $this->findRepresentativePost($campaign, $failedName);

            if (! $failedDomain || ! $replacementDomain || ! $representativePost) {
                $result->failures[] = [
                    'line' => $line,
                    'failed' => $failedName,
                    'replacement' => $replacementName,
                    'error' => 'Mapping could not be resolved.',
                ];

                continue;
            }

            if (in_array((int) $replacementDomain->id, $attachedDomainIds, true)) {
                $result->failures[] = [
                    'line' => $line,
                    'failed' => $failedName,
                    'replacement' => $replacementName,
                    'error' => 'Replacement domain is already on this campaign.',
                ];

                continue;
            }

            $campaignDomainId = (int) $representativePost->campaign_domain_id;

            try {
                $replacement = $this->replacementService->replace(
                    $representativePost,
                    $admin,
                    (int) $replacementDomain->id,
                    (int) $failedDomain->id,
                    (string) Str::uuid(),
                    trim($reason) !== '' ? trim($reason) : 'Bulk domain replacement',
                    manual: true,
                );

                if ($replacement->state === 'completed' || $replacement->state === 'dispatch_pending') {
                    $result->domainsReplaced++;
                    $attachedDomainIds[] = (int) $replacementDomain->id;

                    $siblingCount = $this->replacementService->requeueEligiblePostsOnCampaignDomain(
                        $campaignDomainId,
                        (int) $representativePost->id,
                    );

                    $result->postsRequeued += 1 + $siblingCount;
                } else {
                    $result->failures[] = [
                        'line' => $line,
                        'failed' => $failedName,
                        'replacement' => $replacementName,
                        'error' => $replacement->error ?? 'Replacement dispatch did not complete.',
                    ];
                }
            } catch (ValidationException $exception) {
                $message = collect($exception->errors())->flatten()->first() ?? 'Validation failed.';
                $result->failures[] = [
                    'line' => $line,
                    'failed' => $failedName,
                    'replacement' => $replacementName,
                    'error' => (string) $message,
                ];
            } catch (Throwable $exception) {
                report($exception);
                $result->failures[] = [
                    'line' => $line,
                    'failed' => $failedName,
                    'replacement' => $replacementName,
                    'error' => 'Replacement could not be completed.',
                ];
            }
        }

        return $result;
    }

    private function findCampaignDomainByName(Campaign $campaign, string $domainName): ?CampaignDomain
    {
        $domain = $this->replacementService->lookupDomain($domainName);

        if (! $domain) {
            return null;
        }

        return $this->findCampaignDomainByDomainId($campaign, (int) $domain->id);
    }

    private function findCampaignDomainByDomainId(Campaign $campaign, int $domainId): ?CampaignDomain
    {
        return CampaignDomain::query()
            ->where('campaign_id', $campaign->id)
            ->where('domain_id', $domainId)
            ->first();
    }

    private function findRepresentativePost(Campaign $campaign, string $failedDomainName): ?CampaignPost
    {
        $campaignDomain = $this->findCampaignDomainByName($campaign, $failedDomainName);

        if (! $campaignDomain) {
            return null;
        }

        $posts = CampaignPost::query()
            ->where('campaign_id', $campaign->id)
            ->where('campaign_domain_id', $campaignDomain->id)
            ->whereIn('status', ['queued', 'failed'])
            ->whereNull('remote_id')
            ->whereNull('remote_url')
            ->whereNull('published_at')
            ->whereNull('locked_at')
            ->whereNull('locked_until')
            ->whereNull('lock_token')
            ->orderByRaw("CASE WHEN status = 'failed' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->with(['campaignDomain.domain', 'campaign'])
            ->get();

        return $posts->first(fn (CampaignPost $post) => CampaignDomainReplacementService::ineligibleReason($post) === null);
    }

    private function countEligiblePostsOnCampaignDomain(int $campaignDomainId): int
    {
        return CampaignPost::query()
            ->where('campaign_domain_id', $campaignDomainId)
            ->whereIn('status', ['queued', 'failed'])
            ->whereNull('remote_id')
            ->whereNull('remote_url')
            ->whereNull('published_at')
            ->whereNull('locked_at')
            ->whereNull('locked_until')
            ->whereNull('lock_token')
            ->get()
            ->filter(fn (CampaignPost $post) => CampaignDomainReplacementService::ineligibleReason($post) === null)
            ->count();
    }
}
