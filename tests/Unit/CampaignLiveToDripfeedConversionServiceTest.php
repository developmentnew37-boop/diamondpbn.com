<?php

namespace Tests\Unit;

use App\Services\CampaignConversionEligibilityService;
use App\Services\CampaignLiveToDripfeedConversionService;
use Carbon\Carbon;
use Tests\TestCase;

class CampaignLiveToDripfeedConversionServiceTest extends TestCase
{
    public function test_resolve_publish_date_uses_conversion_day_for_past_slots(): void
    {
        $service = app(CampaignLiveToDripfeedConversionService::class);
        $runDate = Carbon::parse('2026-07-27')->startOfDay();
        $slot = Carbon::parse('2026-07-24')->startOfDay();

        $publish = $service->resolvePublishDate($slot, $runDate);

        $this->assertSame('2026-07-27', $publish->toDateString());
    }

    public function test_resolve_publish_date_uses_slot_for_future(): void
    {
        $service = app(CampaignLiveToDripfeedConversionService::class);
        $runDate = Carbon::parse('2026-07-27')->startOfDay();
        $slot = Carbon::parse('2026-07-28')->startOfDay();

        $publish = $service->resolvePublishDate($slot, $runDate);

        $this->assertSame('2026-07-28', $publish->toDateString());
    }

    public function test_minimum_agent_constant(): void
    {
        $this->assertSame('8.3.0', CampaignConversionEligibilityService::MIN_AGENT_VERSION);
    }
}
