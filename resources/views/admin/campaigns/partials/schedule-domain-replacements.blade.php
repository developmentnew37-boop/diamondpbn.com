@if (isset($recentReplacements) && $recentReplacements->isNotEmpty())
    <div class="w-full rounded border border-blue-100 bg-blue-50 !p-4 !mb-3 !mt-3">
        <h3 class="font-semibold text-blue-900 !mb-2">Recent domain replacements</h3>
        <div class="flex flex-col gap-1 text-sm text-blue-800">
            @foreach ($recentReplacements as $replacement)
                @php
                    $cleanup = (string) ($replacement->old_remote_cleanup_status ?? '');
                    $cleanupLabel = match ($cleanup) {
                        'pending' => 'old remote cleanup pending',
                        'cleaned' => 'old remote cleaned',
                        'failed' => 'old remote cleanup failed',
                        'skipped' => 'old remote cleanup skipped',
                        default => null,
                    };
                    $taskRef = $replacement->schedule_campaign_post_id
                        ?? $replacement->schedule_sidebar_campaign_task_id
                        ?? null;
                @endphp
                <div>
                    {{ $replacement->old_hostname }} → {{ $replacement->new_hostname }}
                    <span class="text-blue-600">
                        @if ($taskRef)
                            · task #{{ $taskRef }}
                        @endif
                        · {{ $replacement->created_at?->format('d M Y H:i') }}
                        @if ($replacement->state !== 'completed')
                            · queue dispatch {{ $replacement->state === 'dispatch_failed' ? 'failed' : 'pending' }}
                        @endif
                        @if (filled($replacement->previous_remote_id))
                            · previous remote {{ \Illuminate\Support\Str::limit((string) $replacement->previous_remote_id, 28) }}
                        @endif
                        @if ($cleanupLabel)
                            · {{ $cleanupLabel }}
                        @endif
                    </span>
                </div>
            @endforeach
        </div>
    </div>
@endif
