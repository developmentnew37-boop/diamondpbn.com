<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Admin\Campaign;
use App\Models\Admin\CampaignArticle;
use App\Models\Admin\HiddenLinksCampaignLinks;
use App\Models\Admin\ScheduleCampaign;
use App\Models\Admin\ScheduleCampaignArticle;
use App\Models\Admin\ScheduleSidebarCampaignLink;
use App\Models\Admin\SidebarCampaignLink;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CampaignKeywordUrlLookupService
{
    /**
     * @var array<int, array{
     *     link_model: class-string<Model>,
     *     url_column: string,
     *     keyword_column: string,
     *     campaign_relation: string,
     *     type: string,
     *     report_route: string,
     *     manage_route: string,
     *     sticky_type?: string
     * }>
     */
    private const SOURCES = [
        [
            'link_model' => CampaignArticle::class,
            'url_column' => 'url',
            'keyword_column' => 'keyword',
            'campaign_relation' => 'campaign',
            'type' => 'PBN Post',
            'sticky_type' => 'Sticky PBN Post',
            'report_route' => 'admin.campaign.report',
            'manage_route' => 'admin.campaign.show',
        ],
        [
            'link_model' => SidebarCampaignLink::class,
            'url_column' => 'target_url',
            'keyword_column' => 'anchor_keyword',
            'campaign_relation' => 'campaign',
            'type' => 'Sidebar Campaign',
            'report_route' => 'admin.sidebar.campaign.report',
            'manage_route' => 'admin.sidebar.campaign.show',
        ],
        [
            'link_model' => HiddenLinksCampaignLinks::class,
            'url_column' => 'target_url',
            'keyword_column' => 'anchor_keyword',
            'campaign_relation' => 'campaign',
            'type' => 'Hidden Links Campaign',
            'report_route' => 'admin.hidden.link.campaign.report',
            'manage_route' => 'admin.hidden.link.campaign.show',
        ],
        [
            'link_model' => ScheduleCampaignArticle::class,
            'url_column' => 'url',
            'keyword_column' => 'keyword',
            'campaign_relation' => 'campaign',
            'type' => 'Scheduled PBN Post',
            'sticky_type' => 'Scheduled Sticky PBN Post',
            'report_route' => 'admin.schedule.campaign.report',
            'manage_route' => 'admin.schedule.campaign.show',
        ],
        [
            'link_model' => ScheduleSidebarCampaignLink::class,
            'url_column' => 'target_url',
            'keyword_column' => 'anchor_keyword',
            'campaign_relation' => 'campaign',
            'type' => 'Scheduled Sidebar Campaign',
            'report_route' => 'admin.schedule.sidebar.campaign.report',
            'manage_route' => 'admin.schedule.sidebar.campaign.show',
        ],
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $input, Admin $admin): array
    {
        $input = trim($input);

        if ($input === '') {
            return [];
        }

        $results = [];

        foreach (self::SOURCES as $source) {
            $results = array_merge($results, $this->searchSource($source, $input, $admin));
        }

        usort($results, function (array $left, array $right) {
            $leftTime = $left['created_at']?->getTimestamp() ?? 0;
            $rightTime = $right['created_at']?->getTimestamp() ?? 0;

            return $rightTime <=> $leftTime;
        });

        return $results;
    }

    /**
     * @param  array<string, mixed>  $source
     * @return list<array<string, mixed>>
     */
    private function searchSource(array $source, string $input, Admin $admin): array
    {
        /** @var class-string<Model> $linkModel */
        $linkModel = $source['link_model'];
        $urlColumn = $source['url_column'];
        $keywordColumn = $source['keyword_column'];
        $campaignRelation = $source['campaign_relation'];

        $likeNeedles = $this->likeNeedles($input);

        if ($likeNeedles === []) {
            return [];
        }

        $rows = $linkModel::query()
            ->whereNotNull($urlColumn)
            ->where($urlColumn, '!=', '')
            ->where(function (Builder $query) use ($urlColumn, $likeNeedles) {
                foreach ($likeNeedles as $needle) {
                    $query->orWhere($urlColumn, 'like', '%'.$needle.'%');
                }
            })
            ->whereHas($campaignRelation, function (Builder $query) use ($admin) {
                if (! $admin->isSuperAdmin()) {
                    $query->where('admin_id', $admin->getKey());
                }
            })
            ->with([$campaignRelation])
            ->limit(250)
            ->get();

        $seen = [];
        $matches = [];

        foreach ($rows as $row) {
            $stored = (string) ($row->{$urlColumn} ?? '');

            if (! $this->storedValueContainsUrl($stored, $input)) {
                continue;
            }

            $campaign = $row->{$campaignRelation};

            if (! $campaign instanceof Model) {
                continue;
            }

            $dedupeKey = $source['type'].'|'.(string) $campaign->getKey();

            if (isset($seen[$dedupeKey])) {
                continue;
            }

            $seen[$dedupeKey] = true;

            $type = $this->resolveCampaignTypeLabel($campaign, $source);
            $owner = Admin::query()->find($campaign->admin_id);
            $token = (string) ($campaign->report_token ?? '');

            $bulkReplaceUrl = app(CampaignBulkReplaceUrlResolver::class)->resolveUrl($campaign, $admin);

            $matches[] = [
                'type' => $type,
                'campaign_id' => (int) $campaign->getKey(),
                'campaign_no' => (string) $campaign->campaign_no,
                'status' => (string) $campaign->status,
                'created_at' => $campaign->created_at,
                'owner' => $owner?->name ?? 'Unknown',
                'total_targets' => (int) ($campaign->total_targets ?? 0),
                'completed_targets' => (int) ($campaign->completed_targets ?? 0),
                'failed_targets' => (int) ($campaign->failed_targets ?? 0),
                'matched_url' => $this->firstMatchingStoredUrl($stored, $input),
                'keyword' => $this->displayKeyword((string) ($row->{$keywordColumn} ?? '')),
                'manage_url' => route($source['manage_route'], $campaign->getKey()),
                'report_url' => $token !== ''
                    ? route($source['report_route'], [
                        'campaign_no' => $campaign->campaign_no,
                        'token' => $token,
                    ])
                    : null,
                'bulk_replace_url' => $bulkReplaceUrl,
            ];
        }

        return $matches;
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function resolveCampaignTypeLabel(Model $campaign, array $source): string
    {
        if ($campaign instanceof Campaign && (bool) $campaign->is_sticky_campaign) {
            return (string) ($source['sticky_type'] ?? $source['type']);
        }

        if ($campaign instanceof ScheduleCampaign && (bool) $campaign->is_sticky_campaign) {
            return (string) ($source['sticky_type'] ?? $source['type']);
        }

        return (string) $source['type'];
    }

    /**
     * @return list<string>
     */
    private function likeNeedles(string $input): array
    {
        $trimmed = trim($input);
        $withoutScheme = $this->stripScheme($trimmed);

        $candidates = [
            $trimmed,
            $withoutScheme,
            str_replace('/', '\/', $trimmed),
            str_replace('/', '\/', $withoutScheme),
        ];

        $needles = [];

        foreach ($candidates as $candidate) {
            $escaped = $this->escapeLike($candidate);

            if ($escaped !== '') {
                $needles[] = $escaped;
            }
        }

        $needles = array_values(array_unique($needles));

        $hostNeedle = $this->hostLikeNeedle($trimmed);

        if ($hostNeedle !== null) {
            $needles[] = $hostNeedle;
        }

        $needles = array_values(array_unique($needles));

        return array_values(array_filter($needles, fn (string $needle) => mb_strlen($needle) >= 3));
    }

    private function hostLikeNeedle(string $input): ?string
    {
        $comparable = $this->comparableUrl($input);

        if ($comparable === '') {
            return null;
        }

        $host = explode('/', $comparable, 2)[0];

        if ($host === '') {
            return null;
        }

        return $this->escapeLike($host);
    }

    private function storedValueContainsUrl(string $stored, string $search): bool
    {
        $searchComparable = $this->comparableUrl($search);

        if ($searchComparable === '') {
            return false;
        }

        foreach ($this->expandStoredUrls($stored) as $storedUrl) {
            if ($this->comparableUrl($storedUrl) === $searchComparable) {
                return true;
            }
        }

        return false;
    }

    private function firstMatchingStoredUrl(string $stored, string $search): string
    {
        $searchComparable = $this->comparableUrl($search);

        foreach ($this->expandStoredUrls($stored) as $storedUrl) {
            if ($this->comparableUrl($storedUrl) === $searchComparable) {
                return trim($storedUrl);
            }
        }

        return trim($stored);
    }

    /**
     * @return list<string>
     */
    private function expandStoredUrls(string $stored): array
    {
        $stored = trim($stored);

        if ($stored === '') {
            return [];
        }

        $decoded = json_decode($stored, true);

        if (is_array($decoded)) {
            return collect($decoded)
                ->filter(fn ($value) => is_string($value) && trim($value) !== '')
                ->map(fn (string $value) => trim($value))
                ->values()
                ->all();
        }

        return [$stored];
    }

    private function comparableUrl(string $url): string
    {
        $url = strtolower(trim($url));
        $url = rtrim($url, '/');
        $url = preg_replace('#^https?://#', '', $url) ?? $url;
        $url = preg_replace('#^www\.#', '', $url) ?? $url;

        return $url;
    }

    private function stripScheme(string $url): string
    {
        return preg_replace('#^https?://#i', '', $url) ?? $url;
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }

    private function displayKeyword(string $keyword): ?string
    {
        $keyword = trim($keyword);

        if ($keyword === '') {
            return null;
        }

        $decoded = json_decode($keyword, true);

        if (is_array($decoded)) {
            $parts = collect($decoded)
                ->filter(fn ($value) => is_string($value) && trim($value) !== '')
                ->map(fn (string $value) => trim($value))
                ->values()
                ->all();

            return $parts === [] ? null : implode(', ', $parts);
        }

        return $keyword;
    }
}
