<?php

namespace Tests\Unit;

use App\Support\ScheduleDateDistributor;
use Carbon\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

class ScheduleDateDistributorTest extends TestCase
{
    public function test_exact_divide_fills_equal_days(): void
    {
        $rows = ScheduleDateDistributor::perDayRows('2026-09-21', 500, 20);

        $this->assertCount(25, $rows);
        $this->assertSame('2026-09-21', $rows[0]['date']);
        $this->assertSame('2026-10-15', $rows[24]['date']);
        $this->assertSame(500, array_sum(array_column($rows, 'quantity')));
        $this->assertTrue(collect($rows)->every(fn (array $row) => $row['quantity'] === 20));
    }

    public function test_remainder_uses_an_extra_last_day(): void
    {
        $rows = ScheduleDateDistributor::perDayRows('2026-09-21', 505, 20);

        $this->assertCount(26, $rows);
        $this->assertSame('2026-09-21', $rows[0]['date']);
        $this->assertSame('2026-10-16', $rows[25]['date']);
        $this->assertSame(20, $rows[0]['quantity']);
        $this->assertSame(5, $rows[25]['quantity']);
        $this->assertSame(505, array_sum(array_column($rows, 'quantity')));
    }

    public function test_offset_two_days_back_from_today(): void
    {
        $this->assertSame(
            '2026-09-19',
            ScheduleDateDistributor::startDateFromOffset(2, Carbon::parse('2026-09-21'))
        );
        $this->assertSame(
            '2026-09-21',
            ScheduleDateDistributor::startDateFromOffset(0, Carbon::parse('2026-09-21'))
        );
    }

    public function test_rejects_invalid_totals(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ScheduleDateDistributor::perDayRows('2026-09-21', 0, 20);
    }
}
