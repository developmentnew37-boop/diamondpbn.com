<?php

namespace App\Support;

class CurrencyFormatter
{
    /** @var array<string, string> */
    private const SYMBOLS = [
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

    /** @var array<string, string> */
    private const NAMES = [
        'USD' => 'US Dollar',
        'EUR' => 'Euro',
        'GBP' => 'British Pound',
        'JPY' => 'Japanese Yen',
        'CNY' => 'Chinese Yuan',
        'AUD' => 'Australian Dollar',
        'CAD' => 'Canadian Dollar',
        'CHF' => 'Swiss Franc',
        'SEK' => 'Swedish Krona',
        'NOK' => 'Norwegian Krone',
        'DKK' => 'Danish Krone',
        'NZD' => 'New Zealand Dollar',
        'INR' => 'Indian Rupee',
        'IDR' => 'Indonesian Rupiah',
        'MYR' => 'Malaysian Ringgit',
        'SGD' => 'Singapore Dollar',
        'PHP' => 'Philippine Peso',
        'THB' => 'Thai Baht',
        'VND' => 'Vietnamese Dong',
        'KRW' => 'South Korean Won',
        'HKD' => 'Hong Kong Dollar',
        'TWD' => 'Taiwan Dollar',
        'AED' => 'UAE Dirham',
        'SAR' => 'Saudi Riyal',
        'QAR' => 'Qatari Riyal',
        'KWD' => 'Kuwaiti Dinar',
        'BHD' => 'Bahraini Dinar',
        'OMR' => 'Omani Rial',
        'ILS' => 'Israeli Shekel',
        'TRY' => 'Turkish Lira',
        'ZAR' => 'South African Rand',
        'NGN' => 'Nigerian Naira',
        'EGP' => 'Egyptian Pound',
        'KES' => 'Kenyan Shilling',
        'BRL' => 'Brazilian Real',
        'MXN' => 'Mexican Peso',
        'ARS' => 'Argentine Peso',
        'CLP' => 'Chilean Peso',
        'COP' => 'Colombian Peso',
        'PEN' => 'Peruvian Sol',
        'RUB' => 'Russian Ruble',
        'PLN' => 'Polish Zloty',
        'CZK' => 'Czech Koruna',
        'HUF' => 'Hungarian Forint',
        'RON' => 'Romanian Leu',
        'PKR' => 'Pakistani Rupee',
        'BDT' => 'Bangladeshi Taka',
        'LKR' => 'Sri Lankan Rupee',
    ];

    /** @var list<string> */
    private const ZERO_DECIMAL = ['IDR', 'VND', 'JPY', 'KRW'];

    /** @return list<string> */
    public static function supportedCodes(): array
    {
        return array_keys(self::SYMBOLS);
    }

    public static function symbol(string $currencyCode): string
    {
        $code = strtoupper($currencyCode);

        return self::SYMBOLS[$code] ?? $code.' ';
    }

    public static function name(string $currencyCode): string
    {
        $code = strtoupper($currencyCode);

        return self::NAMES[$code] ?? $code;
    }

    public static function optionLabel(string $currencyCode): string
    {
        $code = strtoupper($currencyCode);

        return sprintf('%s - %s (%s)', $code, self::name($code), self::symbol($code));
    }

    public static function decimals(string $currencyCode): int
    {
        return in_array(strtoupper($currencyCode), self::ZERO_DECIMAL, true) ? 0 : 2;
    }

    public static function format(float|string|null $amount, string $currencyCode, bool $withSymbol = true): string
    {
        if ($amount === null || $amount === '') {
            return $withSymbol ? self::symbol($currencyCode).'0' : '0';
        }

        $decimals = self::decimals($currencyCode);
        $formatted = number_format((float) $amount, $decimals, '.', ',');

        return $withSymbol ? self::symbol($currencyCode).$formatted : $formatted;
    }
}
