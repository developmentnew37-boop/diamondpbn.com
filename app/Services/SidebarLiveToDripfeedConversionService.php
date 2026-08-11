<?php

namespace App\Services;

use App\Jobs\DraftConvertedLiveSidebarTasksJob;
use App\Models\Admin\ScheduleSidebarCampaign;
use App\Models\Admin\ScheduleSidebarCampaignDate;
use App\Models\Admin\ScheduleSidebarCampaignDomain;
use App\Models\Admin\ScheduleSidebarCampaignLink;
use App\Models\Admin\ScheduleSidebarCampaignTask;
use App\Models\Admin\SidebarCampaign;
use App\Models\Admin\SidebarCampaignDomain;
use App\Models\Admin\SidebarCampaignLink;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SidebarLiveToDripfeedConversionService
{
    public function __construct(

        private readonly SidebarConversionEligibilityService $eligibility,

    ) {}

    /**
     * @param  array<int, array{date: string, quantity: int}>  $dateQuantities
     */
    public function convert(

        SidebarCampaign $source,

        int $adminId,

        string $campaignNoInput,

        array $dateQuantities,

        string $conversionMode,

        bool $acknowledgeRisk = false,

    ): ScheduleSidebarCampaign {

        if ($source->converted_to_schedule_sidebar_campaign_id) {

            throw ValidationException::withMessages([
                'campaign_id' => 'This sidebar campaign was already converted to a scheduled sidebar campaign.',

            ]);

        }

        $summary = $this->eligibility->summarize($source);

        if ($summary['in_progress'] > 0) {

            throw ValidationException::withMessages([
                'campaign_id' => 'Wait until all tasks finish publishing before converting.',

            ]);

        }

        $convertibleTasks = $this->eligibility->convertibleTasks($source);

        $convertibleCount = $convertibleTasks->count();

        if ($convertibleCount < 1) {

            throw ValidationException::withMessages([
                'campaign_id' => 'No live blogroll tasks with remote IDs are available to convert.',

            ]);

        }

        $normalizedDates = $this->normalizeDateQuantities($dateQuantities);

        $totalQty = array_sum(array_column($normalizedDates, 'quantity'));

        if ($totalQty !== $convertibleCount) {

            throw ValidationException::withMessages([
                'date_quantities' => "Date quantities must total {$convertibleCount} tasks (currently {$totalQty}).",

            ]);

        }

        $runDate = now()->startOfDay();

        $campaignNo = $this->generateUniqueScheduleCampaignNo($campaignNoInput);

        $scheduleCampaign = DB::transaction(function () use (

            $source,

            $adminId,

            $campaignNo,

            $normalizedDates,

            $conversionMode,

            $convertibleTasks,

            $runDate,

        ) {

            $schedule = ScheduleSidebarCampaign::create([

                'campaign_no' => $campaignNo,

                'domain_category_id' => $source->domain_category_id,

                'admin_id' => $adminId,

                'schedule_from_date' => $this->minDate($normalizedDates),

                'schedule_to_date' => $this->maxDate($normalizedDates),

                'status' => 'queued',

                'total_targets' => $convertibleTasks->count(),

                'completed_targets' => 0,

                'failed_targets' => 0,

                'converted_from_sidebar_campaign_id' => $source->id,

                'conversion_run_date' => $runDate,

                'conversion_mode' => $conversionMode,

                'conversion_pipeline_status' => 'pending',

            ]);

            $domainMap = $this->copyDomains($source, $schedule);

            $linkMap = $this->copyLinks($source, $schedule);

            $dateIdByString = [];

            foreach ($normalizedDates as $row) {

                $dateRow = ScheduleSidebarCampaignDate::create([

                    'schedule_sidebar_campaign_id' => $schedule->id,

                    'schedule_date' => $row['date'],

                    'quantity' => $row['quantity'],

                ]);

                $dateIdByString[$row['date']] = (int) $dateRow->id;

            }

            $slotDates = $this->expandSlotDates($normalizedDates);

            $taskRows = [];

            $now = now();

            foreach ($convertibleTasks->values() as $index => $liveTask) {

                $slotAt = $this->slotTimestamp($slotDates[$index]);

                $slotDate = Carbon::parse($slotAt)->startOfDay();

                $publishDate = $this->resolvePublishDate($slotDate, $runDate);

                $dateStr = $slotDate->toDateString();

                $scheduleDomainId = $domainMap[(int) $liveTask->sidebar_campaign_domain_id] ?? null;

                $scheduleLinkId = $linkMap[(int) $liveTask->sidebar_campaign_link_id] ?? null;

                $dateId = $dateIdByString[$dateStr] ?? null;

                if (! $scheduleDomainId || ! $scheduleLinkId || ! $dateId) {

                    throw ValidationException::withMessages([
                        'campaign_id' => 'Could not map domain/link/date for task #'.$liveTask->id,

                    ]);

                }

                $taskRows[] = [

                    'schedule_sidebar_campaign_id' => $schedule->id,

                    'source_sidebar_campaign_task_id' => $liveTask->id,

                    'is_converted_live' => true,

                    'conversion_phase' => 'pending_draft',

                    'conversion_publish_date' => $publishDate->toDateString(),

                    'schedule_sidebar_campaign_domain_id' => $scheduleDomainId,

                    'schedule_sidebar_campaign_link_id' => $scheduleLinkId,

                    'schedule_sidebar_campaign_date_id' => $dateId,

                    'schedule_at' => $slotAt,

                    'original_schedule_at' => $slotAt,

                    'remote_id' => $liveTask->remote_id,

                    'status' => 'queued',

                    'attempt_count' => 0,

                    'created_at' => $now,

                    'updated_at' => $now,

                ];

            }

            foreach (array_chunk($taskRows, 200) as $chunk) {

                ScheduleSidebarCampaignTask::insert($chunk);

            }

            $source->update([

                'converted_to_schedule_sidebar_campaign_id' => $schedule->id,

                'conversion_locked_at' => now(),

            ]);

            return $schedule;

        });

        $this->dispatchDraftPhase($scheduleCampaign);

        return $scheduleCampaign->fresh();

    }

    /**
     * Re-assign schedule slots from stored date-quantity rows (fixes campaigns converted before date-order fix).
     */
    public function realignConvertedTaskSlots(ScheduleSidebarCampaign $campaign): int
    {

        if (! filled($campaign->converted_from_sidebar_campaign_id)) {

            return 0;

        }

        $dateRows = ScheduleSidebarCampaignDate::query()

            ->where('schedule_sidebar_campaign_id', $campaign->id)

            ->orderBy('schedule_date')

            ->get(['id', 'schedule_date', 'quantity']);

        if ($dateRows->isEmpty()) {

            return 0;

        }

        $normalized = $this->normalizeDateQuantities($dateRows->map(fn ($row) => [

            'date' => $row->schedule_date->toDateString(),

            'quantity' => (int) $row->quantity,

        ])->all());

        $slotDates = $this->expandSlotDates($normalized);

        $dateIdByString = [];

        foreach ($dateRows as $row) {

            $dateIdByString[$row->schedule_date->toDateString()] = (int) $row->id;

        }

        $runDate = $campaign->conversion_run_date

            ? Carbon::parse($campaign->conversion_run_date)->startOfDay()

            : now()->startOfDay();

        $tasks = ScheduleSidebarCampaignTask::query()

            ->where('schedule_sidebar_campaign_id', $campaign->id)

            ->where('is_converted_live', true)

            ->orderBy('source_sidebar_campaign_task_id')

            ->orderBy('id')

            ->get();

        if ($tasks->count() !== count($slotDates)) {

            return 0;

        }

        $updated = 0;

        foreach ($tasks->values() as $index => $task) {

            $slotAt = $this->slotTimestamp($slotDates[$index]);

            $slotDate = Carbon::parse($slotAt)->startOfDay();

            $dateStr = $slotDate->toDateString();

            $publishDate = $this->resolvePublishDate($slotDate, $runDate);

            $dateId = $dateIdByString[$dateStr] ?? null;

            if (! $dateId) {

                continue;

            }

            $changes = [];

            if ($task->schedule_at?->format('Y-m-d H:i:s') !== $slotAt) {

                $changes['schedule_at'] = $slotAt;

            }

            if ($task->original_schedule_at?->format('Y-m-d H:i:s') !== $slotAt) {

                $changes['original_schedule_at'] = $slotAt;

            }

            if ((int) $task->schedule_sidebar_campaign_date_id !== $dateId) {

                $changes['schedule_sidebar_campaign_date_id'] = $dateId;

            }

            if ($task->conversion_publish_date?->toDateString() !== $publishDate->toDateString()) {

                $changes['conversion_publish_date'] = $publishDate->toDateString();

            }

            if ($changes !== []) {

                $task->update($changes);

                $updated++;

            }

        }

        return $updated;

    }

    public function dispatchDraftPhase(ScheduleSidebarCampaign $scheduleCampaign): void
    {

        $ids = ScheduleSidebarCampaignTask::query()

            ->where('schedule_sidebar_campaign_id', $scheduleCampaign->id)

            ->where('is_converted_live', true)

            ->where('conversion_phase', 'pending_draft')

            ->orderBy('schedule_at')

            ->orderBy('id')

            ->pluck('id')

            ->all();

        foreach (array_chunk($ids, 40) as $chunk) {

            DraftConvertedLiveSidebarTasksJob::dispatch($chunk, (int) $scheduleCampaign->id)

                ->onQueue(DraftConvertedLiveSidebarTasksJob::QUEUE);

        }

        $scheduleCampaign->update(['conversion_pipeline_status' => 'drafting']);

    }

    public function resolvePublishDate(Carbon $slotDate, Carbon $runDate): Carbon
    {

        if ($slotDate->lte($runDate)) {

            return $runDate->copy();

        }

        return $slotDate->copy();

    }

    /**
     * Sort dates ascending and coerce Y-m-d strings (fixes grid order / timezone drift).

     *

     * @param  array<int, array{date: string, quantity: int}>  $dateQuantities
     * @return array<int, array{date: string, quantity: int}>
     */
    public function normalizeDateQuantities(array $dateQuantities): array
    {

        return collect($dateQuantities)

            ->map(function (array $row) {

                if (! isset($row['date'], $row['quantity'])) {

                    return null;

                }

                $qty = (int) $row['quantity'];

                if ($qty < 1) {

                    return null;

                }

                return [

                    'date' => Carbon::parse($row['date'])->toDateString(),

                    'quantity' => $qty,

                ];

            })

            ->filter()

            ->sortBy('date')

            ->values()

            ->all();

    }

    /**
     * @param  array<int, array{date: string, quantity: int}>  $dateQuantities  Must already be normalized + sorted.

     * @return string[]
     */
    public function expandSlotDates(array $dateQuantities): array
    {

        $dates = [];

        foreach ($dateQuantities as $row) {

            for ($i = 0; $i < (int) $row['quantity']; $i++) {

                $dates[] = $row['date'];

            }

        }

        return $dates;

    }

    public function slotTimestamp(string $dateYmd): string
    {

        return Carbon::parse($dateYmd, config('app.timezone'))

            ->startOfDay()

            ->format('Y-m-d H:i:s');

    }

    /**
     * @param  array<int, array{date: string, quantity: int}>  $dateQuantities
     */
    private function minDate(array $dateQuantities): ?string
    {

        if ($dateQuantities === []) {

            return null;

        }

        return collect($dateQuantities)->min('date');

    }

    /**
     * @param  array<int, array{date: string, quantity: int}>  $dateQuantities
     */
    private function maxDate(array $dateQuantities): ?string
    {

        if ($dateQuantities === []) {

            return null;

        }

        return collect($dateQuantities)->max('date');

    }

    /**
     * @return array<int, int> sidebar_campaign_domain_id => schedule_sidebar_campaign_domain_id
     */
    private function copyDomains(SidebarCampaign $source, ScheduleSidebarCampaign $schedule): array
    {

        $map = [];

        SidebarCampaignDomain::query()

            ->where('sidebar_campaign_id', $source->id)

            ->orderBy('id')

            ->each(function (SidebarCampaignDomain $cd) use ($schedule, &$map) {

                $row = ScheduleSidebarCampaignDomain::create([

                    'schedule_sidebar_campaign_id' => $schedule->id,

                    'domain_id' => $cd->domain_id,

                ]);

                $map[(int) $cd->id] = (int) $row->id;

            });

        return $map;

    }

    /**
     * @return array<int, int> sidebar_campaign_link_id => schedule_sidebar_campaign_link_id
     */
    private function copyLinks(SidebarCampaign $source, ScheduleSidebarCampaign $schedule): array
    {

        $map = [];

        SidebarCampaignLink::query()

            ->where('sidebar_campaign_id', $source->id)

            ->orderBy('id')

            ->each(function (SidebarCampaignLink $link) use ($schedule, &$map) {

                $row = ScheduleSidebarCampaignLink::create([

                    'schedule_sidebar_campaign_id' => $schedule->id,

                    'target_url' => $link->target_url,

                    'anchor_keyword' => $link->anchor_keyword,

                    'target_url_type' => $link->target_url_type,

                    'anchor_keyword_type' => $link->anchor_keyword_type,

                    'nofollow' => $link->nofollow,

                    'sponsored' => $link->sponsored,

                    'ugc' => $link->ugc,

                    'noopener' => $link->noopener,

                    'noreferrer' => $link->noreferrer,

                    'raw_rel_attr' => $link->raw_rel_attr,

                    'sort_order' => $link->sort_order,

                ]);

                $map[(int) $link->id] = (int) $row->id;

            });

        return $map;

    }

    private function generateUniqueScheduleCampaignNo(string $input): string
    {

        $base = Str::slug($input);

        if ($base === '') {

            $base = 'converted-sidebar-'.now()->timestamp;

        }

        $slug = $base;

        $counter = 1;

        while (ScheduleSidebarCampaign::where('campaign_no', $slug)->exists()) {

            $slug = "{$base}-{$counter}";

            $counter++;

        }

        return $slug;

    }
}
