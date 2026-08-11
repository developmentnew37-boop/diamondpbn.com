<?php

namespace App\Support;

use App\Models\Admin\ScheduleSidebarCampaignTask;

final class ScheduleSidebarReportStatus
{
    /**
     * @return array{label: string, class: string, is_publicly_live: bool}
     */
    public static function forReport(ScheduleSidebarCampaignTask $task): array
    {

        if ($task->is_converted_live) {

            return self::forConvertedTask($task);

        }

        $isLive = $task->status === 'success';

        return [

            'label' => $isLive ? 'Live' : 'Not Live',

            'class' => $isLive ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700',

            'is_publicly_live' => $isLive,

        ];

    }

    /**
     * @return array{label: string, class: string, is_publicly_live: bool}
     */
    private static function forConvertedTask(ScheduleSidebarCampaignTask $task): array
    {

        $remote = strtolower(trim((string) ($task->remote_status ?? '')));

        $slotDue = ConvertedLivePostSlot::isDue(self::slotDate($task));

        if ($task->status === 'failed' || $task->conversion_phase === 'failed') {

            return [

                'label' => 'Failed',

                'class' => 'bg-red-100 text-red-700',

                'is_publicly_live' => false,

            ];

        }

        if (! $slotDue) {

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

        if ($remote === 'draft' || in_array($task->conversion_phase, ['drafted', 'pending_draft'], true)) {

            if ($slotDue && $task->conversion_phase === 'drafted') {

                return [

                    'label' => 'Publishing…',

                    'class' => 'bg-blue-100 text-blue-800',

                    'is_publicly_live' => false,

                ];

            }

            return [

                'label' => 'Draft (hidden)',

                'class' => 'bg-amber-100 text-amber-800',

                'is_publicly_live' => false,

            ];

        }

        if ($task->status === 'success') {

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

    public static function slotDate(ScheduleSidebarCampaignTask $task): mixed
    {

        return $task->scheduleDate?->schedule_date ?? $task->original_schedule_at ?? $task->schedule_at;

    }
}
