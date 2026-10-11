<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Installment Data</title>
    <style>
        @page { margin: 0; }
        @font-face {
            font-family: 'Outfit';
            src: url('{{ str_replace('\\', '/', storage_path('fonts/Outfit-Regular.ttf')) }}') format('truetype');
            font-weight: 400;
            font-style: normal;
        }
        @font-face {
            font-family: 'Outfit';
            src: url('{{ str_replace('\\', '/', storage_path('fonts/Outfit-Medium.ttf')) }}') format('truetype');
            font-weight: 500;
            font-style: normal;
        }
        @font-face {
            font-family: 'Outfit';
            src: url('{{ str_replace('\\', '/', storage_path('fonts/Outfit-Bold.ttf')) }}') format('truetype');
            font-weight: 700;
            font-style: normal;
        }
        body { font-family: 'Outfit', DejaVu Sans, sans-serif; font-size: 11px; color: #111827; margin: 0; }
        table { border-collapse: collapse; }
        .band { width: 100%; }
        .band-title {
            background: #ff4a17;
            color: #ffffff;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 0.5px;
            padding: 22px 8px 22px 28px;
            vertical-align: middle;
        }
        .band-logo {
            background: #ff4a17;
            text-align: right;
            vertical-align: middle;
            padding: 16px 28px 16px 8px;
            width: 90px;
        }
        .logo-chip { border-collapse: separate; border-spacing: 0; }
        .logo-chip td {
            background: #ffffff;
            text-align: center;
            padding: 6px;
            border-radius: 8px;
        }
        .content { padding: 22px 28px 28px; }
        .section { font-size: 15px; font-weight: 700; color: #111827; margin: 0 0 10px; }
        .meta { width: 100%; margin-bottom: 18px; }
        .detail-line { color: #111827; padding: 2px 0; white-space: nowrap; }
        .detail-line .label { color: #6b7280; }
        .side { text-align: right; vertical-align: top; width: 38%; }
        .side-label { font-size: 12px; font-weight: 700; color: #111827; margin-top: 8px; }
        .side-value { font-size: 11px; color: #374151; margin-top: 2px; }
        .ledger { width: 100%; margin-top: 8px; }
        .ledger th {
            background: #ff4a17;
            color: #ffffff;
            font-weight: 700;
            font-size: 11px;
            text-align: left;
            padding: 8px 10px;
            border: 1px solid #ff4a17;
        }
        .ledger td {
            background: #ffffff;
            color: #111827;
            border: 1px solid #e5e7eb;
            padding: 8px 10px;
            vertical-align: top;
        }
        .money { font-family: DejaVu Sans, sans-serif; text-align: right; white-space: nowrap; }
        .muted { color: #6b7280; }
        .totals { width: 100%; margin-top: 16px; }
        .total-box { width: 100%; background: #f3f4f6; }
        .total-box td { background: #f3f4f6; padding: 8px 12px; font-weight: 700; }
    </style>
</head>
<body>
    @php
        $logoFile = public_path('favicon.png');
        $logoSrc = str_replace('\\', '/', $logoFile);
        $clientLine = ! empty($clientName) ? $clientName : (empty($filters['client_id']) ? 'All clients' : null);
        $dateLine = null;
        if (! empty($filters['from']) || ! empty($filters['to'])) {
            $dateLine = ($filters['from'] ?: 'start').' to '.($filters['to'] ?: 'today');
        }
        $totals = [];
        foreach ($installments as $row) {
            $currency = strtoupper((string) $row->currency);
            $totals[$currency] = ($totals[$currency] ?? 0) + \App\Services\LocalClientPaymentService::toCents($row->amount);
        }
    @endphp

    <table class="band">
        <tr>
            <td class="band-title">INSTALLMENT DATA</td>
            <td class="band-logo">
                @if (is_file($logoFile))
                    <table class="logo-chip" align="right">
                        <tr>
                            <td>
                                <img src="{{ $logoSrc }}" width="42" height="42" alt="Diamond PBN">
                            </td>
                        </tr>
                    </table>
                @endif
            </td>
        </tr>
    </table>

    <div class="content">
        <table class="meta">
            <tr>
                <td valign="top">
                    <div class="section">Prepared for</div>
                    <table>
                        @if ($clientLine)
                            <tr>
                                <td class="detail-line"><span class="label">Client</span> : {{ $clientLine }}</td>
                            </tr>
                        @endif
                        @if (! empty($recordedByName))
                            <tr>
                                <td class="detail-line"><span class="label">Recorded by</span> : {{ $recordedByName }}</td>
                            </tr>
                        @endif
                        @if ($dateLine)
                            <tr>
                                <td class="detail-line"><span class="label">Dates</span> : {{ $dateLine }}</td>
                            </tr>
                        @endif
                    </table>
                </td>
                <td class="side">
                    <div class="side-label">Records</div>
                    <div class="side-value">{{ $installments->count() }}</div>
                    <div class="side-label">Export date</div>
                    <div class="side-value">{{ now()->format('F j, Y') }}</div>
                </td>
            </tr>
        </table>

        <div class="section">Installment details</div>
        <table class="ledger">
            <thead>
                <tr>
                    <th style="width: 22%;">Date</th>
                    <th style="width: 18%;">Client</th>
                    <th style="width: 38%;">Details</th>
                    <th class="money" style="width: 22%; color: #ffffff;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($installments as $installment)
                    <tr>
                        <td>{{ $installment->created_at?->format('M d, Y g:i A') }}</td>
                        <td>{{ $installment->localClient?->name ?: '—' }}</td>
                        <td>
                            {{ $installment->sourceLabel() }}<br>
                            <span class="muted">{{ $installment->note ?: '—' }}</span>
                            @if ($installment->allocationSummary() !== '')
                                <br><span class="muted">{{ $installment->allocationSummary() }}</span>
                            @endif
                        </td>
                        <td class="money">{{ \App\Support\CurrencyFormatter::format($installment->amount, $installment->currency) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">No installments found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($totals !== [])
            <table class="totals">
                <tr>
                    <td style="width: 52%;"></td>
                    <td style="width: 48%;">
                        <table class="total-box">
                            @foreach ($totals as $currency => $cents)
                                <tr>
                                    <td>Total</td>
                                    <td class="money">{{ \App\Support\CurrencyFormatter::format(\App\Services\LocalClientPaymentService::fromCents($cents), $currency) }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </td>
                </tr>
            </table>
        @endif
    </div>
</body>
</html>
