<?php

namespace App\Support;

use Carbon\Carbon;
use InvalidArgumentException;

class ScheduleDateDistributor
{
    /**
     * @return list<array{date: string, quantity: int}>
     */
    public static function perDayRows(string $fromDate, int $total, int $perDay): array
    {
        if ($total < 1 || $perDay < 1) {
            throw new InvalidArgumentException('Total and per day must be at least 1.');
        }

        $remaining = $total;
        $cursor = Carbon::parse($fromDate)->startOfDay();
        $rows = [];

        while ($remaining > 0) {
            $qty = min($perDay, $remaining);
            $rows[] = [
                'date' => $cursor->toDateString(),
                'quantity' => $qty,
            ];
            $remaining -= $qty;
            $cursor->addDay();
        }

        return $rows;
    }

    public static function startDateFromOffset(int $offset, ?Carbon $today = null): string
    {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();

        return $today->subDays(max(0, $offset))->toDateString();
    }
}
