<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class CampaignTaskStatusFilter
{
    public static function apply(Builder $query, ?string $statusFilter): Builder
    {
        if ($statusFilter === 'success') {
            $query->where('status', 'success');
        } elseif ($statusFilter === 'failed') {
            $query->where('status', 'failed');
        } elseif ($statusFilter === 'queued') {
            $query->whereIn('status', ['queued', 'publishing']);
        }

        return $query;
    }

    public static function counts(Builder $query): object
    {
        return (clone $query)->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed")
            ->selectRaw("SUM(CASE WHEN status IN ('queued', 'publishing') THEN 1 ELSE 0 END) as queued")
            ->first();
    }

    /**
     * Status tabs for live→dripfeed campaigns (aligns counts with WordPress when local row is stale).
     */
    public static function countsForConvertedLivePosts(Builder $query): object
    {
        $rows = (clone $query)->get([
            'status',
            'is_converted_live',
            'remote_status',
            'conversion_phase',
            'schedule_at',
            'original_schedule_at',
        ]);

        $total = $rows->count();
        $success = 0;
        $failed = 0;
        $queued = 0;

        foreach ($rows as $post) {
            $bucket = self::convertedLivePostFilterBucket($post);
            match ($bucket) {
                'success' => $success++,
                'failed' => $failed++,
                default => $queued++,
            };
        }

        return (object) [
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
            'queued' => $queued,
        ];
    }

    /**
     * @return 'success'|'failed'|'queued'
     */
    public static function convertedLivePostFilterBucket(object $post): string
    {
        if (($post->status ?? '') === 'failed' || ($post->conversion_phase ?? '') === 'failed') {
            return 'failed';
        }

        $scheduleAt = isset($post->original_schedule_at) && $post->original_schedule_at
            ? Carbon::parse($post->original_schedule_at)
            : (isset($post->schedule_at) ? Carbon::parse($post->schedule_at) : null);

        if (! ConvertedLivePostSlot::isDue($scheduleAt)) {
            return 'queued';
        }

        $remote = strtolower(trim((string) ($post->remote_status ?? '')));

        if ($remote === 'publish') {
            return 'success';
        }

        if (($post->status ?? '') === 'success' && $remote === 'future') {
            return 'success';
        }

        if (($post->status ?? '') === 'success' && $remote === '') {
            return 'success';
        }

        return 'queued';
    }

    public static function applyForConvertedLivePost(Builder $query, ?string $statusFilter): Builder
    {
        if ($statusFilter === '' || $statusFilter === null) {
            return $query;
        }

        $ids = (clone $query)->pluck('id');
        if ($ids->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        $rows = (clone $query)->get([
            'id',
            'status',
            'is_converted_live',
            'remote_status',
            'conversion_phase',
            'schedule_at',
            'original_schedule_at',
        ]);

        $matchingIds = $rows->filter(function ($post) use ($statusFilter) {
            return self::convertedLivePostFilterBucket($post) === $statusFilter;
        })->pluck('id');

        return $query->whereIn('id', $matchingIds);
    }
}
