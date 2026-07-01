<?php

use App\Services\Utf8SanitizerService;

if (! function_exists('cleanUtf8')) {
    /**
     * Clean text to valid UTF-8, removing malformed bytes
     */
    function cleanUtf8(?string $text, array $options = []): ?string
    {
        return Utf8SanitizerService::clean($text, $options);
    }
}

if (! function_exists('safeJsonEncode')) {
    /**
     * Safe JSON encode with UTF-8 handling
     *
     * @param  mixed  $data
     * @return string|false
     */
    function safeJsonEncode($data, int $flags = 0, int $depth = 512)
    {
        return Utf8SanitizerService::jsonEncode($data, $flags, $depth);
    }
}

if (! function_exists('isValidUtf8')) {
    /**
     * Check if text is valid UTF-8
     */
    function isValidUtf8(?string $text): bool
    {
        return Utf8SanitizerService::isValidUtf8($text);
    }
}

if (! function_exists('isRtlText')) {
    /**
     * Detect if text contains primarily RTL characters (Arabic, Persian, Hebrew, Urdu)
     */
    function isRtlText(?string $text): bool
    {
        if (empty($text)) {
            return false;
        }

        // RTL Unicode ranges:
        // Arabic: U+0600-U+06FF, U+0750-U+077F, U+08A0-U+08FF
        // Hebrew: U+0590-U+05FF
        // Persian uses Arabic range
        // Urdu uses Arabic range
        $rtlPattern = '/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{0590}-\x{05FF}]/u';

        preg_match_all($rtlPattern, $text, $rtlMatches);
        $rtlCount = count($rtlMatches[0] ?? []);

        // If more than 30% of characters are RTL, consider it RTL text
        $totalChars = mb_strlen(preg_replace('/\s+/', '', $text));

        return $totalChars > 0 && ($rtlCount / $totalChars) > 0.3;
    }
}

if (! function_exists('wrapRtlContent')) {
    /**
     * Wrap HTML content with RTL direction attribute if needed
     *
     * @param  string|null  $title  Optional title to check for RTL
     */
    function wrapRtlContent(string $html, ?string $title = null): string
    {
        // Check title first (more reliable), then content
        $textToCheck = $title ?? strip_tags($html);

        if (isRtlText($textToCheck)) {
            // Wrap content in RTL div
            return '<div dir="rtl" style="text-align: right;">'.$html.'</div>';
        }

        return $html;
    }
}

if (! function_exists('normalizeDomainName')) {
    /**
     * Normalize a domain URL or hostname to bare domain.com format.
     */
    function normalizeDomainName(?string $input): string
    {
        if ($input === null || $input === '') {
            return '';
        }

        $domain = strtolower(trim($input));
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = preg_replace('#/.*$#', '', $domain);
        $domain = preg_replace('#:\d+$#', '', $domain);

        return rtrim($domain, '/');
    }
}

if (! function_exists('toDomainUrl')) {
    /**
     * Build a full https URL from a domain URL or bare hostname.
     */
    function toDomainUrl(?string $input): string
    {
        $normalized = normalizeDomainName($input);

        return $normalized === '' ? '' : 'https://'.$normalized;
    }
}

if (! function_exists('extractDomainExtension')) {
    /**
     * Extract TLD/extension from a domain name (e.g. example.com -> com).
     */
    function extractDomainExtension(?string $domainName): string
    {
        $normalized = normalizeDomainName($domainName);
        if ($normalized === '') {
            return '';
        }

        $parts = explode('.', $normalized);

        return count($parts) >= 2 ? (string) end($parts) : '';
    }
}

if (! function_exists('parseIniSizeBytes')) {
    /**
     * Parse PHP ini size values (e.g. 8M, 512K) into bytes.
     */
    function parseIniSizeBytes(string|false|null $value): int
    {
        if ($value === false || $value === null || $value === '') {
            return PHP_INT_MAX;
        }

        $value = trim(strtolower((string) $value));
        $unit = substr($value, -1);
        $number = (float) $value;

        return (int) match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }
}

if (! function_exists('pluginManagerUploadLimits')) {
    /**
     * Effective plugin ZIP upload limit (app config vs PHP ini).
     *
     * @return array{
     *     max_zip_mb: int,
     *     php_upload_mb: float,
     *     php_post_mb: float,
     *     effective_bytes: int,
     *     effective_mb: float,
     *     validation_kb: int,
     *     server_ready: bool
     * }
     */
    function pluginManagerUploadLimits(): array
    {
        $maxZipMb = max(1, (int) config('plugin_manager.max_zip_mb', 10));
        $configBytes = $maxZipMb * 1024 * 1024;
        $uploadBytes = parseIniSizeBytes(ini_get('upload_max_filesize'));
        $postBytes = parseIniSizeBytes(ini_get('post_max_size'));
        $effectiveBytes = (int) min($configBytes, $uploadBytes, $postBytes);

        return [
            'max_zip_mb' => $maxZipMb,
            'php_upload_mb' => round($uploadBytes / 1024 / 1024, 2),
            'php_post_mb' => round($postBytes / 1024 / 1024, 2),
            'effective_bytes' => $effectiveBytes,
            'effective_mb' => round($effectiveBytes / 1024 / 1024, 2),
            'validation_kb' => max(1, (int) floor($effectiveBytes / 1024)),
            'server_ready' => $effectiveBytes >= $configBytes,
        ];
    }
}
