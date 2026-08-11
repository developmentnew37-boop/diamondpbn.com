<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1a1a1a;
            line-height: 1.45;
            padding: 36px 42px;
        }
        .header {
            border-bottom: 2px solid #222;
            padding-bottom: 14px;
            margin-bottom: 22px;
        }
        .header-top {
            width: 100%;
            margin-bottom: 6px;
        }
        .header-top td { vertical-align: bottom; }
        .company-name {
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 0.3px;
        }
        .doc-title {
            font-size: 22px;
            font-weight: bold;
            text-align: right;
            letter-spacing: 2px;
            color: #333;
        }
        .meta {
            width: 100%;
            margin-bottom: 20px;
        }
        .meta td {
            vertical-align: top;
            width: 50%;
            padding-bottom: 4px;
        }
        .meta-label {
            color: #666;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .meta-value {
            font-size: 12px;
            font-weight: bold;
            margin-top: 2px;
        }
        .meta-right { text-align: right; }
        .client-block {
            background: #f7f7f7;
            border: 1px solid #ddd;
            padding: 12px 14px;
            margin-bottom: 22px;
        }
        .client-block .label {
            font-size: 10px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .client-block .name {
            font-size: 14px;
            font-weight: bold;
        }
        .client-block .sub {
            font-size: 11px;
            color: #444;
            margin-top: 2px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }
        .items-table thead th {
            background: #222;
            color: #fff;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            padding: 9px 10px;
            text-align: left;
        }
        .items-table thead th.num { text-align: right; }
        .items-table tbody td {
            border-bottom: 1px solid #e0e0e0;
            padding: 10px;
            font-size: 11px;
        }
        .items-table tbody tr:nth-child(even) td {
            background: #fafafa;
        }
        .items-table tbody td.num {
            text-align: right;
            white-space: nowrap;
        }
        .summary {
            width: 100%;
            margin-top: 8px;
        }
        .summary td { vertical-align: top; }
        .summary-left {
            width: 55%;
            font-size: 10px;
            color: #555;
            padding-top: 6px;
        }
        .summary-right {
            width: 45%;
        }
        .total-box {
            border: 2px solid #222;
            padding: 12px 16px;
            text-align: right;
        }
        .total-label {
            font-size: 10px;
            text-transform: uppercase;
            color: #666;
            letter-spacing: 0.5px;
        }
        .total-amount {
            font-size: 20px;
            font-weight: bold;
            margin-top: 4px;
        }
        .status-badge {
            display: inline-block;
            margin-top: 8px;
            padding: 3px 8px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            border: 1px solid #999;
            color: #333;
        }
        .footer-note {
            margin-top: 28px;
            padding-top: 12px;
            border-top: 1px solid #ddd;
            font-size: 9px;
            color: #888;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-top">
            <tr>
                <td class="company-name">{{ $company_name ?? 'Diamond PBN' }}</td>
                <td class="doc-title">INVOICE</td>
            </tr>
        </table>
    </div>

    <table class="meta">
        <tr>
            <td>
                <div class="meta-label">Invoice Number</div>
                <div class="meta-value">{{ $invoice_number }}</div>
            </td>
            <td class="meta-right">
                <div class="meta-label">Date</div>
                <div class="meta-value">{{ \Illuminate\Support\Carbon::parse($invoice_date)->format('d M Y') }}</div>
            </td>
        </tr>
        @if(!empty($campaign_no))
        <tr>
            <td colspan="2" style="padding-top: 8px;">
                <div class="meta-label">Campaign</div>
                <div class="meta-value">{{ $campaign_no }}</div>
            </td>
        </tr>
        @endif
    </table>

    <div class="client-block">
        <div class="label">Bill To</div>
        <div class="name">{{ $client_name }}</div>
        @if(!empty($client_address))
            <div class="sub">{!! nl2br(e($client_address)) !!}</div>
        @endif
        @if(!empty($client_email))
            <div class="sub">{{ $client_email }}</div>
        @endif
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 8%;">#</th>
                <th style="width: 44%;">Description</th>
                <th class="num" style="width: 16%;">Rate</th>
                <th class="num" style="width: 12%;">Qty</th>
                <th class="num" style="width: 20%;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $item['title'] }}</td>
                <td class="num">{{ $currency }} {{ number_format($item['price'], 2) }}</td>
                <td class="num">{{ $item['quantity'] }}</td>
                <td class="num">{{ $currency }} {{ number_format($item['subtotal'], 2) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="5" style="text-align: center; color: #888; padding: 16px;">No line items</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <table class="summary">
        <tr>
            <td class="summary-left">
                @if(!empty($company_email))
                    <div>{{ $company_email }}</div>
                @endif
                @if(!empty($company_phone))
                    <div>{{ $company_phone }}</div>
                @endif
                @if(!empty($payment_status))
                    <div class="status-badge">{{ ucfirst($payment_status) }}</div>
                @endif
            </td>
            <td class="summary-right">
                <div class="total-box">
                    <div class="total-label">Amount Due</div>
                    <div class="total-amount">{{ $final_total }}</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="footer-note">
        Generated from campaign billing snapshot. Grouped by domain category.
    </div>
</body>
</html>
