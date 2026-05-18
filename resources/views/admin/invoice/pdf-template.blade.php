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

@font-face {
    font-family: 'Outfit';
    src: url('{{ storage_path("fonts/Outfit-Regular.ttf") }}') format('truetype');
    font-weight: 400;
    font-style: normal;
}

@font-face {
    font-family: 'Outfit';
    src: url('{{ storage_path("fonts/Outfit-Medium.ttf") }}') format('truetype');
    font-weight: 500;
    font-style: normal;
}

@font-face {
    font-family: 'Outfit';
    src: url('{{ storage_path("fonts/Outfit-Bold.ttf") }}') format('truetype');
    font-weight: 700;
    font-style: normal;
}

@font-face {
    font-family: 'Outfit';
    src: url('{{ storage_path("fonts/Outfit-ExtraBold.ttf") }}') format('truetype');
    font-weight: 800;
    font-style: normal;
}

body {
    font-family: 'Outfit', 'Helvetica', 'Arial', sans-serif;
    color: #18202c;
    font-size: 10pt;
    background: #ffffff;
    font-weight: 500;
}

.invoice {
    width: 794px;
    margin: 0 auto;
    background: #ffffff;
    position: relative;
}

/* Header */
.header {
    height: 100px;
    background: #e9e9e9;
    padding: 18px 55px;
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
    width: 95px;
}

.logo {
    width: 90px;
    height: 90px;
    object-fit: fill;
}

.logo-placeholder {
    width: 90px;
    height: 90px;
    background: #d0d0d0;
    border-radius: 6px;
}

.brand-cell {
    padding-left: 30px;
}

.brand {
    font-size: 15pt;
    font-weight: bold;
    color: #101820;
    letter-spacing: 0.5px;
}

.invoice-title-cell {
    text-align: right;
    width: 200px;
}

.invoice-title {
    font-size: 30pt;
    font-weight: bold;
    color: #273140;
    letter-spacing: 12px;
}

/* Top Bar with CSS Arrow */
.top-bar {
    height: 40px;
    background: #f4f4f4;
    position: relative;
}

.top-bar-table {
    width: 100%;
    height: 40px;
    border-collapse: collapse;
}

.top-bar-table td {
    height: 40px;
    vertical-align: middle;
}

.invoice-no-cell {
    width: 385px;
    height: 40px;
    background: {{ $theme_color }};
    color: #ffffff;
    padding-left: 58px;
    font-size: 13pt;
    font-weight: bold;
    position: relative;
}

/* CSS Arrow for Top Bar */
.invoice-no-cell::after {
    content: "";
    position: absolute;
    right: -20px;
    top: 0;
    width: 0;
    height: 0;
    border-top: 20px solid transparent;
    border-bottom: 20px solid transparent;
    border-left: 20px solid {{ $theme_color }};
}

.date-cell {
    height: 40px;
    background: #f4f4f4;
    text-align: right;
    padding-right: 65px;
    font-size: 12pt;
    font-weight: bold;
}

/* Invoice To */
.invoice-to-section {
    padding: 22px 55px 18px;
    min-height: 60px;
    max-height: 90px;
    overflow: hidden;
}

.invoice-to-text {
    font-size: 18pt;
    font-weight: bold;
    letter-spacing: 0.8px;
}

.client-name {
    font-size: 24pt;
    font-weight: normal;
    margin-left: 8px;
}

.client-address {
    font-size: 9pt;
    font-weight: normal;
    margin-top: 4px;
    margin-left: 0px;
    color: #18202c;
    line-height: 1.3;
    max-width: 500px;
    word-wrap: break-word;
}

/* Items Table - 90% WIDTH */
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
    font-weight: bold;
    text-align: left;
    padding: 8px 12px;
    height: 38px;
    letter-spacing: 0.5px;
}

.items-table thead th.highlight {
    background: {{ $theme_color }};
    color: #ffffff;
    position: relative;
}

/* CSS Arrow for Table Header */
.items-table thead th.item-head::after {
    content: "";
    position: absolute;
    right: -26px;
    top: 0px;
    width: 0;
    height: 0;
    border-top: 27px solid transparent;
    border-bottom: 27px solid transparent;
    border-left: 27px solid {{ $theme_color }};
}

.items-table tbody td {
    font-size: 11pt;
    font-weight: normal;
    /* padding: 13px 16px; */
    padding: 8px 12px;
    border-bottom: 2px solid #a9aaa9;
    height: 52px;
}

.items-table tbody tr:last-child td {
    border-bottom: none;
}

/* Column widths */
.items-table .col-sl {
    width: 8%;
    text-align: center;
}

.items-table .col-item {
    width: 44%;
    text-align: left;
}

.items-table .col-price {
    width: 17%;
    text-align: left;
    padding-left:26px !important;
}

.items-table .col-qty {
    width: 13%;
    text-align: center;
}

.items-table .col-total {
    width: 18%;
    text-align: right;
}

.items-table tbody td:nth-child(odd) {
    background: #edf4f0;
}

.items-table tbody tr.empty-row td {
    color: transparent;
}

/* Payment Section - ALIGNED */
.payment-section {
    padding: 32px 0 0;
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
    padding-right: 25px;
}

.payment-title {
    font-size: 16pt;
    font-weight: normal;
    margin-bottom: 4px;
    letter-spacing: 0.8px;
}

.payment-info {
    font-size: 9pt;
    font-weight: normal;
    line-height: 1.7;
}

.payment-info p {
    margin: 5px 0;
}

.total-cell {
    width: 48%;
    vertical-align: top;
    text-align: right;
}

/* Total Box with CSS Arrow */
.total-box {
    background: {{ $theme_color }};
    color: #ffffff;
    font-size: 14pt;
    font-weight: normal;
    display: inline-block;
    min-width: 240px;
    position: relative;
    height: 50px !important;
    /* line-height: 50px; */
    padding: 0 16px;
    vertical-align:middle;
    padding-top:10px;
}

/* CSS Arrow for Total Box */
.total-box::after {
    content: "";
    position: absolute;
    right: -30px;
    top: 0px;
    width: 0;
    height: 0;
    border-top: 30px solid transparent;
    border-bottom: 30px solid transparent;
    border-left: 30px solid {{ $theme_color }};
}

.total-amount {
    margin-left: 10px;
    margin:0px;
}

/* Signature - RIGHT ALIGNED */
.signature-section {
    padding: 22px 80px 10px 0;
    text-align: right;
}

.signature-box {
    display: inline-block;
    text-align: center;
}

.signature-line {
    width: 160px;
    height: 2px;
    background: #333333;
    margin: 0 auto 11px auto;
}

.signature-text {
    font-size: 11pt;
    font-weight: bold;
    letter-spacing: 0.8px;
    text-align: center;
}

/* Footer with CSS Arrow */
.footer {
    background: #e9e9e9;
    height: 40px;
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    overflow:hidden;
}

.footer-table {
    width: 100%;
    height: 40px;
    border-collapse: collapse;
}

.footer-table td {
    height: 40px;
    vertical-align: middle;
}

.footer-left {
    width: 360px;
    height: 40px;
    background: {{ $theme_color }};
    color: #ffffff;
    padding-left: 58px;
    font-size: 10pt;
    font-weight: bold;
    letter-spacing: 1.8px;
    position: relative;
}

/* CSS Arrow for Footer */
.footer-left::after {
    content: "";
    position: absolute;
    right: -20px;
    top: 0;
    width: 0;
    height: 0;
    border-top: 20px solid transparent;
    border-bottom: 20px solid transparent;
    border-left: 20px solid {{ $theme_color }};
}

.footer-right {
    height: 40px;
    background: #e9e9e9;
    text-align: center;
    font-size: 13pt;
    font-weight: bold;
}

/* Page setup */
@page {
    size: A4 portrait;
    margin: 0;
}

.page-wrapper {
    position: relative;
    height: 1122px;
    overflow: hidden;
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

    <!-- Top Bar with CSS Arrow -->
    <div class="top-bar">
        <table class="top-bar-table">
            <tr>
                <td class="invoice-no-cell">
                    Invoice# <span style="margin-left: 22px;">{{ $invoice_number }}</span>
                </td>
                <td class="date-cell">
                    Date <span style="margin-left: 10   px;">{{ date('d / M / Y', strtotime($invoice_date)) }}</span>
                </td>
            </tr>
        </table>
    </div>

    <!-- Invoice To -->
    <div class="invoice-to-section">
        <div style="display: inline-block; vertical-align: top;">
            <div>
                <span class="invoice-to-text">Invoice to:</span><span class="client-name">{{ $client_name }}</span>
            </div>
            @if(isset($client_address) && $client_address)
            <div class="client-address">{{ $client_address }}</div>
            @endif
        </div>
    </div>

    <!-- Items Table with CSS Arrow -->
    <div class="items-section">
        <table class="items-table">
            <thead>
                <tr>
                    <th class="col-sl highlight">SL.</th>
                    <th class="col-item highlight item-head">Item Description</th>
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
                    <td class="col-price">{{ $currency }} {{ number_format($item['price'], 2) }}</td>
                    <td class="col-qty">{{ $item['quantity'] }}</td>
                    <td class="col-total">{{ $currency }} {{ number_format($item['subtotal'], 2) }}</td>
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

    <!-- Payment Info and Total with CSS Arrow -->
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
                        <p style="margin-top: 9px;">{{ $notes }}</p>
                        @endif
                    </div>
                </td>
                <td class="total-cell">
                    <div class="total-box">
                        Total:<span class="total-amount">
                            @if($final_total)
                                {{ $final_total }}
                            @else
                                {{ $currency }} {{ number_format($calculated_total, 2) }}
                            @endif
                        </span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Signature - RIGHT SIDE -->
    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line"></div>
            <div class="signature-text">Authorised Sign</div>
        </div>
    </div>

</div>

<!-- Footer with CSS Arrow -->
<div class="footer">
    <table class="footer-table">
        <tr>
            <td class="footer-left">
                Thank you for your business
            </td>
            <td class="footer-right">{{ $company_email ?? 'invoice@company.com' }}</td>
        </tr>
    </table>
</div>

</div>

</body>
</html>
