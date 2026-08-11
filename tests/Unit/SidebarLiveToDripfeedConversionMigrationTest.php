<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SidebarLiveToDripfeedConversionMigrationTest extends TestCase
{
    public function test_sidebar_conversion_columns_exist(): void
    {
        $this->assertTrue(Schema::hasColumn('sidebar_campaigns', 'converted_to_schedule_sidebar_campaign_id'));
        $this->assertTrue(Schema::hasColumn('sidebar_campaigns', 'conversion_locked_at'));
        $this->assertTrue(Schema::hasColumn('schedule_sidebar_campaigns', 'converted_from_sidebar_campaign_id'));
        $this->assertTrue(Schema::hasColumn('schedule_sidebar_campaigns', 'conversion_pipeline_status'));
        $this->assertTrue(Schema::hasColumn('schedule_sidebar_campaign_tasks', 'source_sidebar_campaign_task_id'));
        $this->assertTrue(Schema::hasColumn('schedule_sidebar_campaign_tasks', 'is_converted_live'));
        $this->assertTrue(Schema::hasColumn('schedule_sidebar_campaign_tasks', 'conversion_phase'));
        $this->assertTrue(Schema::hasColumn('schedule_sidebar_campaign_tasks', 'remote_status'));
    }
}
