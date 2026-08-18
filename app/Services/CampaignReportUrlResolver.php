<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Admin\Campaign;
use App\Models\Admin\HiddenLinksCampaign;
use App\Models\Admin\ScheduleCampaign;
use App\Models\Admin\ScheduleSidebarCampaign;
use App\Models\Admin\SidebarCampaign;
use App\Models\Admin\WpScheduledCampaign;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class CampaignReportUrlResolver
{
    /**
     * @var array<string, array{model: class-string<Model>, type: string, report_route: string, manage_route: string, edit_route: string}>
     */
    private const REPORT_TYPES = [
        'campaign/report' => [
            'model' => Campaign::class,
            'type' => 'PBN Post',
            'report_route' => 'admin.campaign.report',
            'manage_route' => 'admin.campaign.show',
            'edit_route' => 'admin.campaign.edit',
        ],
        'sidebar/campaign/report' => [
            'model' => SidebarCampaign::class,
            'type' => 'Sidebar Campaign',
            'report_route' => 'admin.sidebar.campaign.report',
            'manage_route' => 'admin.sidebar.campaign.show',
            'edit_route' => 'admin.sidebar.campaign.edit',
        ],
        'hidden/link/campaign/report' => [
            'model' => HiddenLinksCampaign::class,
            'type' => 'Hidden Links Campaign',
            'report_route' => 'admin.hidden.link.campaign.report',
            'manage_route' => 'admin.hidden.link.campaign.show',
            'edit_route' => 'admin.hidden.link.campaign.edit',
        ],
        'schedule/campaign/report' => [
            'model' => ScheduleCampaign::class,
            'type' => 'Scheduled PBN Post',
            'report_route' => 'admin.schedule.campaign.report',
            'manage_route' => 'admin.schedule.campaign.show',
            'edit_route' => 'admin.schedule.campaign.edit',
        ],
        'schedule/sidebar/campaign/report' => [
            'model' => ScheduleSidebarCampaign::class,
            'type' => 'Scheduled Sidebar Campaign',
            'report_route' => 'admin.schedule.sidebar.campaign.report',
            'manage_route' => 'admin.schedule.sidebar.campaign.show',
            'edit_route' => 'admin.schedule.sidebar.campaign.edit',
        ],
        'campaign/post/wp-schedule/report' => [
            'model' => WpScheduledCampaign::class,
            'type' => 'WordPress Scheduled Campaign',
            'report_route' => 'admin.wp.schedule.campaign.report',
            'manage_route' => 'admin.wp.schedule.campaign.show',
            'edit_route' => 'admin.wp.schedule.campaign.edit',
        ],
    ];

    /**
     * Resolve a report URL without making an HTTP request.
     *
     * @return array<string, mixed>|null
     */
    public function resolve(string $input, Request $request, Admin $admin): ?array
    {
        $parsed = $this->parse($input, $request);

        if ($parsed === null) {
            return null;
        }

        $configuration = self::REPORT_TYPES[$parsed['family']];
        /** @var class-string<Model> $model */
        $model = $configuration['model'];

        $campaign = $model::query()
            ->where('campaign_no', $parsed['campaign_no'])
            ->where('report_token', $parsed['token'])
            ->when(
                ! $admin->isSuperAdmin(),
                fn ($query) => $query->where('admin_id', $admin->getKey())
            )
            ->first();

        if ($campaign === null) {
            return null;
        }

        $type = $configuration['type'];
        if ($campaign instanceof Campaign && (bool) $campaign->is_sticky_campaign) {
            $type = 'Sticky PBN Post';
        } elseif ($campaign instanceof ScheduleCampaign && (bool) $campaign->is_sticky_campaign) {
            $type = 'Scheduled Sticky PBN Post';
        }

        $owner = Admin::query()->find($campaign->admin_id);
        $reportUrl = route($configuration['report_route'], [
            'campaign_no' => $campaign->campaign_no,
            'token' => $parsed['token'],
        ]);

        $bulkReplaceUrl = app(CampaignBulkReplaceUrlResolver::class)->resolveUrl($campaign, $admin);

        return [
            'campaign_id' => (int) $campaign->getKey(),
            'report_family' => $parsed['family'],
            'type' => $type,
            'campaign_no' => $campaign->campaign_no,
            'status' => $campaign->status,
            'created_at' => $campaign->created_at,
            'owner' => $owner?->name ?? 'Unknown',
            'total_targets' => (int) ($campaign->total_targets ?? 0),
            'completed_targets' => (int) ($campaign->completed_targets ?? 0),
            'failed_targets' => (int) ($campaign->failed_targets ?? 0),
            'manage_url' => route($configuration['manage_route'], $campaign->getKey()),
            'bulk_edit_url' => route($configuration['edit_route'], $campaign->getKey()),
            'report_url' => $reportUrl,
            'bulk_replace_url' => $bulkReplaceUrl,
        ];
    }

    /**
     * @return array{family: string, campaign_no: string, token: string}|null
     */
    private function parse(string $input, Request $request): ?array
    {
        $input = trim($input);

        if ($input === '' || str_contains($input, '\\') || str_contains($input, "\0")) {
            return null;
        }

        $parts = parse_url($input);
        if ($parts === false) {
            return null;
        }

        $isAbsolute = isset($parts['scheme']) || isset($parts['host']);
        if ($isAbsolute) {
            if (! $this->hasAllowedOrigin($parts, $request)) {
                return null;
            }
        } elseif (
            str_starts_with($input, '//')
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            return null;
        }

        if (isset($parts['query']) || isset($parts['fragment'])) {
            return null;
        }

        $path = trim($parts['path'] ?? '', '/');
        if ($path === '' || str_contains($path, '%')) {
            return null;
        }

        foreach (self::REPORT_TYPES as $family => $configuration) {
            $pattern = '#^'.preg_quote($family, '#').'/([A-Za-z0-9_-]+)/([A-Za-z0-9]+)(?:/export)?$#';

            if (preg_match($pattern, $path, $matches) === 1) {
                return [
                    'family' => $family,
                    'campaign_no' => $matches[1],
                    'token' => $matches[2],
                ];
            }
        }

        return null;
    }

    /**
     * @param  array<string, int|string>  $parts
     */
    private function hasAllowedOrigin(array $parts, Request $request): bool
    {
        if (
            ! isset($parts['scheme'], $parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || ! in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)
        ) {
            return false;
        }

        $candidate = $this->origin($parts);
        $allowedOrigins = array_filter([
            $this->origin(parse_url((string) config('app.url')) ?: []),
            $this->origin(parse_url($request->getSchemeAndHttpHost()) ?: []),
        ]);

        return in_array($candidate, $allowedOrigins, true);
    }

    /**
     * @param  array<string, int|string>  $parts
     */
    private function origin(array $parts): ?string
    {
        if (! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower(rtrim((string) $parts['host'], '.'));
        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);

        return "{$scheme}://{$host}:{$port}";
    }
}
