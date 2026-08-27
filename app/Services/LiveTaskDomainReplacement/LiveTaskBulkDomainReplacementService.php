<?php

namespace App\Services\LiveTaskDomainReplacement;

use App\Data\BulkReplaceResult;
use App\Models\Admin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class LiveTaskBulkDomainReplacementService
{
    public function __construct(
        private readonly LiveTaskDomainReplacementService $replacementService,
    ) {}

    /**
     * @return array<int, string>
     */
    public function parseLines(?string $text): array
    {
        $lines = preg_split('/\R/', (string) ($text ?? '')) ?: [];
        $parsed = [];

        foreach ($lines as $line) {
            $normalized = normalizeDomainName($line);

            if ($normalized !== '') {
                $parsed[] = $normalized;
            }
        }

        return $parsed;
    }

    /**
     * @return array<int, string>
     */
    public function failedDomainNamesForCampaign(LiveTaskReplacementProfile $profile, Model $campaign): array
    {
        $domainTable = $this->domainTable($profile);
        $taskTable = $this->taskTable($profile);

        $query = $profile->campaignDomainModel::query()
            ->where("{$domainTable}.{$profile->domainRowCampaignIdColumn}", $campaign->id)
            ->join('domains', 'domains.id', '=', "{$domainTable}.domain_id")
            ->join($taskTable, "{$taskTable}.{$profile->taskDomainRowIdColumn}", '=', "{$domainTable}.id")
            ->whereIn("{$taskTable}.status", ['queued', 'failed'])
            ->whereNull("{$taskTable}.remote_id")
            ->whereNull("{$taskTable}.remote_url")
            ->whereNull("{$taskTable}.published_at");

        $this->applyUnlockedTaskConstraints($query, $taskTable, $profile);

        return $query
            ->orderBy("{$domainTable}.id")
            ->orderBy('domains.name')
            ->select('domains.name')
            ->distinct()
            ->pluck('name')
            ->filter(fn ($name) => is_string($name) && $name !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $campaignIds
     * @return array<int, int>
     */
    public function replaceableCampaignIds(LiveTaskReplacementProfile $profile, array $campaignIds): array
    {
        if ($campaignIds === []) {
            return [];
        }

        $domainTable = $this->domainTable($profile);
        $taskTable = $this->taskTable($profile);

        $query = $profile->campaignDomainModel::query()
            ->whereIn("{$domainTable}.{$profile->domainRowCampaignIdColumn}", $campaignIds)
            ->join($taskTable, "{$taskTable}.{$profile->taskDomainRowIdColumn}", '=', "{$domainTable}.id")
            ->whereIn("{$taskTable}.status", ['queued', 'failed'])
            ->whereNull("{$taskTable}.remote_id")
            ->whereNull("{$taskTable}.remote_url")
            ->whereNull("{$taskTable}.published_at");

        $this->applyUnlockedTaskConstraints($query, $taskTable, $profile);

        return $query
            ->distinct()
            ->pluck("{$domainTable}.{$profile->domainRowCampaignIdColumn}")
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function campaignHasReplaceableTasks(LiveTaskReplacementProfile $profile, Model $campaign): bool
    {
        return $this->failedDomainNamesForCampaign($profile, $campaign) !== [];
    }

    /**
     * @param  array<int, string>  $failedLines
     * @param  array<int, string>  $replacementLines
     * @return array<int, array{line: int, failed: string, replacement: string, task_count: int}>
     */
    public function buildPreview(
        LiveTaskReplacementProfile $profile,
        Model $campaign,
        Admin $admin,
        array $failedLines,
        array $replacementLines,
    ): array {
        $this->validateMappings($profile, $campaign, $admin, $failedLines, $replacementLines);

        $preview = [];
        $pairCount = min(count($failedLines), count($replacementLines));

        for ($i = 0; $i < $pairCount; $i++) {
            $failedName = $failedLines[$i];
            $replacementName = $replacementLines[$i];
            $domainRow = $this->findDomainRowByName($profile, $campaign, $failedName);

            $preview[] = [
                'line' => $i + 1,
                'failed' => $failedName,
                'replacement' => $replacementName,
                'task_count' => $domainRow
                    ? $this->countEligibleTasksOnDomainRow($profile, (int) $domainRow->id)
                    : 0,
            ];
        }

        return $preview;
    }

    /**
     * @param  array<int, string>  $failedLines
     * @param  array<int, string>  $replacementLines
     */
    public function validateMappings(
        LiveTaskReplacementProfile $profile,
        Model $campaign,
        Admin $admin,
        array $failedLines,
        array $replacementLines,
    ): void {
        $errors = [];

        if ($failedLines === []) {
            $errors['failed_domains'][] = 'Enter at least one failed domain.';
        }

        if (count($replacementLines) > count($failedLines)) {
            $errors['replacement_domains'][] = 'Too many replacement domains ('.count($replacementLines).') for '.count($failedLines).' failed domain(s).';
        }

        if (in_array($campaign->status ?? '', ['paused', 'cancelled'], true)) {
            $errors['campaign'][] = 'Cannot replace domains: campaign is paused or cancelled.';
        }

        $seenFailed = [];
        foreach ($failedLines as $index => $failedName) {
            $line = $index + 1;

            if (isset($seenFailed[$failedName])) {
                $errors['failed_domains'][] = "Duplicate failed domain on line {$line}: {$failedName}";

                continue;
            }

            $seenFailed[$failedName] = true;

            $failedDomain = $this->replacementService->lookupDomain($failedName);

            if (! $failedDomain) {
                $errors['failed_domains'][] = "Line {$line}: domain not found in inventory ({$failedName}).";

                continue;
            }

            $domainRow = $this->findDomainRowByDomainId($profile, $campaign, (int) $failedDomain->id);

            if (! $domainRow) {
                $errors['failed_domains'][] = "Line {$line}: domain is not on this campaign ({$failedName}).";

                continue;
            }

            if ($this->countEligibleTasksOnDomainRow($profile, (int) $domainRow->id) < 1) {
                $errors['failed_domains'][] = "Line {$line}: no replaceable tasks on this domain ({$failedName}).";
            }
        }

        $seenReplacement = [];
        $attachedDomainIds = $profile->campaignDomainModel::query()
            ->where($profile->domainRowCampaignIdColumn, $campaign->id)
            ->pluck('domain_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $pairCount = min(count($failedLines), count($replacementLines));

        for ($i = 0; $i < $pairCount; $i++) {
            $line = $i + 1;
            $replacementName = $replacementLines[$i];
            $failedName = $failedLines[$i];

            if (isset($seenReplacement[$replacementName])) {
                $errors['replacement_domains'][] = "Duplicate replacement domain on line {$line}: {$replacementName}";

                continue;
            }

            $seenReplacement[$replacementName] = true;

            $replacementDomain = $this->replacementService->lookupDomain($replacementName);

            if (! $replacementDomain) {
                $errors['replacement_domains'][] = "Line {$line}: replacement not found in inventory ({$replacementName}).";

                continue;
            }

            if (in_array((int) $replacementDomain->id, $attachedDomainIds, true)) {
                $errors['replacement_domains'][] = "Line {$line}: domain already on campaign ({$replacementName}).";

                continue;
            }

            if ($replacementName === $failedName) {
                $errors['replacement_domains'][] = "Line {$line}: replacement must differ from failed domain ({$replacementName}).";

                continue;
            }

            $representativeTask = $this->findRepresentativeTask($profile, $campaign, $failedName);

            if (! $representativeTask) {
                continue;
            }

            if ($reason = $this->replacementService->ineligibilityReasonForDomain(
                $profile,
                $campaign,
                $representativeTask,
                $admin,
                $replacementDomain,
                manual: true,
            )) {
                $errors['replacement_domains'][] = "Line {$line}: {$reason}";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<int, string>  $failedLines
     * @param  array<int, string>  $replacementLines
     */
    public function execute(
        LiveTaskReplacementProfile $profile,
        Model $campaign,
        Admin $admin,
        array $failedLines,
        array $replacementLines,
        string $reason = '',
    ): BulkReplaceResult {
        $this->validateMappings($profile, $campaign, $admin, $failedLines, $replacementLines);

        $pairCount = min(count($failedLines), count($replacementLines));
        $result = new BulkReplaceResult(requeueUnitLabel: 'task(s)');
        $attachedDomainIds = $profile->campaignDomainModel::query()
            ->where($profile->domainRowCampaignIdColumn, $campaign->id)
            ->pluck('domain_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        for ($i = 0; $i < $pairCount; $i++) {
            $line = $i + 1;
            $failedName = $failedLines[$i];
            $replacementName = $replacementLines[$i];

            $failedDomain = $this->replacementService->lookupDomain($failedName);
            $replacementDomain = $this->replacementService->lookupDomain($replacementName);
            $representativeTask = $this->findRepresentativeTask($profile, $campaign, $failedName);

            if (! $failedDomain || ! $replacementDomain || ! $representativeTask) {
                $result->failures[] = [
                    'line' => $line,
                    'failed' => $failedName,
                    'replacement' => $replacementName,
                    'error' => 'Mapping could not be resolved.',
                ];

                continue;
            }

            if (in_array((int) $replacementDomain->id, $attachedDomainIds, true)) {
                $result->failures[] = [
                    'line' => $line,
                    'failed' => $failedName,
                    'replacement' => $replacementName,
                    'error' => 'Replacement domain is already on this campaign.',
                ];

                continue;
            }

            $domainRowId = (int) $representativeTask->{$profile->taskDomainRowIdColumn};

            try {
                $replacement = $this->replacementService->replace(
                    $profile,
                    $representativeTask,
                    $admin,
                    (int) $replacementDomain->id,
                    (int) $failedDomain->id,
                    (string) Str::uuid(),
                    trim($reason) !== '' ? trim($reason) : 'Bulk domain replacement',
                );

                if ($replacement->state === 'completed' || $replacement->state === 'dispatch_pending') {
                    $result->domainsReplaced++;
                    $attachedDomainIds[] = (int) $replacementDomain->id;

                    $siblingCount = $this->replacementService->requeueEligibleTasksOnDomainRow(
                        $profile,
                        $domainRowId,
                        (int) $representativeTask->id,
                    );

                    $result->postsRequeued += 1 + $siblingCount;
                } else {
                    $result->failures[] = [
                        'line' => $line,
                        'failed' => $failedName,
                        'replacement' => $replacementName,
                        'error' => $replacement->error ?? 'Replacement dispatch did not complete.',
                    ];
                }
            } catch (ValidationException $exception) {
                $message = collect($exception->errors())->flatten()->first() ?? 'Validation failed.';
                $result->failures[] = [
                    'line' => $line,
                    'failed' => $failedName,
                    'replacement' => $replacementName,
                    'error' => (string) $message,
                ];
            } catch (Throwable $exception) {
                report($exception);
                $result->failures[] = [
                    'line' => $line,
                    'failed' => $failedName,
                    'replacement' => $replacementName,
                    'error' => 'Replacement could not be completed.',
                ];
            }
        }

        return $result;
    }

    private function domainTable(LiveTaskReplacementProfile $profile): string
    {
        return (new $profile->campaignDomainModel)->getTable();
    }

    private function taskTable(LiveTaskReplacementProfile $profile): string
    {
        return (new $profile->taskModel)->getTable();
    }

    private function findDomainRowByName(LiveTaskReplacementProfile $profile, Model $campaign, string $domainName): ?Model
    {
        $domain = $this->replacementService->lookupDomain($domainName);

        if (! $domain) {
            return null;
        }

        return $this->findDomainRowByDomainId($profile, $campaign, (int) $domain->id);
    }

    private function findDomainRowByDomainId(LiveTaskReplacementProfile $profile, Model $campaign, int $domainId): ?Model
    {
        return $profile->campaignDomainModel::query()
            ->where($profile->domainRowCampaignIdColumn, $campaign->id)
            ->where('domain_id', $domainId)
            ->first();
    }

    private function findRepresentativeTask(LiveTaskReplacementProfile $profile, Model $campaign, string $failedDomainName): ?Model
    {
        $domainRow = $this->findDomainRowByName($profile, $campaign, $failedDomainName);

        if (! $domainRow) {
            return null;
        }

        $tasks = $profile->taskModel::query()
            ->where($profile->taskCampaignIdColumn, $campaign->id)
            ->where($profile->taskDomainRowIdColumn, $domainRow->id)
            ->whereIn('status', ['queued', 'failed'])
            ->whereNull('remote_id')
            ->whereNull('remote_url')
            ->whereNull('published_at');

        $this->applyUnlockedTaskConstraints($tasks, null, $profile);

        $tasks = $tasks
            ->orderByRaw("CASE WHEN status = 'failed' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->with(['campaign', $profile->taskDomainRelation.'.domain'])
            ->get();

        return $tasks->first(fn (Model $task) => $this->replacementService->ineligibleReason($profile, $task) === null);
    }

    private function countEligibleTasksOnDomainRow(LiveTaskReplacementProfile $profile, int $domainRowId): int
    {
        $query = $profile->taskModel::query()
            ->where($profile->taskDomainRowIdColumn, $domainRowId)
            ->whereIn('status', ['queued', 'failed'])
            ->whereNull('remote_id')
            ->whereNull('remote_url')
            ->whereNull('published_at');

        $this->applyUnlockedTaskConstraints($query, null, $profile);

        return $query
            ->get()
            ->filter(fn (Model $task) => $this->replacementService->ineligibleReason($profile, $task) === null)
            ->count();
    }

    private function applyUnlockedTaskConstraints(
        Builder $query,
        ?string $taskTable,
        LiveTaskReplacementProfile $profile,
    ): void {
        $table = $taskTable ?? $this->taskTable($profile);
        $prefix = $taskTable !== null ? "{$table}." : '';

        $query->whereNull("{$prefix}locked_at")
            ->whereNull("{$prefix}lock_token");

        if (Schema::hasColumn($table, 'locked_until')) {
            $query->whereNull("{$prefix}locked_until");
        }
    }
}
