<?php

namespace App\Services\LiveTaskDomainReplacement;

use App\Jobs\DraftConvertedLivePostsJob;
use App\Jobs\DraftConvertedLiveSidebarTasksJob;
use App\Jobs\PublishHiddenLinksJob;
use App\Jobs\PublishScheduledCampaignPostJob;
use App\Jobs\PublishScheduledSidebarBlogrollJob;
use App\Jobs\PublishSidebarBlogrollJob;
use App\Jobs\RepublishConvertedSchedulePostAfterDomainReplaceJob;
use App\Jobs\RepublishConvertedScheduleSidebarAfterDomainReplaceJob;
use App\Models\Admin\HiddenLinksCampaign;
use App\Models\Admin\HiddenLinksCampaignDomainReplacement;
use App\Models\Admin\HiddenLinksCampaignDomains;
use App\Models\Admin\HiddenLinksCampaignTasks;
use App\Models\Admin\ScheduleCampaign;
use App\Models\Admin\ScheduleCampaignDomain;
use App\Models\Admin\ScheduleCampaignDomainReplacement;
use App\Models\Admin\ScheduleCampaignPost;
use App\Models\Admin\ScheduleSidebarCampaign;
use App\Models\Admin\ScheduleSidebarCampaignDomain;
use App\Models\Admin\ScheduleSidebarCampaignDomainReplacement;
use App\Models\Admin\ScheduleSidebarCampaignTask;
use App\Models\Admin\SidebarCampaign;
use App\Models\Admin\SidebarCampaignDomain;
use App\Models\Admin\SidebarCampaignDomainReplacement;
use App\Models\Admin\SidebarCampaignTask;

final class LiveTaskReplacementProfile
{
    public function __construct(
        public string $label,
        public string $replacementModel,
        public string $taskModel,
        public string $campaignModel,
        public string $campaignDomainModel,
        public string $auditCampaignIdColumn,
        public string $auditTaskIdColumn,
        public string $auditDomainRowIdColumn,
        public string $taskCampaignIdColumn,
        public string $taskDomainRowIdColumn,
        public string $domainRowCampaignIdColumn,
        public string $taskDomainRelation,
        public string $publishJobClass,
        public string $queue,
        public string $showRouteName,
        public bool $supportsConvertedLive = false,
    ) {}

    public static function sidebar(): self
    {
        return new self(
            label: 'sidebar',
            replacementModel: SidebarCampaignDomainReplacement::class,
            taskModel: SidebarCampaignTask::class,
            campaignModel: SidebarCampaign::class,
            campaignDomainModel: SidebarCampaignDomain::class,
            auditCampaignIdColumn: 'sidebar_campaign_id',
            auditTaskIdColumn: 'sidebar_campaign_task_id',
            auditDomainRowIdColumn: 'sidebar_campaign_domain_id',
            taskCampaignIdColumn: 'sidebar_campaign_id',
            taskDomainRowIdColumn: 'sidebar_campaign_domain_id',
            domainRowCampaignIdColumn: 'sidebar_campaign_id',
            taskDomainRelation: 'domainRow',
            publishJobClass: PublishSidebarBlogrollJob::class,
            queue: 'sidebar_campaigns',
            showRouteName: 'admin.sidebar.campaign.show',
        );
    }

    public static function hiddenLinks(): self
    {
        return new self(
            label: 'hidden_links',
            replacementModel: HiddenLinksCampaignDomainReplacement::class,
            taskModel: HiddenLinksCampaignTasks::class,
            campaignModel: HiddenLinksCampaign::class,
            campaignDomainModel: HiddenLinksCampaignDomains::class,
            auditCampaignIdColumn: 'hidden_links_campaign_id',
            auditTaskIdColumn: 'hidden_links_campaign_task_id',
            auditDomainRowIdColumn: 'hidden_links_campaign_domain_id',
            taskCampaignIdColumn: 'hidden_links_campaigns_id',
            taskDomainRowIdColumn: 'hidden_links_campaigns_domain_id',
            domainRowCampaignIdColumn: 'hidden_links_campaigns_id',
            taskDomainRelation: 'domainRow',
            publishJobClass: PublishHiddenLinksJob::class,
            queue: 'hidden_links_campaigns',
            showRouteName: 'admin.hidden.link.campaign.show',
        );
    }

    public static function schedulePost(): self
    {
        return new self(
            label: 'schedule_post',
            replacementModel: ScheduleCampaignDomainReplacement::class,
            taskModel: ScheduleCampaignPost::class,
            campaignModel: ScheduleCampaign::class,
            campaignDomainModel: ScheduleCampaignDomain::class,
            auditCampaignIdColumn: 'schedule_campaign_id',
            auditTaskIdColumn: 'schedule_campaign_post_id',
            auditDomainRowIdColumn: 'schedule_campaign_domain_id',
            taskCampaignIdColumn: 'schedule_campaign_id',
            taskDomainRowIdColumn: 'schedule_campaign_domain_id',
            domainRowCampaignIdColumn: 'schedule_campaign_id',
            taskDomainRelation: 'campaignDomain',
            publishJobClass: PublishScheduledCampaignPostJob::class,
            queue: 'scheduled_campaigns',
            showRouteName: 'admin.schedule.campaign.show',
        );
    }

    public static function scheduleSidebar(): self
    {
        return new self(
            label: 'schedule_sidebar',
            replacementModel: ScheduleSidebarCampaignDomainReplacement::class,
            taskModel: ScheduleSidebarCampaignTask::class,
            campaignModel: ScheduleSidebarCampaign::class,
            campaignDomainModel: ScheduleSidebarCampaignDomain::class,
            auditCampaignIdColumn: 'schedule_sidebar_campaign_id',
            auditTaskIdColumn: 'schedule_sidebar_campaign_task_id',
            auditDomainRowIdColumn: 'schedule_sidebar_campaign_domain_id',
            taskCampaignIdColumn: 'schedule_sidebar_campaign_id',
            taskDomainRowIdColumn: 'schedule_sidebar_campaign_domain_id',
            domainRowCampaignIdColumn: 'schedule_sidebar_campaign_id',
            taskDomainRelation: 'domain',
            publishJobClass: PublishScheduledSidebarBlogrollJob::class,
            queue: 'scheduled_sidebar_campaigns',
            showRouteName: 'admin.schedule.sidebar.campaign.show',
        );
    }

    public static function convertedSchedulePost(): self
    {
        return new self(
            label: 'converted_schedule_post',
            replacementModel: ScheduleCampaignDomainReplacement::class,
            taskModel: ScheduleCampaignPost::class,
            campaignModel: ScheduleCampaign::class,
            campaignDomainModel: ScheduleCampaignDomain::class,
            auditCampaignIdColumn: 'schedule_campaign_id',
            auditTaskIdColumn: 'schedule_campaign_post_id',
            auditDomainRowIdColumn: 'schedule_campaign_domain_id',
            taskCampaignIdColumn: 'schedule_campaign_id',
            taskDomainRowIdColumn: 'schedule_campaign_domain_id',
            domainRowCampaignIdColumn: 'schedule_campaign_id',
            taskDomainRelation: 'campaignDomain',
            publishJobClass: RepublishConvertedSchedulePostAfterDomainReplaceJob::class,
            queue: DraftConvertedLivePostsJob::QUEUE,
            showRouteName: 'admin.schedule.campaign.show',
            supportsConvertedLive: true,
        );
    }

    public static function convertedScheduleSidebar(): self
    {
        return new self(
            label: 'converted_schedule_sidebar',
            replacementModel: ScheduleSidebarCampaignDomainReplacement::class,
            taskModel: ScheduleSidebarCampaignTask::class,
            campaignModel: ScheduleSidebarCampaign::class,
            campaignDomainModel: ScheduleSidebarCampaignDomain::class,
            auditCampaignIdColumn: 'schedule_sidebar_campaign_id',
            auditTaskIdColumn: 'schedule_sidebar_campaign_task_id',
            auditDomainRowIdColumn: 'schedule_sidebar_campaign_domain_id',
            taskCampaignIdColumn: 'schedule_sidebar_campaign_id',
            taskDomainRowIdColumn: 'schedule_sidebar_campaign_domain_id',
            domainRowCampaignIdColumn: 'schedule_sidebar_campaign_id',
            taskDomainRelation: 'domain',
            publishJobClass: RepublishConvertedScheduleSidebarAfterDomainReplaceJob::class,
            queue: DraftConvertedLiveSidebarTasksJob::QUEUE,
            showRouteName: 'admin.schedule.sidebar.campaign.show',
            supportsConvertedLive: true,
        );
    }
}
