<?php

namespace App\Models\Admin;

enum BillingCampaignType: string
{
    case Post = 'post';
    case Sidebar = 'sidebar';
    case HiddenLinks = 'hidden_links';
    case Sticky = 'sticky';

    public function priceColumn(): string
    {
        return match ($this) {
            self::Post => 'post_price',
            self::Sidebar => 'sidebar_price',
            self::HiddenLinks => 'hidden_links_price',
            self::Sticky => 'sticky_price',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Post => 'Post',
            self::Sidebar => 'Sidebar',
            self::HiddenLinks => 'Hidden Links',
            self::Sticky => 'Sticky',
        };
    }
}
