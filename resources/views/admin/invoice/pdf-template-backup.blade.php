<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice {{ $invoice_number }}</title>
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Trebuchet MS', 'Lucida Grande', 'Lucida Sans Unicode', 'Helvetica', 'Arial', sans-serif;
    color: #18202c;
    font-size: 10pt;
    background: #ffffff;
    font-weight: 600;
}

.invoice {
    width: 794px;
    margin: 0 auto;
    background: #ffffff;
    position: relative;
}

/* Header */
.header {
    height: 110px;
    background: #e9e9e9;
    padding: 15px 50px;
}

.header-table {
    width: 100%;
    height: 100%;
    border-collapse: collapse;
}

.header-table td {
    vertical-align: middle;
}

.logo-cell {
    width: 90px;
}

.logo {
    width: 85px;
    height: 85px;
    object-fit: contain;
}

.logo-placeholder {
    width: 85px;
    height: 85px;
    background: #d0d0d0;
    border-radius: 4px;
}

.brand-cell {
    padding-left: 28px;
}

.brand {
    font-size: 16pt;
    font-weight: 800;
    color: #101820;
    letter-spacing: 0.3px;
}

.invoice-title-cell {
    text-align: right;
    width: 190px;
}

.invoice-title {
    font-size: 38pt;
    font-weight: 800;
    color: #273140;
    letter-spacing: 10px;
}

/* Top Bar */
.top-bar {
    height: 38px;
    background: #f4f4f4;
    position: relative;
}

.top-bar-table {
    width: 100%;
    height: 38px;
    border-collapse: collapse;
}

.top-bar-table td {
    height: 38px;
    vertical-align: middle;
}

.invoice-no-cell {
    width: 380px;
    height: 38px;
    background: {{ $theme_color }};
    color: #ffffff;
    padding-left: 55px;
    font-size: 13pt;
    font-weight: 700;
    position: relative;
}

.date-cell {
    height: 38px;
    background: #f4f4f4;
    text-align: right;
    padding-right: 60px;
    font-size: 12pt;
    font-weight: 700;
}

/* Invoice To */
.invoice-to-section {
    padding: 20px 50px 22px;
}

.invoice-to-text {
    font-size: 19pt;
    font-weight: 700;
    letter-spacing: 0.5px;
}

.client-name {
    font-size: 28pt;
    font-weight: 500;
    margin-left: 6px;
}

/* Items Table - 90% WIDTH CENTERED */
.items-section {
    padding: 0;
    text-align: center;
}

.items-table {
    width: 90%;
    margin: 0 auto;
    border-collapse: collapse;
}

.items-table thead th {
    background: #e9e9e9;
    color: #18202c;
    font-size: 11pt;
    font-weight: 800;
    text-align: left;
    padding: 10px 14px;
    height: 36px;
    letter-spacing: 0.3px;
}

.items-table thead th.highlight {
    background: {{ $theme_color }};
    color: #ffffff;
}

.items-table tbody td {
    font-size: 11pt;
    font-weight: 800;
    padding: 12px 14px;
    border-bottom: 1.8px solid #a9aaa9;
    height: 50px;
}

.items-table tbody tr:last-child td {
    border-bottom: none;
}

/* Column widths - Percentage based */
.items-table .col-sl {
    width: 8%;
    text-align: center;
}

.items-table .col-item {
    width: 45%;
    text-align: left;
}

.items-table .col-price {
    width: 17%;
    text-align: left;
}

.items-table .col-qty {
    width: 13%;
    text-align: center;
}

.items-table .col-total {
    width: 17%;
    text-align: left;
}

.items-table tbody td:nth-child(odd) {
    background: #edf4f0;
}

.items-table tbody tr.empty-row td {
    color: transparent;
}

/* Payment Section - ALIGNED WITH TABLE */
.payment-section {
    padding: 30px 0 0;
    width: 90%;
    margin: 0 auto;
}

.payment-table {
    width: 100%;
    border-collapse: collapse;
}

.payment-info-cell {
    width: 52%;
    vertical-align: top;
    padding-right: 20px;
}

.payment-title {
    font-size: 12pt;
    font-weight: 800;
    margin-bottom: 10px;
    letter-spacing: 0.5px;
}

.payment-info {
    font-size: 9pt;
    font-weight: 800;
    line-height: 1.6;
}

.payment-info p {
    margin: 4px 0;
}

.total-cell {
    width: 48%;
    vertical-align: top;
    text-align: right;
}

.total-box {
    background: {{ $theme_color }};
    color: #ffffff;
    padding: 9px 22px;
    font-size: 14pt;
    font-weight: 800;
    display: inline-block;
    min-width: 230px;
    position: relative;
    height: 36px;
    line-height: 18px;
}

.total-amount {
    margin-left: 18px;
}

/* Signature */
.signature-section {
    padding: 40px 75px 55px 0;
    text-align: right;
}

.signature-line {
    width: 150px;
    height: 2px;
    background: #333333;
    margin: 0 0 10px auto;
}

.signature-text {
    font-size: 11pt;
    font-weight: 800;
    letter-spacing: 0.5px;
}

/* Footer */
.footer {
    background: #e9e9e9;
    height: 38px;
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
}

.footer-table {
    width: 100%;
    height: 38px;
    border-collapse: collapse;
}

.footer-table td {
    height: 38px;
    vertical-align: middle;
}

.footer-left {
    width: 350px;
    height: 38px;
    background: {{ $theme_color }};
    color: #ffffff;
    padding-left: 55px;
    font-size: 10pt;
    font-weight: 800;
    letter-spacing: 1.5px;
    position: relative;
}

.footer-right {
    height: 38px;
    background: #e9e9e9;
    text-align: center;
    font-size: 13pt;
    font-weight: 800;
}

/* Page setup */
@page {
    size: A4 portrait;
    margin: 0;
}

.page-wrapper {
    position: relative;
    min-height: 1085px;
    max-height: 1085px;
    padding-bottom: 38px;
}
</style>
</head>
<body>

<div class="page-wrapper">
<div class="invoice">

    <!-- Header -->
    <div class="header">
        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    @if($logo_base64)
                        <img src="data:{{ $logo_mime }};base64,{{ $logo_base64 }}" class="logo" alt="Logo">
                    @else
                        <div class="logo-placeholder"></div>
                    @endif
                </td>
                <td class="brand-cell">
                    <div class="brand">{{ strtoupper($company_name) }}</div>
                </td>
                <td class="invoice-title-cell">
                    <div class="invoice-title">INVOICE</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Top Bar with Arrow -->
    <div class="top-bar">
        <table class="top-bar-table">
            <tr>
                <td class="invoice-no-cell">
                    Invoice# <span style="margin-left: 20px;">{{ $invoice_number }}</span>
                    <svg width="25" height="38" style="position: absolute; right: -25px; top: 0;">
                        <polygon points="0,0 25,19 0,38" fill="{{ $theme_color }}" />
                    </svg>
                </td>
                <td class="date-cell">
                    Date <span style="margin-left: 25px;">{{ date('d / M / Y', strtotime($invoice_date)) }}</span>
                </td>
            </tr>
        </table>
    </div>

    <!-- Invoice To -->
    <div class="invoice-to-section">
        <span class="invoice-to-text">Invoice to:</span><span class="client-name">{{ $client_name }}</span>
    </div>

    <!-- Items Table - 90% WIDTH -->
    <div class="items-section">
        <table class="items-table">
            <thead>
                <tr>
                    <th class="col-sl highlight">SL.</th>
                    <th class="col-item highlight" style="position: relative;">
                        Item Description
                        <svg width="25" height="36" style="position: absolute; right: -25px; top: 0;">
                            <polygon points="0,0 25,18 0,36" fill="{{ $theme_color }}" />
                        </svg>
                    </th>
                    <th class="col-price">Price</th>
                    <th class="col-qty">Qty.</th>
                    <th class="col-total">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $index => $item)
                <tr>
                    <td class="col-sl">{{ $index + 1 }}</td>
                    <td class="col-item">{{ $item['title'] }}</td>
                    <td class="col-price">${{ number_format($item['price'], 2) }}</td>
                    <td class="col-qty">{{ $item['quantity'] }}</td>
                    <td class="col-total">${{ number_format($item['subtotal'], 2) }}</td>
                </tr>
                @endforeach

                @php
                    $emptyRows = max(0, 5 - count($items));
                @endphp

                @for($i = 0; $i < $emptyRows; $i++)
                <tr class="empty-row">
                    <td class="col-sl">_</td>
                    <td class="col-item">_</td>
                    <td class="col-price">_</td>
                    <td class="col-qty">_</td>
                    <td class="col-total">_</td>
                </tr>
                @endfor
            </tbody>
        </table>
    </div>

    <!-- Payment Info and Total - ALIGNED WITH TABLE -->
    <div class="payment-section">
        <table class="payment-table">
            <tr>
                <td class="payment-info-cell">
                    <div class="payment-title">Payment Info:</div>
                    <div class="payment-info">
                        @if($company_address)
                        <p>Office Address: {{ $company_address }}</p>
                        @endif
                        @if($company_email)
                        <p>Email: {{ $company_email }}</p>
                        @endif
                        @if($company_phone)
                        <p>Phone: {{ $company_phone }}</p>
                        @endif
                        @if($notes)
                        <p style="margin-top: 8px;">{{ $notes }}</p>
                        @endif
                    </div>
                </td>
                <td class="total-cell">
                    <div class="total-box">
                        Total:<span class="total-amount">${{ number_format($total, 2) }}</span>
                        <svg width="25" height="36" style="position: absolute; right: -25px; top: 0;">
                            <polygon points="0,0 25,18 0,36" fill="{{ $theme_color }}" />
                        </svg>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Signature -->
    <div class="signature-section">
        <div class="signature-line"></div>
        <div class="signature-text">Authorised Sign</div>
    </div>

</div>

<!-- Footer -->
<div class="footer">
    <table class="footer-table">
        <tr>
            <td class="footer-left">
                Thank you for your business
                <svg width="25" height="38" style="position: absolute; right: -25px; top: 0;">
                    <polygon points="0,0 25,19 0,38" fill="{{ $theme_color }}" />
                </svg>
            </td>
            <td class="footer-right">{{ $company_email ?? 'invoice@company.com' }}</td>
        </tr>
    </table>
</div>

</div>

</body>
</html>
