@extends('admin.layout.layout')

@section('title', 'Invoice Generator')

@push('style')
    <style>
        .invoice-form-section {
            background: white;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--primary-color);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-label {
            font-size: 14px;
            font-weight: 500;
            color: #374151;
        }

        .form-input {
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
        }

        .logo-upload-area {
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            padding: 24px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .logo-upload-area:hover {
            border-color: var(--primary-color);
            background: #fff7ed;
        }

        .logo-preview {
            max-width: 200px;
            max-height: 100px;
            margin: 12px auto;
            display: none;
        }

        .logo-preview.active {
            display: block;
        }

        .products-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        .products-table th {
            background: var(--primary-color);
            color: white;
            padding: 12px;
            text-align: left;
            font-size: 14px;
            font-weight: 600;
        }

        .products-table td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
        }

        .products-table input {
            width: 100%;
            padding: 8px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 14px;
        }

        .btn-add-row {
            background: #10b981;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            margin-top: 12px;
            transition: background 0.2s;
        }

        .btn-add-row:hover {
            background: #059669;
        }

        .btn-remove-row {
            background: #ef4444;
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }

        .btn-remove-row:hover {
            background: #dc2626;
        }

        .totals-section {
            background: #f9fafb;
            padding: 20px;
            border-radius: 8px;
            margin-top: 24px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
        }

        .total-row.grand-total {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary-color);
            border-top: 2px solid #d1d5db;
            padding-top: 12px;
            margin-top: 8px;
        }

        .btn-generate {
            background: var(--primary-color);
            color: white;
            padding: 14px 32px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            margin-top: 24px;
            transition: all 0.2s;
        }

        .btn-generate:hover {
            background: #ea580c;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(249, 115, 22, 0.3);
        }

        .btn-generate:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
        }

        .theme-selector {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        .theme-option {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            cursor: pointer;
            position: relative;
            border: 3px solid transparent;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .theme-option:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .theme-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }

        .theme-option.active {
            border-color: #1f2937;
            box-shadow: 0 0 0 2px #fff, 0 0 0 4px #1f2937;
        }

        .theme-option .checkmark {
            color: white;
            font-size: 24px;
            font-weight: bold;
            opacity: 0;
            transition: opacity 0.2s;
        }

        .theme-option.active .checkmark {
            opacity: 1;
        }

        .custom-color-option {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
            overflow: hidden;
        }

        .custom-color-option input[type="color"] {
            position: absolute;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .custom-color-option .custom-label {
            color: white;
            font-size: 12px;
            font-weight: 600;
            pointer-events: none;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .products-table {
                font-size: 12px;
            }

            .products-table th,
            .products-table td {
                padding: 8px 4px;
            }

            .theme-option {
                width: 50px;
                height: 50px;
            }
        }
    </style>
@endpush

@section('main-content')
    <div class="page-header">
        <div class="w-full flex flex-wrap items-center">
            <div class="w-1/2 flex flex-col gap-2">
                <h2 class="page-title">Invoice Generator</h2>
                <div class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="#" class="breadcrumb-link">Invoice Generator</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <div class="!p-4 text-sm rounded bg-red-100 text-red-700 w-full !mb-4" role="alert">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.invoice.generate') }}" method="POST" enctype="multipart/form-data" id="invoice-form">
        @csrf

        <!-- Company Information -->
        <div class="invoice-form-section">
            <h3 class="section-title">Company Information</h3>

            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label">Company Logo (Optional)</label>
                <div class="logo-upload-area" id="logo-upload-area">
                    <input type="file" name="logo" id="logo-input" accept="image/*" style="display: none;">
                    <img src="" alt="Logo Preview" class="logo-preview" id="logo-preview">
                    <div id="upload-text">
                        <svg class="w-12 h-12 mx-auto mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        <p class="text-sm text-gray-600">Click to upload logo</p>
                        <p class="text-xs text-gray-400 mt-1">PNG, JPG, GIF up to 2MB</p>
                    </div>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Company Name <span class="text-red-500">*</span></label>
                    <input type="text" name="company_name" class="form-input" required value="{{ old('company_name') }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Company Email</label>
                    <input type="email" name="company_email" class="form-input" value="{{ old('company_email') }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Company Phone</label>
                    <input type="text" name="company_phone" class="form-input" value="{{ old('company_phone') }}">
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label class="form-label">Company Address</label>
                    <textarea name="company_address" class="form-input" rows="2">{{ old('company_address') }}</textarea>
                </div>
            </div>
        </div>

        <!-- Client Information -->
        <div class="invoice-form-section">
            <h3 class="section-title">Client Information</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Client Name <span class="text-red-500">*</span></label>
                    <input type="text" name="client_name" class="form-input" required value="{{ old('client_name') }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Client Email</label>
                    <input type="email" name="client_email" class="form-input" value="{{ old('client_email') }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Client Phone</label>
                    <input type="text" name="client_phone" class="form-input" value="{{ old('client_phone') }}">
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label class="form-label">Client Address</label>
                    <textarea name="client_address" class="form-input" rows="2">{{ old('client_address') }}</textarea>
                </div>
            </div>
        </div>

        <!-- Invoice Details -->
        <div class="invoice-form-section">
            <h3 class="section-title">Invoice Details</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Invoice Number <span class="text-red-500">*</span></label>
                    <input type="text" name="invoice_number" class="form-input" required value="{{ old('invoice_number', 'INV-' . date('Ymd') . '-001') }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Invoice Date <span class="text-red-500">*</span></label>
                    <input type="date" name="invoice_date" class="form-input" required value="{{ old('invoice_date', date('Y-m-d')) }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Due Date <span class="text-red-500">*</span></label>
                    <input type="date" name="due_date" class="form-input" required value="{{ old('due_date', date('Y-m-d', strtotime('+30 days'))) }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Currency <span class="text-red-500">*</span></label>
                    <select name="currency" id="currency-select" class="form-input" required>
                        <option value="USD" data-symbol="$" selected>USD - US Dollar ($)</option>
                        <option value="EUR" data-symbol="€">EUR - Euro (€)</option>
                        <option value="GBP" data-symbol="£">GBP - British Pound (£)</option>
                        <option value="JPY" data-symbol="¥">JPY - Japanese Yen (¥)</option>
                        <option value="CNY" data-symbol="¥">CNY - Chinese Yuan (¥)</option>
                        <option value="AUD" data-symbol="A$">AUD - Australian Dollar (A$)</option>
                        <option value="CAD" data-symbol="C$">CAD - Canadian Dollar (C$)</option>
                        <option value="CHF" data-symbol="CHF">CHF - Swiss Franc (CHF)</option>
                        <option value="SEK" data-symbol="kr">SEK - Swedish Krona (kr)</option>
                        <option value="NOK" data-symbol="kr">NOK - Norwegian Krone (kr)</option>
                        <option value="DKK" data-symbol="kr">DKK - Danish Krone (kr)</option>
                        <option value="NZD" data-symbol="NZ$">NZD - New Zealand Dollar (NZ$)</option>
                        <option value="INR" data-symbol="₹">INR - Indian Rupee (₹)</option>
                        <option value="IDR" data-symbol="Rp">IDR - Indonesian Rupiah (Rp)</option>
                        <option value="MYR" data-symbol="RM">MYR - Malaysian Ringgit (RM)</option>
                        <option value="SGD" data-symbol="S$">SGD - Singapore Dollar (S$)</option>
                        <option value="PHP" data-symbol="₱">PHP - Philippine Peso (₱)</option>
                        <option value="THB" data-symbol="฿">THB - Thai Baht (฿)</option>
                        <option value="VND" data-symbol="₫">VND - Vietnamese Dong (₫)</option>
                        <option value="KRW" data-symbol="₩">KRW - South Korean Won (₩)</option>
                        <option value="HKD" data-symbol="HK$">HKD - Hong Kong Dollar (HK$)</option>
                        <option value="TWD" data-symbol="NT$">TWD - Taiwan Dollar (NT$)</option>
                        <option value="AED" data-symbol="د.إ">AED - UAE Dirham (د.إ)</option>
                        <option value="SAR" data-symbol="﷼">SAR - Saudi Riyal (﷼)</option>
                        <option value="QAR" data-symbol="﷼">QAR - Qatari Riyal (﷼)</option>
                        <option value="KWD" data-symbol="د.ك">KWD - Kuwaiti Dinar (د.ك)</option>
                        <option value="BHD" data-symbol="د.ب">BHD - Bahraini Dinar (د.ب)</option>
                        <option value="OMR" data-symbol="﷼">OMR - Omani Rial (﷼)</option>
                        <option value="ILS" data-symbol="₪">ILS - Israeli Shekel (₪)</option>
                        <option value="TRY" data-symbol="₺">TRY - Turkish Lira (₺)</option>
                        <option value="ZAR" data-symbol="R">ZAR - South African Rand (R)</option>
                        <option value="NGN" data-symbol="₦">NGN - Nigerian Naira (₦)</option>
                        <option value="EGP" data-symbol="£">EGP - Egyptian Pound (£)</option>
                        <option value="KES" data-symbol="KSh">KES - Kenyan Shilling (KSh)</option>
                        <option value="BRL" data-symbol="R$">BRL - Brazilian Real (R$)</option>
                        <option value="MXN" data-symbol="$">MXN - Mexican Peso ($)</option>
                        <option value="ARS" data-symbol="$">ARS - Argentine Peso ($)</option>
                        <option value="CLP" data-symbol="$">CLP - Chilean Peso ($)</option>
                        <option value="COP" data-symbol="$">COP - Colombian Peso ($)</option>
                        <option value="PEN" data-symbol="S/">PEN - Peruvian Sol (S/)</option>
                        <option value="RUB" data-symbol="₽">RUB - Russian Ruble (₽)</option>
                        <option value="PLN" data-symbol="zł">PLN - Polish Zloty (zł)</option>
                        <option value="CZK" data-symbol="Kč">CZK - Czech Koruna (Kč)</option>
                        <option value="HUF" data-symbol="Ft">HUF - Hungarian Forint (Ft)</option>
                        <option value="RON" data-symbol="lei">RON - Romanian Leu (lei)</option>
                        <option value="PKR" data-symbol="₨">PKR - Pakistani Rupee (₨)</option>
                        <option value="BDT" data-symbol="৳">BDT - Bangladeshi Taka (৳)</option>
                        <option value="LKR" data-symbol="Rs">LKR - Sri Lankan Rupee (Rs)</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Theme Customization -->
        <div class="invoice-form-section">
            <h3 class="section-title">Theme Customization</h3>
            <div class="form-group">
                <label class="form-label">Select Theme Color</label>
                <div class="theme-selector">
                    <div class="theme-option" data-color="#4f79bd" style="background: #4f79bd;" title="Classic Blue">
                        <input type="radio" name="theme_color" value="#4f79bd" checked>
                        <span class="checkmark">✓</span>
                    </div>
                    <div class="theme-option" data-color="#10b981" style="background: #10b981;" title="Emerald Green">
                        <input type="radio" name="theme_color" value="#10b981">
                        <span class="checkmark">✓</span>
                    </div>
                    <div class="theme-option" data-color="#f97316" style="background: #f97316;" title="Orange">
                        <input type="radio" name="theme_color" value="#f97316">
                        <span class="checkmark">✓</span>
                    </div>
                    <div class="theme-option" data-color="#8b5cf6" style="background: #8b5cf6;" title="Purple">
                        <input type="radio" name="theme_color" value="#8b5cf6">
                        <span class="checkmark">✓</span>
                    </div>
                    <div class="theme-option" data-color="#ef4444" style="background: #ef4444;" title="Red">
                        <input type="radio" name="theme_color" value="#ef4444">
                        <span class="checkmark">✓</span>
                    </div>
                    <div class="theme-option" data-color="#06b6d4" style="background: #06b6d4;" title="Cyan">
                        <input type="radio" name="theme_color" value="#06b6d4">
                        <span class="checkmark">✓</span>
                    </div>
                    <div class="theme-option" data-color="#ec4899" style="background: #ec4899;" title="Pink">
                        <input type="radio" name="theme_color" value="#ec4899">
                        <span class="checkmark">✓</span>
                    </div>
                    <div class="theme-option" data-color="#14b8a6" style="background: #14b8a6;" title="Teal">
                        <input type="radio" name="theme_color" value="#14b8a6">
                        <span class="checkmark">✓</span>
                    </div>
                    <div class="theme-option custom-color-option" title="Custom Color">
                        <input type="color" id="custom-color-picker" value="#4f79bd">
                        <span class="custom-label">Custom</span>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-2">Choose a theme color for your invoice or select a custom color</p>
            </div>
        </div>

        <!-- Products/Services -->
        <div class="invoice-form-section">
            <h3 class="section-title">Products / Services</h3>
            <div style="overflow-x: auto;">
                <table class="products-table">
                    <thead>
                        <tr>
                            <th style="width: 40%;">Item Description</th>
                            <th style="width: 15%;">Price</th>
                            <th style="width: 15%;">Quantity</th>
                            <th style="width: 20%;">Subtotal</th>
                            <th style="width: 10%;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="products-tbody">
                        <!-- Initial row will be added by JavaScript -->
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn-add-row" id="add-product-btn">
                <span>+ Add Item</span>
            </button>
        </div>

        <!-- Totals and Additional Info -->
        <div class="invoice-form-section">
            <div class="form-grid" style="margin-bottom: 20px;">
                <div class="form-group">
                    <label class="form-label">Tax Rate (%)</label>
                    <input type="number" name="tax_rate" id="tax-rate" class="form-input" min="0" max="100" step="0.01" value="{{ old('tax_rate', 0) }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Discount ($)</label>
                    <input type="number" name="discount" id="discount" class="form-input" min="0" step="0.01" value="{{ old('discount', 0) }}">
                </div>
            </div>

            <div class="totals-section">
                <div class="total-row">
                    <span>Subtotal:</span>
                    <span id="subtotal-display">$0.00</span>
                </div>
                <div class="total-row">
                    <span>Discount:</span>
                    <span id="discount-display">$0.00</span>
                </div>
                <div class="total-row">
                    <span>Tax:</span>
                    <span id="tax-display">$0.00</span>
                </div>
                <div class="total-row grand-total">
                    <span>Calculated Total:</span>
                    <span id="total-display">$0.00</span>
                </div>
            </div>

            <div class="form-group" style="margin-top: 20px;">
                <label class="form-label">
                    Custom Total Override (Optional)
                    <span class="text-xs text-gray-500 font-normal ml-2">- Leave empty to use calculated total</span>
                </label>
                <input type="text" name="custom_total" id="custom-total" class="form-input" placeholder="e.g., Rp 1,560,000 or $100.00">
                <p class="text-xs text-gray-500 mt-1">
                    Enter the total with currency symbol (e.g., "Rp 1,560,000" or "$100.00").
                    This will replace the calculated total in the PDF exactly as you type it.
                </p>
            </div>

            <div class="form-group" style="margin-top: 20px;">
                <label class="form-label">Notes / Terms</label>
                <textarea name="notes" class="form-input" rows="4" placeholder="Payment terms, thank you note, or any additional information...">{{ old('notes') }}</textarea>
            </div>
        </div>

        <button type="submit" class="btn-generate" id="generate-btn">
            <span id="btn-text">Generate PDF Invoice</span>
            <span id="btn-loader" style="display: none;">Generating...</span>
        </button>
    </form>
@endsection

@push('scripts')
    <script src="{{ asset('js/invoice-generator.js') }}"></script>
@endpush
