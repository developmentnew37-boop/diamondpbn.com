<?php

namespace App\Jobs;

use App\Models\Admin\Domain;
use App\Models\Admin\ScheduleCampaignDomainReplacement;
use App\Models\Admin\ScheduleSidebarCampaignDomainReplacement;
use App\Services\BlogrollApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class CleanupReplacedDomainRemoteContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'deletions';

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [60, 120, 240, 300];

    public function __construct(
        public string $profileLabel,
        public int $replacementId,
    ) {
        $this->onQueue(self::QUEUE);
    }

    public function handle(): void
    {
        $replacement = $this->findReplacement();

        if (! $replacement) {
            return;
        }

        if ((string) ($replacement->old_remote_cleanup_status ?? '') !== 'pending') {
            return;
        }

        $remoteId = trim((string) ($replacement->previous_remote_id ?? ''));
        if ($remoteId === '') {
            $replacement->forceFill([
                'old_remote_cleanup_status' => 'skipped',
                'old_remote_cleaned_at' => now(),
                'old_remote_cleanup_error' => null,
            ])->save();

            return;
        }

        $oldDomain = Domain::query()->find($replacement->old_domain_id);
        if (! $oldDomain || trim((string) $oldDomain->api_key) === '') {
            $this->markFailed($replacement, 'Old domain or API key is missing for remote cleanup.');

            return;
        }

        try {
            $ok = $this->isSidebarProfile()
                ? $this->deleteSidebarRemote($oldDomain, $remoteId)
                : $this->deletePostRemote($oldDomain, $remoteId);

            if (! $ok) {
                throw new \RuntimeException('Remote delete did not succeed.');
            }

            $replacement->forceFill([
                'old_remote_cleanup_status' => 'cleaned',
                'old_remote_cleaned_at' => now(),
                'old_remote_cleanup_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            Log::warning('CleanupReplacedDomainRemoteContentJob failed attempt', [
                'profile' => $this->profileLabel,
                'replacement_id' => $this->replacementId,
                'remote_id' => $remoteId,
                'attempt' => $this->attempts(),
                'error' => $exception->getMessage(),
            ]);

            if ($this->attempts() >= $this->tries) {
                $this->markFailed($replacement, $exception->getMessage());

                return;
            }

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $replacement = $this->findReplacement();
        if (! $replacement || (string) ($replacement->old_remote_cleanup_status ?? '') !== 'pending') {
            return;
        }

        $this->markFailed($replacement, $exception?->getMessage() ?? 'Old remote cleanup failed.');
    }

    private function findReplacement(): ?Model
    {
        return $this->isSidebarProfile()
            ? ScheduleSidebarCampaignDomainReplacement::query()->find($this->replacementId)
            : ScheduleCampaignDomainReplacement::query()->find($this->replacementId);
    }

    private function isSidebarProfile(): bool
    {
        return str_contains($this->profileLabel, 'sidebar');
    }

    private function deletePostRemote(Domain $domain, string $remoteId): bool
    {
        $domainName = trim((string) $domain->name);
        if (! preg_match('~^https?://~i', $domainName)) {
            $domainName = 'https://'.$domainName;
        }

        $url = rtrim($domainName, '/').'/wp-json/external/v1/posts/delete/'.$remoteId
            .'?api_key='.urlencode((string) $domain->api_key);

        $response = Http::withoutVerifying()->timeout(60)->asJson()->delete($url);

        return $response->successful();
    }

    private function deleteSidebarRemote(Domain $domain, string $remoteId): bool
    {
        $response = BlogrollApiService::deleteEntryByRemoteId(
            (string) $domain->name,
            (string) $domain->api_key,
            $remoteId
        );

        return $response->successful();
    }

    private function markFailed(Model $replacement, string $message): void
    {
        $replacement->forceFill([
            'old_remote_cleanup_status' => 'failed',
            'old_remote_cleanup_error' => mb_substr($message, 0, 1000),
            'old_remote_cleaned_at' => null,
        ])->save();
    }
}
