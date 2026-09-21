<?php

namespace App\Services;

use App\Models\Admin\Campaign;
use App\Models\Admin\ScheduleCampaign;
use App\Models\Admin\ScheduleSidebarCampaign;
use App\Models\Admin\SidebarCampaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class ConvertedCampaignReportRedirector
{
    public const FLASH_KEY = 'converted_report_notice';

    public function respond(Campaign|SidebarCampaign $source, bool $export = false): RedirectResponse|Response|null
    {
        if (! $this->isConverted($source)) {
            return null;
        }

        $target = $this->target($source);

        if ($this->isUsableTarget($target)) {
            return redirect()
                ->route($this->targetRoute($source, $export), [
                    'campaign_no' => $target->campaign_no,
                    'token' => $target->report_token,
                ])
                ->with(self::FLASH_KEY, $this->notice($target));
        }

        return response()->view('admin.campaigns.partials.converted-report-inactive', [
            'sourceCampaignNo' => (string) $source->campaign_no,
            'targetCampaignNo' => $target?->campaign_no,
        ], 410);
    }

    public function isConverted(Campaign|SidebarCampaign $source): bool
    {
        if ($source instanceof Campaign) {
            return filled($source->converted_to_schedule_campaign_id);
        }

        return filled($source->converted_to_schedule_sidebar_campaign_id);
    }

    public function target(Campaign|SidebarCampaign $source): ScheduleCampaign|ScheduleSidebarCampaign|null
    {
        return $source->convertedScheduleCampaign;
    }

    public function notice(ScheduleCampaign|ScheduleSidebarCampaign $target): string
    {
        return 'This report was converted into '.$target->campaign_no.'.';
    }

    public function targetRoute(Campaign|SidebarCampaign $source, bool $export = false): string
    {
        if ($source instanceof SidebarCampaign) {
            return $export
                ? 'admin.schedule.sidebar.campaign.report.export'
                : 'admin.schedule.sidebar.campaign.report';
        }

        return $export
            ? 'admin.schedule.campaign.report.export'
            : 'admin.schedule.campaign.report';
    }

    private function isUsableTarget(ScheduleCampaign|ScheduleSidebarCampaign|null $target): bool
    {
        return $target !== null
            && filled($target->campaign_no)
            && filled($target->report_token);
    }
}
