<?php

namespace App\Support;

use App\Models\Admin\ScheduleCampaignPost;

final class SchedulePostReportStatus
{
    /**
     * @return array{label: string, class: string, is_publicly_live: bool}
     */
    public static function forReport(ScheduleCampaignPost $post): array
    {

        if ($post->is_converted_live) {

            return self::forConvertedPost($post);

        }

        $isLive = $post->status === 'success';

        return [

            'label' => $isLive ? 'Live' : 'Not Live',

            'class' => $isLive ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700',

            'is_publicly_live' => $isLive,

        ];

    }

    /**
     * @return array{label: string, class: string, is_publicly_live: bool}
     */
    private static function forConvertedPost(ScheduleCampaignPost $post): array
    {

        $remote = strtolower(trim((string) ($post->remote_status ?? '')));

        $slotDue = ConvertedLivePostSlot::isDue($post->schedule_at);

        if ($post->status === 'failed' || $post->conversion_phase === 'failed') {

            return [

                'label' => 'Failed',

                'class' => 'bg-red-100 text-red-700',

                'is_publicly_live' => false,

            ];

        }

        if (! $slotDue) {

            if (in_array($remote, ['draft'], true) || in_array($post->conversion_phase, ['drafted', 'pending_draft'], true)) {

                return [

                    'label' => 'Awaiting slot',

                    'class' => 'bg-slate-100 text-slate-700',

                    'is_publicly_live' => false,

                ];

            }

            if ($remote === 'future') {

                return [

                    'label' => 'Scheduled',

                    'class' => 'bg-blue-100 text-blue-700',

                    'is_publicly_live' => false,

                ];

            }

            return [

                'label' => 'Awaiting slot',

                'class' => 'bg-slate-100 text-slate-700',

                'is_publicly_live' => false,

            ];

        }

        if ($remote === 'publish') {

            return [

                'label' => 'Live',

                'class' => 'bg-green-100 text-green-700',

                'is_publicly_live' => true,

            ];

        }

        if ($remote === 'future') {

            return [

                'label' => 'Scheduled',

                'class' => 'bg-blue-100 text-blue-700',

                'is_publicly_live' => false,

            ];

        }

        if ($remote === 'draft' || in_array($post->conversion_phase, ['drafted', 'pending_draft'], true)) {

            return [

                'label' => 'Draft (hidden)',

                'class' => 'bg-amber-100 text-amber-800',

                'is_publicly_live' => false,

            ];

        }

        if ($post->status === 'success') {

            return [

                'label' => 'Syncing…',

                'class' => 'bg-orange-100 text-orange-800',

                'is_publicly_live' => false,

            ];

        }

        return [

            'label' => 'Pending',

            'class' => 'bg-gray-100 text-gray-700',

            'is_publicly_live' => false,

        ];

    }
}
