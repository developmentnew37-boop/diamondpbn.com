<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $client->name }} Billing Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .muted { color: #666; margin-bottom: 16px; }
        .grid { width: 100%; margin-bottom: 16px; }
        .grid td { padding: 8px; border: 1px solid #ddd; }
        table.items { width: 100%; border-collapse: collapse; }
        table.items th, table.items td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        table.items th { background: #f3f4f6; }
    </style>
</head>
<body>
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
                <th>Campaign</th>
                <th>Type</th>
                <th>Date</th>
                <th>Total</th>
                <th>Status</th>
                <th>Report URL</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($campaigns as $row)
                <tr>
                    <td>{{ $row['campaign_no'] }}</td>
                    <td>{{ $row['type_label'] }}</td>
                    <td>{{ $row['created_at'] }}</td>
                    <td>{{ \App\Support\CurrencyFormatter::format($row['billing_total'], $row['billing_currency'] ?? $client->default_currency) }}</td>
                    <td>{{ ucfirst($row['billing_payment_status'] ?? 'unpaid') }}</td>
                    <td>{{ $row['report_url'] ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
