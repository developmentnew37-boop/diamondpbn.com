<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;

final class ConvertedLivePostSlot
{
    /**
     * Dripfeed slot day is today or in the past (post may be published on the site).
     */
    public static function isDue(?DateTimeInterface $scheduleAt): bool
    {
        if ($scheduleAt === null) {
            return true;
        }

        return Carbon::parse($scheduleAt)->startOfDay()->lte(now()->startOfDay());
    }

    /**
     * Public on the WordPress front end: slot is due and remote status is publish.
     */
    public static function isPubliclyLive(?DateTimeInterface $scheduleAt, ?string $remoteStatus): bool
    {
        if (! self::isDue($scheduleAt)) {
            return false;
        }

        return strtolower(trim((string) $remoteStatus)) === 'publish';
    }
}
