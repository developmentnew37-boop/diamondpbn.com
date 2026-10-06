<?php

namespace App\Services;

use App\Jobs\ProcessPluginInventoryReportChunkJob;
use App\Models\Admin\Domain;
use App\Models\Admin\PluginInventoryReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\SimpleExcel\SimpleExcelWriter;

class PluginInventoryReportService
{
    public function __construct(
        private readonly PluginDeploymentService $deploymentService,
        private readonly RemotePluginManagerService $remotePluginManager,
    ) {}

    /**
     * @param  array<int, string>  $domainNames
     */
    public function createReport(int $adminId, array $domainNames, string $source, ?int $categoryId): PluginInventoryReport
    {
        $this->purgeExpired();

        $domainNames = array_values($domainNames);

        return DB::transaction(function () use ($adminId, $domainNames, $source, $categoryId) {
            $report = PluginInventoryReport::create([
                'admin_id' => $adminId,
                'source' => $source,
                'domain_category_id' => $categoryId,
                'status' => 'queued',
                'total_count' => count($domainNames),
                'status_message' => 'Queued — waiting for queue worker (plugin_deployments)',
            ]);

            Storage::disk('local')->put(
                $this->domainsFile($report),
                json_encode($domainNames, JSON_THROW_ON_ERROR)
            );

            ProcessPluginInventoryReportChunkJob::dispatch($report->id)
                ->afterCommit()
                ->onQueue('plugin_deployments');

            return $report;
        });
    }

    public function processNextChunk(PluginInventoryReport $report): void
    {
        $report->refresh();

        if ($report->isFinished()) {
            return;
        }

        $report->update([
            'status' => 'running',
            'started_at' => $report->started_at ?? now(),
            'status_message' => 'Reading installed plugins from remote sites...',
        ]);

        $domains = $this->domainNames($report);
        $start = (int) $report->processed_count;
        $slice = array_slice($domains, $start, $this->chunkSize());

        if ($slice === [] && $start < (int) $report->total_count) {
            $report->update([
                'status' => 'failed',
                'completed_at' => now(),
                'status_message' => 'Report stopped: the domain list is missing or shorter than expected.',
            ]);

            return;
        }

        foreach ($slice as $offset => $domainName) {
            $report->refresh();
            if ($report->isCancelled()) {
                return;
            }

            try {
                $rows = $this->rowsForDomain($domainName);
            } catch (\Throwable $e) {
                report($e);
                $rows = [$this->errorRow($domainName, 'Could not read installed plugins')];
            }

            $index = $start + $offset;
            Storage::disk('local')->put(
                $this->siteFile($report, $index),
                json_encode($rows, JSON_THROW_ON_ERROR)
            );

            $failed = (bool) ($rows[0]['failed'] ?? false);
            $report->increment('processed_count');
            $report->increment($failed ? 'failed_count' : 'success_count');
        }

        $report->refresh();

        if ($report->isCancelled()) {
            return;
        }

        if ((int) $report->processed_count >= (int) $report->total_count) {
            $this->writeWorkbook($report);
            $report->update([
                'status' => 'completed',
                'file_path' => $this->workbookFile($report),
                'completed_at' => now(),
                'status_message' => 'Report ready to download.',
            ]);
            $this->deleteWorkingFiles($report);

            return;
        }

        $report->update([
            'status_message' => 'Reading installed plugins from remote sites...',
        ]);
    }

    public function cancel(PluginInventoryReport $report): void
    {
        if ($report->isFinished()) {
            return;
        }

        $report->update([
            'status' => 'cancelled',
            'completed_at' => now(),
            'status_message' => 'Cancelled.',
        ]);
    }

    public function deleteReport(PluginInventoryReport $report): void
    {
        Storage::disk('local')->deleteDirectory($this->directory($report));
        $report->delete();
    }

    public function purgeExpired(): void
    {
        $hours = max(1, (int) config('plugin_manager.report_retention_hours', 24));
        $cutoff = now()->subHours($hours);

        PluginInventoryReport::query()
            ->where('updated_at', '<', $cutoff)
            ->whereNotIn('status', ['queued', 'running'])
            ->orderBy('id')
            ->each(function (PluginInventoryReport $report) {
                $this->deleteReport($report);
            });
    }

    public function chunkSize(): int
    {
        return max(1, (int) config('plugin_manager.report_chunk_size', 5));
    }

    public function jobTimeoutSeconds(): int
    {
        return max(60, (int) config('plugin_manager.job_timeout', 400));
    }

    public function lockSeconds(): int
    {
        return $this->jobTimeoutSeconds() + 30;
    }

    public function workbookAbsolutePath(PluginInventoryReport $report): ?string
    {
        if ($report->file_path === null || $report->file_path === '') {
            return null;
        }

        if (! Storage::disk('local')->exists($report->file_path)) {
            return null;
        }

        return Storage::disk('local')->path($report->file_path);
    }

    /**
     * @return list<array{domain: string, plugin_count: int, plugin_name: string, version: string, folder: string, active: string, message: string}>
     */
    public function rowsForDomain(string $domainName): array
    {
        $domain = Domain::findByNormalizedName($domainName);

        if (! $domain || $domain->api_key === null || $domain->api_key === '') {
            return [$this->errorRow($domainName, 'Domain not in inventory or missing API key')];
        }

        $agentStatus = $this->remotePluginManager->fetchAgentStatus($domain);

        if (! ($agentStatus['ok'] ?? false)) {
            return [$this->errorRow($domainName, (string) ($agentStatus['message'] ?? 'Remote agent status check failed'))];
        }

        if (! ($agentStatus['plugin_manager_supported'] ?? false)) {
            $version = (string) ($agentStatus['plugin_version'] ?? 'unknown');

            return [$this->errorRow(
                $domainName,
                "Remote agent does not support plugin listing ({$version}). Update External API Manager to "
                .config('plugin_manager.min_agent_version', '8.1.5')
                .' or newer.'
            )];
        }

        $inventory = $this->remotePluginManager->fetchPluginsInventory($domain);

        if (! ($inventory['ok'] ?? false)) {
            return [$this->errorRow($domainName, (string) ($inventory['message'] ?? 'Could not read installed plugins'))];
        }

        $plugins = is_array($inventory['plugins'] ?? null) ? $inventory['plugins'] : [];

        if ($plugins === []) {
            return [[
                'domain' => $domainName,
                'plugin_count' => 0,
                'plugin_name' => '',
                'version' => '',
                'folder' => '',
                'active' => '',
                'message' => 'No plugins installed',
            ]];
        }

        $count = count($plugins);
        $rows = [];

        foreach ($plugins as $plugin) {
            $active = $plugin['active'] ?? null;
            $rows[] = [
                'domain' => $domainName,
                'plugin_count' => $count,
                'plugin_name' => (string) ($plugin['name'] ?? ''),
                'version' => (string) ($plugin['version'] ?? ''),
                'folder' => (string) ($plugin['slug'] ?? ''),
                'active' => $active === true ? 'active' : ($active === false ? 'inactive' : ''),
                'message' => '',
            ];
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function domainNames(PluginInventoryReport $report): array
    {
        $json = Storage::disk('local')->get($this->domainsFile($report));
        $decoded = json_decode((string) $json, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }

    private function writeWorkbook(PluginInventoryReport $report): void
    {
        $path = Storage::disk('local')->path($this->workbookFile($report));
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $sites = [];
        $maxPlugins = 0;

        for ($index = 0; $index < (int) $report->total_count; $index++) {
            $json = Storage::disk('local')->get($this->siteFile($report, $index));
            $rows = json_decode((string) $json, true);

            if (! is_array($rows) || $rows === []) {
                continue;
            }

            $plugins = [];
            $first = null;

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $first ??= $row;

                if ($this->isPluginRow($row)) {
                    $plugins[] = $row;
                }
            }

            if ($first === null) {
                continue;
            }

            $maxPlugins = max($maxPlugins, count($plugins));
            $sites[] = [
                'domain' => (string) ($first['domain'] ?? ''),
                'plugin_count' => (int) ($first['plugin_count'] ?? count($plugins)),
                'message' => $plugins === [] ? (string) ($first['message'] ?? '') : '',
                'plugins' => $plugins,
            ];
        }

        $header = ['Domain', 'Plugin count'];

        for ($number = 1; $number <= $maxPlugins; $number++) {
            $header[] = 'Plugin name - ('.$number.')';
        }

        $header[] = 'Message';

        $writer = SimpleExcelWriter::create($path);
        $writer->addHeader($header);

        foreach ($sites as $site) {
            $line = [
                $this->excelText($site['domain']),
                $site['plugin_count'],
            ];

            for ($number = 0; $number < $maxPlugins; $number++) {
                $line[] = isset($site['plugins'][$number])
                    ? $this->pluginCell($site['plugins'][$number])
                    : '';
            }

            $line[] = $this->excelText($site['message']);
            $writer->addRow($line);
        }

        $writer->close();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isPluginRow(array $row): bool
    {
        return ($row['plugin_name'] ?? '') !== ''
            || ($row['folder'] ?? '') !== ''
            || ($row['version'] ?? '') !== '';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function pluginCell(array $row): string
    {
        $label = (string) ($row['plugin_name'] ?? '');

        if ($label === '') {
            $label = (string) ($row['folder'] ?? '');
        }

        $parts = [];

        if ($label !== '') {
            $parts[] = $label;
        }

        $version = (string) ($row['version'] ?? '');
        $active = (string) ($row['active'] ?? '');

        if ($version !== '') {
            $parts[] = '('.$version.')';
        }

        if ($active !== '') {
            $parts[] = $active;
        }

        return $this->excelText(implode(' - ', $parts));
    }

    private function deleteWorkingFiles(PluginInventoryReport $report): void
    {
        Storage::disk('local')->delete($this->domainsFile($report));
        Storage::disk('local')->deleteDirectory($this->directory($report).'/sites');
    }

    /**
     * @return array{domain: string, plugin_count: int, plugin_name: string, version: string, folder: string, active: string, message: string}
     */
    private function errorRow(string $domainName, string $message): array
    {
        return [
            'domain' => $domainName,
            'plugin_count' => 0,
            'plugin_name' => '',
            'version' => '',
            'folder' => '',
            'active' => '',
            'message' => $message,
            'failed' => true,
        ];
    }

    private function excelText(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
            return "'".$value;
        }

        return $value;
    }

    private function directory(PluginInventoryReport $report): string
    {
        return 'plugin-reports/'.$report->uuid;
    }

    private function domainsFile(PluginInventoryReport $report): string
    {
        return $this->directory($report).'/domains.json';
    }

    private function siteFile(PluginInventoryReport $report, int $index): string
    {
        return $this->directory($report).'/sites/'.$index.'.json';
    }

    private function workbookFile(PluginInventoryReport $report): string
    {
        return $this->directory($report).'/plugins.xlsx';
    }
}
