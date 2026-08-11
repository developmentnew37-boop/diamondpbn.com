<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LiveToDripfeedConversionMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('failed_targets')->default(0);
        });
        Schema::create('schedule_campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('failed_targets')->default(0);
        });
        Schema::create('campaign_posts', function (Blueprint $table) {
            $table->id();
        });
        Schema::create('schedule_campaigns_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schedule_campaign_id');
            $table->timestamp('schedule_at')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('schedule_campaigns_posts');
        Schema::dropIfExists('campaign_posts');
        Schema::dropIfExists('schedule_campaigns');
        Schema::dropIfExists('campaigns');

        parent::tearDown();
    }

    public function test_conversion_schema_columns_exist_after_migration(): void
    {
        $migration = require database_path('migrations/2026_07_28_120000_add_live_to_dripfeed_conversion_schema.php');

        $migration->up();

        $this->assertTrue(Schema::hasColumn('campaigns', 'converted_to_schedule_campaign_id'));
        $this->assertTrue(Schema::hasColumn('schedule_campaigns', 'converted_from_campaign_id'));
        $this->assertTrue(Schema::hasColumn('schedule_campaigns_posts', 'is_converted_live'));
        $this->assertTrue(Schema::hasColumn('schedule_campaigns_posts', 'conversion_phase'));

        $migration->up();

        $this->assertTrue(Schema::hasColumn('campaigns', 'converted_to_schedule_campaign_id'));

        $migration->down();

        $this->assertFalse(Schema::hasColumn('campaigns', 'converted_to_schedule_campaign_id'));
    }
}
