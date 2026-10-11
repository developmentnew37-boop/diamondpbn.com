<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Admin\Domain;
use App\Models\Admin\DomainCategory;
use App\Models\Admin\DomainSet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DomainDetailsLookupService
{
    /**
     * @return array<string, mixed>|null Null when the input has no domain name.
     */
    public function lookup(string $input): ?array
    {
        $normalized = normalizeDomainName($input);

        if ($normalized === '') {
            return null;
        }

        $domain = $this->findDomain($normalized);

        if (! $domain) {
            return ['found' => false];
        }

        return $this->present($domain);
    }

    private function findDomain(string $normalized): ?Domain
    {
        $domain = Domain::findByNormalizedName($normalized);

        if ($domain) {
            return $domain;
        }

        $alternate = str_starts_with($normalized, 'www.')
            ? substr($normalized, 4)
            : 'www.'.$normalized;

        if ($alternate === '' || $alternate === $normalized) {
            return null;
        }

        return Domain::findByNormalizedName($alternate);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Domain $domain): array
    {
        $campaigns = $this->campaigns((int) $domain->id);

        return [
            'found' => true,
            'name' => (string) $domain->name,
            'category' => $this->categoryName($domain),
            'added_by' => $this->adminName($domain),
            'created_at' => $this->formatTime($domain->created_at),
            'updated_at' => $this->formatTime($domain->updated_at),
            'status' => (int) $domain->status === 1 ? 'Connected' : 'Disconnected',
            'last_seen_at' => $this->formatTime($domain->last_seen_at),
            'agent_version' => (string) ($domain->agent_version ?? ''),
            'last_status_message' => (string) ($domain->last_status_message ?? ''),
            'campaign_total' => $campaigns['total'],
            'campaign_counts' => $campaigns['counts'],
            'campaigns' => $campaigns['page'],
            'sets' => $this->sets((int) $domain->id),
        ];
    }

    private function categoryName(Domain $domain): string
    {
        if (! $domain->domain_category_id) {
            return '';
        }

        return (string) (DomainCategory::query()->whereKey($domain->domain_category_id)->value('name') ?? '');
    }

    private function adminName(Domain $domain): string
    {
        if (! $domain->admin_id) {
            return '';
        }

        return (string) (Admin::query()->whereKey($domain->admin_id)->value('name') ?? '');
    }

    /**
     * @return array{total: int, counts: array<string, int>, page: LengthAwarePaginator<int, array{type: string, campaign_no: string, created_at: string, url: ?string}>}
     */
    private function campaigns(int $domainId): array
    {
        $counts = [];
        $total = 0;

        foreach ($this->campaignSources() as $source) {
            $count = (int) DB::table($source['pivot'])->where('domain_id', $domainId)->count();
            $total += $count;

            if ($count === 0) {
                continue;
            }

            if ($source['source'] !== 'pbn') {
                $counts[$source['label']] = $count;

                continue;
            }

            $sticky = (int) DB::table('campaign_domains as pivot')
                ->join('campaigns as parent', 'parent.id', '=', 'pivot.campaign_id')
                ->where('pivot.domain_id', $domainId)
                ->where('parent.is_sticky_campaign', 1)
                ->count();
            $plain = $count - $sticky;

            if ($plain > 0) {
                $counts['PBN post'] = $plain;
            }

            if ($sticky > 0) {
                $counts['Sticky post'] = $sticky;
            }
        }

        $union = null;

        foreach ($this->campaignSources() as $source) {
            $branch = $this->campaignBranch($source, $domainId);
            $union = $union === null ? $branch : $union->unionAll($branch);
        }

        $page = DB::query()
            ->fromSub($union, 'domain_campaigns')
            ->orderByDesc('created_at')
            ->orderByDesc('campaign_id')
            ->paginate(20, ['*'], 'campaigns_page')
            ->withQueryString();

        $page->setCollection($page->getCollection()->map(function (object $row): array {
            $campaignId = (int) $row->campaign_id;
            $hasParent = $row->campaign_no !== null && $row->campaign_no !== '';

            return [
                'type' => $this->campaignType((string) $row->source, (int) $row->is_sticky),
                'campaign_no' => $hasParent ? (string) $row->campaign_no : (string) $campaignId,
                'created_at' => $this->formatTime($row->created_at),
                'url' => $hasParent ? $this->campaignUrl((string) $row->source, $campaignId) : null,
            ];
        }));

        return [
            'total' => $total,
            'counts' => $counts,
            'page' => $page,
        ];
    }

    /**
     * @param  array{source: string, pivot: string, parent: string, foreign: string, label: string, sticky: string, route: string, routeKey: string}  $source
     */
    private function campaignBranch(array $source, int $domainId): Builder
    {
        return DB::table($source['pivot'].' as pivot')
            ->leftJoin($source['parent'].' as parent', 'parent.id', '=', 'pivot.'.$source['foreign'])
            ->where('pivot.domain_id', $domainId)
            ->selectRaw(
                '? as source, pivot.'.$source['foreign'].' as campaign_id, parent.campaign_no as campaign_no, '.$source['sticky'].' as is_sticky, COALESCE(parent.created_at, pivot.created_at) as created_at',
                [$source['source']],
            );
    }

    /**
     * @return list<array{source: string, pivot: string, parent: string, foreign: string, label: string, sticky: string, route: string, routeKey: string}>
     */
    private function campaignSources(): array
    {
        return [
            [
                'source' => 'pbn',
                'pivot' => 'campaign_domains',
                'parent' => 'campaigns',
                'foreign' => 'campaign_id',
                'label' => 'PBN post',
                'sticky' => 'COALESCE(parent.is_sticky_campaign, 0)',
                'route' => 'admin.campaign.show',
                'routeKey' => 'campaign',
            ],
            [
                'source' => 'sidebar',
                'pivot' => 'sidebar_campaign_domains',
                'parent' => 'sidebar_campaigns',
                'foreign' => 'sidebar_campaign_id',
                'label' => 'Sidebar',
                'sticky' => '0',
                'route' => 'admin.sidebar.campaign.show',
                'routeKey' => 'campaign',
            ],
            [
                'source' => 'hidden',
                'pivot' => 'hidden_links_campaigns_domains',
                'parent' => 'hidden_links_campaigns',
                'foreign' => 'hidden_links_campaigns_id',
                'label' => 'Hidden links',
                'sticky' => '0',
                'route' => 'admin.hidden.link.campaign.show',
                'routeKey' => 'campaign',
            ],
            [
                'source' => 'schedule',
                'pivot' => 'schedule_campaigns_domains',
                'parent' => 'schedule_campaigns',
                'foreign' => 'schedule_campaign_id',
                'label' => 'Schedule post',
                'sticky' => '0',
                'route' => 'admin.schedule.campaign.show',
                'routeKey' => 'schedule',
            ],
            [
                'source' => 'schedule_sidebar',
                'pivot' => 'schedule_sidebar_campaign_domains',
                'parent' => 'schedule_sidebar_campaigns',
                'foreign' => 'schedule_sidebar_campaign_id',
                'label' => 'Schedule sidebar',
                'sticky' => '0',
                'route' => 'admin.schedule.sidebar.campaign.show',
                'routeKey' => 'schedule',
            ],
            [
                'source' => 'wp',
                'pivot' => 'wp_scheduled_campaign_domains',
                'parent' => 'wp_scheduled_campaigns',
                'foreign' => 'wp_scheduled_campaign_id',
                'label' => 'WP scheduled',
                'sticky' => '0',
                'route' => 'admin.wp.schedule.campaign.show',
                'routeKey' => 'wp_schedule',
            ],
        ];
    }

    private function campaignType(string $source, int $isSticky): string
    {
        if ($source === 'pbn') {
            return $isSticky === 1 ? 'Sticky post' : 'PBN post';
        }

        foreach ($this->campaignSources() as $definition) {
            if ($definition['source'] === $source) {
                return $definition['label'];
            }
        }

        return 'Campaign';
    }

    private function campaignUrl(string $source, int $campaignId): ?string
    {
        foreach ($this->campaignSources() as $definition) {
            if ($definition['source'] === $source) {
                return route($definition['route'], [$definition['routeKey'] => $campaignId]);
            }
        }

        return null;
    }

    /**
     * @return list<array{name: string, url: string}>
     */
    private function sets(int $domainId): array
    {
        $sets = [];

        foreach (DomainSet::query()->get(['id', 'name', 'domains']) as $set) {
            if (! in_array($domainId, $this->setDomainIds($set->domains), true)) {
                continue;
            }

            $sets[] = [
                'name' => (string) $set->name,
                'url' => route('admin.set.show', ['set' => $set->id]),
            ];
        }

        return $sets;
    }

    /**
     * @return list<int>
     */
    private function setDomainIds(mixed $domains): array
    {
        if (is_string($domains)) {
            $decoded = json_decode($domains, true);
            $domains = is_array($decoded) ? $decoded : null;
        }

        if (! is_array($domains) || array_is_list($domains) === false) {
            return [];
        }

        $ids = [];

        foreach ($domains as $id) {
            if (is_numeric($id)) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }

    private function formatTime(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return Carbon::parse($value)->timezone(config('app.timezone'))->format('d M Y, h:i A');
    }
}
