<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $client->name }} Billing Report</title>
    <style>
        @page { margin: 24px 20px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        .muted { color: #666; margin-bottom: 14px; font-size: 10px; }
        .grid { width: 100%; margin-bottom: 14px; border-collapse: collapse; }
        .grid td { padding: 8px; border: 1px solid #ddd; vertical-align: top; }
        table.items {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        table.items th,
        table.items td {
            border: 1px solid #ddd;
            padding: 5px 6px;
            text-align: left;
            vertical-align: top;
        }
        table.items th { background: #f3f4f6; font-size: 9px; }
        table.items .col-campaign { width: 16%; }
        table.items .col-type { width: 11%; }
        table.items .col-date { width: 14%; }
        table.items .col-total { width: 10%; }
        table.items .col-status { width: 8%; }
        table.items .col-url { width: 41%; }
        .wrap {
            word-wrap: break-word;
            word-break: break-all;
            overflow-wrap: anywhere;
            white-space: normal;
        }
        .url-link {
            color: #111;
            text-decoration: none;
            font-size: 8.5px;
            line-height: 1.35;
        }
    </style>
</head>
<body>
    @php
        $softBreak = static function (?string $value, int $chunk = 28): string {
            $value = (string) ($value ?? '');
            if ($value === '') {
                return '—';
            }

            $zwsp = json_decode('"\u200b"') ?: '';
            $broken = preg_replace('#([/?&=.\-_])#', '$1'.$zwsp, $value) ?? $value;
            $broken = preg_replace('/(.{'.$chunk.'})/u', '$1'.$zwsp, $broken) ?? $broken;

            return e($broken);
        };
    @endphp

    <h1>{{ $client->name }}</h1>
    <div class="muted">
        Billing report · {{ $client->default_currency }}
        @if (! empty($filters['is_active']) && ! empty($filters['label']))
            · Filter: {{ $filters['label'] }}
        @endif
    </div>

    <table class="grid">
        <tr>
            <td><strong>Total billed</strong><br>{{ $summary['total_billed'] }}</td>
            <td><strong>Paid</strong><br>{{ $summary['total_paid'] }} ({{ $summary['paid_count'] }})</td>
            <td><strong>Unpaid</strong><br>{{ $summary['total_unpaid'] }} ({{ $summary['unpaid_count'] }})</td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th class="col-campaign">Campaign</th>
                <th class="col-type">Type</th>
                <th class="col-date">Date</th>
                <th class="col-total">Total</th>
                <th class="col-status">Status</th>
                <th class="col-url">Report URL</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($campaigns as $row)
                <tr>
                    <td class="col-campaign wrap">{!! $softBreak($row['campaign_no'] ?? '', 22) !!}</td>
                    <td class="col-type wrap">{{ $row['type_label'] }}</td>
                    <td class="col-date wrap">{{ $row['created_at'] }}</td>
                    <td class="col-total">{{ \App\Support\CurrencyFormatter::format($row['billing_total'], $row['billing_currency'] ?? $client->default_currency) }}</td>
                    <td class="col-status">{{ ucfirst($row['billing_payment_status'] ?? 'unpaid') }}</td>
                    <td class="col-url wrap">
                        @if (! empty($row['report_url']))
                            <a class="url-link" href="{{ $row['report_url'] }}">{!! $softBreak($row['report_url'], 34) !!}</a>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
