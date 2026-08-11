<?php

namespace App\Support;

use App\Models\Admin\BillingCampaignType;
use App\Models\Admin\Campaign;
use App\Models\Admin\HiddenLinksCampaign;
use App\Models\Admin\ScheduleCampaign;
use App\Models\Admin\ScheduleSidebarCampaign;
use App\Models\Admin\SidebarCampaign;
use InvalidArgumentException;

class BillableCampaignRegistry
{
    /** @var array<string, array{class: class-string, label: string, admin_column: string}> */
    public const TYPES = [
        'campaign' => [
            'class' => Campaign::class,
            'label' => 'PBN Post',
            'admin_column' => 'admin_id',
        ],
        'sticky_campaign' => [
            'class' => Campaign::class,
            'label' => 'Sticky Post',
            'admin_column' => 'admin_id',
        ],
        'sidebar_campaign' => [
            'class' => SidebarCampaign::class,
            'label' => 'Sidebar',
            'admin_column' => 'admin_id',
        ],
        'hidden_links_campaign' => [
            'class' => HiddenLinksCampaign::class,
            'label' => 'Hidden Links',
            'admin_column' => 'admin_id',
        ],
        'schedule_campaign' => [
            'class' => ScheduleCampaign::class,
            'label' => 'Schedule Post',
            'admin_column' => 'admin_id',
        ],
        'schedule_sidebar_campaign' => [
            'class' => ScheduleSidebarCampaign::class,
            'label' => 'Schedule Sidebar',
            'admin_column' => 'admin_id',
        ],
    ];

    public static function resolve(string $type, int $id): object
    {
        $entry = self::TYPES[$type] ?? null;
        if (! $entry) {
            throw new InvalidArgumentException("Unknown billable campaign type: {$type}");
        }

        return $entry['class']::query()->findOrFail($id);
    }

    public static function typeForModel(object $model): string
    {
        if ($model instanceof Campaign) {
            return $model->is_sticky_campaign ? 'sticky_campaign' : 'campaign';
        }

        foreach (self::TYPES as $key => $entry) {
            if ($key === 'campaign' || $key === 'sticky_campaign') {
                continue;
            }
            if ($model instanceof $entry['class']) {
                return $key;
            }
        }

        throw new InvalidArgumentException('Unsupported billable model.');
    }

    public static function labelForModel(object $model): string
    {
        return self::TYPES[self::typeForModel($model)]['label'];
    }

    public static function billingTypeForModel(object $model): BillingCampaignType
    {
        return match (self::typeForModel($model)) {
            'campaign', 'schedule_campaign' => BillingCampaignType::Post,
            'sticky_campaign' => BillingCampaignType::Sticky,
            'sidebar_campaign', 'schedule_sidebar_campaign' => BillingCampaignType::Sidebar,
            'hidden_links_campaign' => BillingCampaignType::HiddenLinks,
        };
    }
}
