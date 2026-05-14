<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceController extends Controller
{
    /**
     * Display the invoice generator form
     */
    public function create()
    {
        return view('admin.invoice.create');
    }

    /**
     * Generate PDF from invoice data
     */
    public function generatePdf(Request $request)
    {
        // Validate input
        $validated = $request->validate([
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'invoice_number' => 'required|string|max:255',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date',
            'company_name' => 'required|string|max:255',
            'company_address' => 'nullable|string|max:500',
            'company_email' => 'nullable|email|max:255',
            'company_phone' => 'nullable|string|max:50',
            'client_name' => 'required|string|max:255',
            'client_address' => 'nullable|string|max:500',
            'client_email' => 'nullable|email|max:255',
            'client_phone' => 'nullable|string|max:50',
            'items' => 'required|array|min:1',
            'items.*.title' => 'required|string|max:255',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.quantity' => 'required|integer|min:1',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'discount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'theme_color' => 'nullable|string|max:7',
            'currency' => 'required|string|max:10',
            'custom_total' => 'nullable|string|max:100',
        ]);

        // Handle logo upload (convert to base64 for PDF embedding)
        $logoBase64 = null;
        $logoMime = null;

        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->getRealPath();
            $logoBase64 = base64_encode(file_get_contents($logoPath));
            $logoMime = $request->file('logo')->getMimeType();
        }

        // Reindex items array to start from 0 (fixes S.No bug)
        $validated['items'] = array_values($validated['items']);

        // Calculate totals
        $subtotal = 0;
        foreach ($validated['items'] as &$item) {
            $item['subtotal'] = $item['price'] * $item['quantity'];
            $subtotal += $item['subtotal'];
        }

        // Apply discount
        $discount = $validated['discount'] ?? 0;
        $subtotalAfterDiscount = $subtotal - $discount;

        // Calculate tax
        $taxRate = $validated['tax_rate'] ?? 0;
        $taxAmount = ($subtotalAfterDiscount * $taxRate) / 100;

        // Calculate total
        $total = $subtotalAfterDiscount + $taxAmount;

        // Use custom total if provided (as-is with user's formatting), otherwise use calculated total
        $finalTotal = !empty($validated['custom_total']) ? $validated['custom_total'] : null;
        $calculatedTotal = $total;

        // Get currency symbol
        $currencySymbol = $this->getCurrencySymbol($validated['currency']);

        // Get theme color or use default
        $themeColor = $validated['theme_color'] ?? '#4f79bd';

        // Prepare data for PDF
        $data = array_merge($validated, [
            'logo_base64' => $logoBase64,
            'logo_mime' => $logoMime,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'subtotal_after_discount' => $subtotalAfterDiscount,
            'tax_amount' => $taxAmount,
            'calculated_total' => $calculatedTotal,
            'final_total' => $finalTotal,
            'currency_symbol' => $currencySymbol,
            'theme_color' => $themeColor,
        ]);

        // Generate PDF
        $pdf = PDF::loadView('admin.invoice.pdf-template', $data);

        // Set paper size and orientation
        $pdf->setPaper('A4', 'portrait');

        // Return PDF as base64 for AJAX download
        $pdfContent = $pdf->output();
        $pdfBase64 = base64_encode($pdfContent);
        $filename = 'invoice-' . $validated['invoice_number'] . '.pdf';

        return response()->json([
            'success' => true,
            'pdf' => $pdfBase64,
            'filename' => $filename,
            'message' => 'Invoice generated successfully!'
        ]);
    }

    /**
     * Get currency symbol from currency code
     */
    private function getCurrencySymbol($currencyCode)
    {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'CNY' => '¥',
            'AUD' => 'A$',
            'CAD' => 'C$',
            'CHF' => 'CHF',
            'SEK' => 'kr',
            'NOK' => 'kr',
            'DKK' => 'kr',
            'NZD' => 'NZ$',
            'INR' => '₹',
            'IDR' => 'Rp',
            'MYR' => 'RM',
            'SGD' => 'S$',
            'PHP' => '₱',
            'THB' => '฿',
            'VND' => '₫',
            'KRW' => '₩',
            'HKD' => 'HK$',
            'TWD' => 'NT$',
            'AED' => 'د.إ',
            'SAR' => '﷼',
            'QAR' => '﷼',
            'KWD' => 'د.ك',
            'BHD' => 'د.ب',
            'OMR' => '﷼',
            'ILS' => '₪',
            'TRY' => '₺',
            'ZAR' => 'R',
            'NGN' => '₦',
            'EGP' => '£',
            'KES' => 'KSh',
            'BRL' => 'R$',
            'MXN' => '$',
            'ARS' => '$',
            'CLP' => '$',
            'COP' => '$',
            'PEN' => 'S/',
            'RUB' => '₽',
            'PLN' => 'zł',
            'CZK' => 'Kč',
            'HUF' => 'Ft',
            'RON' => 'lei',
            'PKR' => '₨',
            'BDT' => '৳',
            'LKR' => 'Rs',
        ];

        return $symbols[$currencyCode] ?? '$';
    }
}
