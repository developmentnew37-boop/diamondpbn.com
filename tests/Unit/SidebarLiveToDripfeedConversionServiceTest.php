<?php

namespace Tests\Unit;

use App\Services\SidebarConversionEligibilityService;
use App\Services\SidebarLiveToDripfeedConversionService;
use Carbon\Carbon;
use Tests\TestCase;

class SidebarLiveToDripfeedConversionServiceTest extends TestCase
{
    public function test_resolve_publish_date_uses_conversion_day_for_past_slots(): void
    {
        $service = app(SidebarLiveToDripfeedConversionService::class);
        $runDate = Carbon::parse('2026-07-27')->startOfDay();
        $slot = Carbon::parse('2026-07-24')->startOfDay();

        $publish = $service->resolvePublishDate($slot, $runDate);

        $this->assertSame('2026-07-27', $publish->toDateString());
    }

    public function test_resolve_publish_date_uses_slot_for_future(): void
    {
        $service = app(SidebarLiveToDripfeedConversionService::class);
        $runDate = Carbon::parse('2026-07-27')->startOfDay();
        $slot = Carbon::parse('2026-07-28')->startOfDay();

        $publish = $service->resolvePublishDate($slot, $runDate);

        $this->assertSame('2026-07-28', $publish->toDateString());
    }

    public function test_expand_slot_dates_honors_quantities_in_date_order(): void
    {
        $service = app(SidebarLiveToDripfeedConversionService::class);

        $normalized = $service->normalizeDateQuantities([
            ['date' => '2026-07-31', 'quantity' => 3],
            ['date' => '2026-07-29', 'quantity' => 4],
            ['date' => '2026-07-30', 'quantity' => 3],
        ]);

        $slots = $service->expandSlotDates($normalized);

        $this->assertSame([
            '2026-07-29',
            '2026-07-29',
            '2026-07-29',
            '2026-07-29',
            '2026-07-30',
            '2026-07-30',
            '2026-07-30',
            '2026-07-31',
            '2026-07-31',
            '2026-07-31',
        ], $slots);
    }

    public function test_slot_timestamp_uses_midnight_in_app_timezone(): void
    {
        $service = app(SidebarLiveToDripfeedConversionService::class);

        $this->assertSame(
            Carbon::parse('2026-07-29', config('app.timezone'))->startOfDay()->format('Y-m-d H:i:s'),
            $service->slotTimestamp('2026-07-29'),
        );
    }

    public function test_minimum_agent_constant(): void
    {
        $this->assertSame('8.3.0', SidebarConversionEligibilityService::MIN_AGENT_VERSION);
    }
}
