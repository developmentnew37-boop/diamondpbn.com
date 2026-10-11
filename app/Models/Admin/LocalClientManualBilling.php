<?php

namespace App\Models\Admin;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocalClientManualBilling extends Model
{
    public const TYPE_LABEL = 'Manual campaign';

    protected $fillable = [
        'local_client_id',
        'campaign_no',
        'report_token',
        'billing_campaign_type',
        'post_links',
        'keyword',
        'target_url',
        'billing_total',
        'billing_amount_paid',
        'billing_currency',
        'billing_snapshot',
        'billing_payment_status',
        'billing_paid_at',
        'billing_paid_by_admin_id',
        'billing_payment_note',
        'admin_id',
    ];

    protected $casts = [
        'billing_total' => 'decimal:2',
        'billing_amount_paid' => 'decimal:2',
        'billing_snapshot' => 'array',
        'billing_paid_at' => 'datetime',
    ];

    public function localClient(): BelongsTo
    {
        return $this->belongsTo(LocalClient::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function reportTypeKey(): string
    {
        return match ((string) $this->billing_campaign_type) {
            'sidebar' => 'manual_sidebar',
            'hidden_links' => 'manual_hidden',
            'sticky' => 'manual_sticky',
            'schedule_post' => 'manual_schedule_post',
            'schedule_sidebar' => 'manual_schedule_sidebar',
            'schedule_sticky' => 'manual_schedule_sticky',
            default => 'manual_post',
        };
    }

    public function kindLabel(): string
    {
        return match ((string) $this->billing_campaign_type) {
            'sidebar' => 'Sidebar',
            'hidden_links' => 'Hidden Links',
            'sticky' => 'Sticky',
            'schedule_post' => 'Schedule Post',
            'schedule_sidebar' => 'Schedule Blogroll',
            'schedule_sticky' => 'Schedule Sticky',
            default => 'Post',
        };
    }

    /**
     * @return array{kind: string, live_label: string, domains: list<string>, post_links: list<string>, keywords: list<string>, urls: list<string>}
     */
    public function reportDetails(): array
    {
        $lines = is_array($this->billing_snapshot) ? ($this->billing_snapshot['lines'] ?? []) : [];
        $domains = [];

        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }

            $name = trim((string) ($line['domain_name'] ?? ''));
            if ($name !== '') {
                $domains[] = $name;
            }
        }

        $links = [];
        if (is_string($this->post_links) && trim($this->post_links) !== '') {
            $links = array_values(array_filter(array_map(
                'trim',
                preg_split('/\R/', $this->post_links) ?: []
            )));
        }

        return [
            'kind' => $this->kindLabel(),
            'live_label' => $this->usesDomainLines() ? 'Entered domains' : 'Live post links',
            'domains' => $domains,
            'post_links' => $links,
            'keywords' => $this->detailLines($this->keyword),
            'urls' => $this->detailLines($this->target_url),
        ];
    }

    /**
     * @return list<array{domain: string, live: string, keyword: string, url: string}>
     */
    public function reportLines(): array
    {
        $details = $this->reportDetails();
        $count = max(
            count($details['domains']),
            count($details['post_links']),
            count($details['keywords']),
            count($details['urls']),
        );
        $lines = [];

        for ($index = 0; $index < $count; $index++) {
            $lines[] = [
                'domain' => $details['domains'][$index] ?? '',
                'live' => $details['post_links'][$index] ?? '',
                'keyword' => $details['keywords'][$index] ?? '',
                'url' => $details['urls'][$index] ?? '',
            ];
        }

        return $lines;
    }

    public function usesDomainLines(): bool
    {
        return in_array((string) $this->billing_campaign_type, ['sidebar', 'hidden_links', 'schedule_sidebar'], true);
    }

    /**
     * @return list<string>
     */
    private function detailLines(mixed $raw): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/\R/', (string) $raw) ?: []),
            static fn (string $line): bool => $line !== '',
        ));
    }
}
