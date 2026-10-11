<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Admin\BillingCampaignType;
use App\Models\Admin\Domain;
use App\Models\Admin\LocalClient;
use App\Models\Admin\LocalClientManualBilling;
use App\Models\Admin\LocalClientPaymentEvent;
use App\Support\CurrencyFormatter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LocalClientManualBillingService
{
    public const KINDS = [
        'post',
        'sidebar',
        'hidden_links',
        'sticky',
        'schedule_post',
        'schedule_sidebar',
        'schedule_sticky',
    ];

    public function __construct(
        private readonly LocalClientBillingService $billing,
    ) {}

    /**
     * @return array{
     *   quantity: int,
     *   missing: list<string>,
     *   total: ?string,
     *   currency: ?string,
     *   formatted_total: ?string,
     *   lines: list<array<string, mixed>>
     * }
     */
    public function preview(LocalClient $client, string $campaignType, ?string $liveText): array
    {
        $parsed = $this->parseLiveLines($liveText);
        $empty = [
            'quantity' => count($parsed['sources']),
            'missing' => $parsed['missing'],
            'total' => null,
            'currency' => null,
            'formatted_total' => null,
            'lines' => [],
        ];

        if ($parsed['sources'] === [] || $parsed['missing'] !== []) {
            return $empty;
        }

        $priced = $this->priceResolved($client, $campaignType, $parsed['resolved']);

        return [
            'quantity' => count($parsed['sources']),
            'missing' => [],
            'total' => $priced['total'],
            'currency' => $priced['currency'],
            'formatted_total' => CurrencyFormatter::format($priced['total'], $priced['currency']),
            'lines' => collect($priced['lines'])->map(function (array $line) use ($priced) {
                return [
                    'domain_name' => $line['domain_name'],
                    'formatted_line_total' => CurrencyFormatter::format($line['line_total'], $priced['currency']),
                ];
            })->values()->all(),
        ];
    }

    /**
     * @param  array{
     *   campaign_no: string,
     *   campaign_type: string,
     *   payment_status: string,
     *   post_links?: ?string,
     *   keyword: string,
     *   url: string
     * }  $input
     */
    public function create(LocalClient $client, Admin $admin, array $input): LocalClientManualBilling
    {
        $campaignNo = trim((string) $input['campaign_no']);
        if ($campaignNo === '') {
            throw ValidationException::withMessages([
                'campaign_no' => 'Campaign name is required.',
            ]);
        }

        $kind = $this->kind((string) $input['campaign_type']);
        $parsed = $this->parseLiveLines($input['post_links'] ?? null);
        if ($parsed['sources'] === []) {
            throw ValidationException::withMessages([
                'post_links' => 'Enter at least one live post link or domain.',
            ]);
        }
        if ($parsed['missing'] !== []) {
            throw ValidationException::withMessages([
                'post_links' => 'These domains are not in the inventory: '.implode(', ', $parsed['missing']).'.',
            ]);
        }

        $keywords = $this->entryLines($input['keyword'] ?? null);
        $urls = $this->entryLines($input['url'] ?? null);
        $this->assertCountsMatch(count($parsed['sources']), $keywords, $urls);

        $priced = $this->priceResolved($client, $kind, $parsed['resolved']);
        $paid = ($input['payment_status'] ?? 'unpaid') === 'paid';
        $paidAt = $paid ? now() : null;

        return DB::transaction(function () use ($client, $admin, $kind, $campaignNo, $paid, $paidAt, $parsed, $keywords, $urls, $priced) {
            $bill = LocalClientManualBilling::query()->create([
                'local_client_id' => $client->id,
                'campaign_no' => mb_substr($campaignNo, 0, 191),
                'report_token' => Str::random(64),
                'billing_campaign_type' => $kind,
                'post_links' => implode("\n", $parsed['sources']),
                'keyword' => implode("\n", $keywords),
                'target_url' => implode("\n", $urls),
                'billing_total' => $priced['total'],
                'billing_amount_paid' => null,
                'billing_currency' => $priced['currency'],
                'billing_snapshot' => [
                    'total' => $priced['total'],
                    'currency' => $priced['currency'],
                    'billing_campaign_type' => $priced['rate']->value,
                    'campaign_kind' => $kind,
                    'lines' => $priced['lines'],
                ],
                'billing_payment_status' => $paid ? 'paid' : 'unpaid',
                'billing_paid_at' => $paidAt,
                'billing_paid_by_admin_id' => $paid ? $admin->id : null,
                'billing_payment_note' => null,
                'admin_id' => $admin->id,
            ]);

            if ($paid) {
                LocalClientPaymentEvent::query()->create([
                    'billable_type' => $bill::class,
                    'billable_id' => $bill->id,
                    'local_client_id' => $client->id,
                    'campaign_no' => mb_substr($campaignNo, 0, 64),
                    'old_status' => null,
                    'new_status' => 'paid',
                    'note' => null,
                    'admin_id' => $admin->id,
                    'created_at' => $paidAt,
                ]);
            }

            return $bill;
        });
    }

    /**
     * @return array{sources: list<string>, missing: list<string>, resolved: list<array{source: string, domain: Domain}>}
     */
    private function parseLiveLines(?string $raw): array
    {
        $sources = $this->entryLines($raw);
        if (count($sources) > 500) {
            throw ValidationException::withMessages([
                'post_links' => 'A manual campaign can include at most 500 lines.',
            ]);
        }

        $normalized = [];
        $lookup = [];
        foreach ($sources as $source) {
            $name = $this->hostFromLine($source);
            $normalized[] = $name;
            if ($name === '') {
                continue;
            }
            $lookup[] = $name;
            $alternate = $this->alternateName($name);
            if ($alternate !== null) {
                $lookup[] = $alternate;
            }
        }

        $domains = Domain::collectionByNormalizedName($lookup);
        $resolved = [];
        $missing = [];
        foreach ($sources as $index => $source) {
            $name = $normalized[$index];
            $domain = $name !== '' ? $domains->get($name) : null;
            if (! $domain && $name !== '') {
                $alternate = $this->alternateName($name);
                $domain = $alternate !== null ? $domains->get($alternate) : null;
            }
            if (! $domain) {
                $missing[] = $name !== '' ? $name : $source;

                continue;
            }
            $resolved[] = [
                'source' => $source,
                'domain' => $domain,
            ];
        }

        return [
            'sources' => $sources,
            'missing' => array_values(array_unique($missing)),
            'resolved' => $resolved,
        ];
    }

    /**
     * @param  list<array{source: string, domain: Domain}>  $resolved
     * @return array{total: string, currency: string, rate: BillingCampaignType, lines: list<array<string, mixed>>}
     */
    private function priceResolved(LocalClient $client, string $kind, array $resolved): array
    {
        $rate = $this->rateType($kind);
        $domainIds = [];
        foreach ($resolved as $row) {
            $domainIds[] = $row['domain']->id;
        }
        $uniqueIds = array_values(array_unique($domainIds));

        try {
            $result = $this->billing->calculate($client, $rate, $uniqueIds);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first()
                ?: 'Could not price the entered domains.';

            throw ValidationException::withMessages([
                'post_links' => $message,
            ]);
        }

        $pricedById = collect($result->lines)->keyBy('domain_id');
        $totalCents = 0;
        $lines = [];
        foreach ($resolved as $row) {
            $priced = $pricedById->get($row['domain']->id);
            if (! is_array($priced)) {
                throw ValidationException::withMessages([
                    'post_links' => 'Could not price '.$row['domain']->name.'.',
                ]);
            }
            $totalCents += LocalClientPaymentService::toCents((string) $priced['unit_price']);
            $lines[] = [
                'domain_id' => $priced['domain_id'],
                'domain_name' => $priced['domain_name'],
                'category_id' => $priced['category_id'],
                'category_name' => $priced['category_name'],
                'billing_campaign_type' => $rate->value,
                'unit_price' => $priced['unit_price'],
                'line_total' => $priced['unit_price'],
                'source' => $row['source'],
            ];
        }

        if ($totalCents <= 0) {
            throw ValidationException::withMessages([
                'post_links' => 'The calculated total must be greater than zero.',
            ]);
        }

        return [
            'total' => LocalClientPaymentService::fromCents($totalCents),
            'currency' => strtoupper((string) $client->default_currency),
            'rate' => $rate,
            'lines' => $lines,
        ];
    }

    private function kind(string $campaignType): string
    {
        if (! in_array($campaignType, self::KINDS, true)) {
            throw ValidationException::withMessages([
                'campaign_type' => 'Choose a campaign type.',
            ]);
        }

        return $campaignType;
    }

    private function rateType(string $kind): BillingCampaignType
    {
        $kind = $this->kind($kind);

        return match ($kind) {
            'sidebar', 'schedule_sidebar' => BillingCampaignType::Sidebar,
            'hidden_links' => BillingCampaignType::HiddenLinks,
            'sticky', 'schedule_sticky' => BillingCampaignType::Sticky,
            default => BillingCampaignType::Post,
        };
    }

    /**
     * @param  list<string>  $keywords
     * @param  list<string>  $urls
     */
    private function assertCountsMatch(int $quantity, array $keywords, array $urls): void
    {
        if (count($keywords) === $quantity && count($urls) === $quantity) {
            return;
        }

        throw ValidationException::withMessages([
            'keyword' => sprintf(
                'Keywords and URLs must match the %d live lines. Keywords are %d and URLs are %d.',
                $quantity,
                count($keywords),
                count($urls),
            ),
        ]);
    }

    /**
     * @return list<string>
     */
    private function entryLines(?string $raw): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/\R/', (string) $raw) ?: []),
            static fn (string $line): bool => $line !== '',
        ));
    }

    private function hostFromLine(string $line): string
    {
        $line = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $line) ?? $line;
        $line = trim($line);
        if ($line === '') {
            return '';
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $line) === 1) {
            $candidate = $line;
        } elseif (str_starts_with($line, '//')) {
            $candidate = 'https:'.$line;
        } else {
            $candidate = 'https://'.$line;
        }

        $host = parse_url($candidate, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            $host = $line;
        }

        return normalizeDomainName(rtrim($host, " \t.,;:"));
    }

    private function alternateName(string $name): ?string
    {
        if (str_starts_with($name, 'www.')) {
            $bare = substr($name, 4);

            return $bare !== '' ? $bare : null;
        }

        return 'www.'.$name;
    }
}
