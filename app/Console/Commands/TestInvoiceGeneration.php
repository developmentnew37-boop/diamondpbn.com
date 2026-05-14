<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Barryvdh\DomPDF\Facade\Pdf;

class TestInvoiceGeneration extends Command
{
    protected $signature = 'invoice:test {--theme=} {--currency=USD} {--custom-total=}';
    protected $description = 'Generate a test invoice PDF to verify the template';

    public function handle()
    {
        $themeColor = $this->option('theme') ?? '#4f79bd';
        $currency = $this->option('currency') ?? 'USD';
        $customTotal = $this->option('custom-total');

        // Get currency symbol
        $currencySymbol = $this->getCurrencySymbol($currency);

        $data = [
            'invoice_number' => 'INV-20260513-001',
            'invoice_date' => '2026-05-13',
            'due_date' => '2026-06-13',
            'company_name' => 'ABDUL SERVICES',
            'company_address' => 'A/113 - 212 Jail Road, Hyderabad, Sindh',
            'company_email' => 'awaheedkhatri@gmail.com',
            'company_phone' => '+92 300 1234567',
            'client_name' => 'Jerricho Smith',
            'client_address' => '123 Business Street, New York, NY 10001',
            'client_email' => 'jerricho.smith@example.com',
            'client_phone' => '+1 555 123 4567',
            'items' => [
                [
                    'title' => '20 .EDU Traffic',
                    'price' => 7.00,
                    'quantity' => 20,
                    'subtotal' => 140.00,
                ],
                [
                    'title' => 'Premium SEO Package',
                    'price' => 250.00,
                    'quantity' => 1,
                    'subtotal' => 250.00,
                ],
                [
                    'title' => 'Content Writing Service',
                    'price' => 50.00,
                    'quantity' => 5,
                    'subtotal' => 250.00,
                ],
            ],
            'subtotal' => 640.00,
            'discount' => 40.00,
            'tax_rate' => 10,
            'tax_amount' => 60.00,
            'calculated_total' => 660.00,
            'final_total' => $customTotal,
            'currency' => $currency,
            'currency_symbol' => $currencySymbol,
            'notes' => 'Payment due within 30 days. Thank you for your business!',
            'theme_color' => $themeColor,
            'logo_base64' => null,
            'logo_mime' => null,
        ];

        try {
            $pdf = PDF::loadView('admin.invoice.pdf-template', $data);
            $pdf->setPaper('A4', 'portrait');

            $filename = 'test-invoice-' . $currency . '-' . date('YmdHis') . '.pdf';
            $path = storage_path('app/public/' . $filename);

            $pdf->save($path);

            $this->info('✓ Test invoice generated successfully!');
            $this->info('Location: ' . $path);
            $this->info('Currency: ' . $currency . ' (' . $currencySymbol . ')');
            $this->info('Theme Color: ' . $themeColor);
            if ($customTotal) {
                $this->info('Custom Total: ' . $customTotal);
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('✗ Failed to generate invoice: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function getCurrencySymbol($currencyCode)
    {
        $symbols = [
            'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'JPY' => '¥', 'CNY' => '¥',
            'AUD' => 'A$', 'CAD' => 'C$', 'CHF' => 'CHF', 'SEK' => 'kr', 'NOK' => 'kr',
            'DKK' => 'kr', 'NZD' => 'NZ$', 'INR' => '₹', 'IDR' => 'Rp', 'MYR' => 'RM',
            'SGD' => 'S$', 'PHP' => '₱', 'THB' => '฿', 'VND' => '₫', 'KRW' => '₩',
            'HKD' => 'HK$', 'TWD' => 'NT$', 'AED' => 'د.إ', 'SAR' => '﷼', 'QAR' => '﷼',
            'KWD' => 'د.ك', 'BHD' => 'د.ب', 'OMR' => '﷼', 'ILS' => '₪', 'TRY' => '₺',
            'ZAR' => 'R', 'NGN' => '₦', 'EGP' => '£', 'KES' => 'KSh', 'BRL' => 'R$',
            'MXN' => '$', 'ARS' => '$', 'CLP' => '$', 'COP' => '$', 'PEN' => 'S/',
            'RUB' => '₽', 'PLN' => 'zł', 'CZK' => 'Kč', 'HUF' => 'Ft', 'RON' => 'lei',
            'PKR' => '₨', 'BDT' => '৳', 'LKR' => 'Rs',
        ];

        return $symbols[$currencyCode] ?? '$';
    }
}
