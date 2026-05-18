<?php

use App\Services\Utf8SanitizerService;

if (!function_exists('cleanUtf8')) {
    /**
     * Clean text to valid UTF-8, removing malformed bytes
     *
     * @param string|null $text
     * @param array $options
     * @return string|null
     */
    function cleanUtf8(?string $text, array $options = []): ?string
    {
        return Utf8SanitizerService::clean($text, $options);
    }
}

if (!function_exists('safeJsonEncode')) {
    /**
     * Safe JSON encode with UTF-8 handling
     *
     * @param mixed $data
     * @param int $flags
     * @param int $depth
     * @return string|false
     */
    function safeJsonEncode($data, int $flags = 0, int $depth = 512)
    {
        return Utf8SanitizerService::jsonEncode($data, $flags, $depth);
    }
}

if (!function_exists('isValidUtf8')) {
    /**
     * Check if text is valid UTF-8
     *
     * @param string|null $text
     * @return bool
     */
    function isValidUtf8(?string $text): bool
    {
        return Utf8SanitizerService::isValidUtf8($text);
    }
}

if (!function_exists('isRtlText')) {
    /**
     * Detect if text contains primarily RTL characters (Arabic, Persian, Hebrew, Urdu)
     *
     * @param string|null $text
     * @return bool
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

if (!function_exists('wrapRtlContent')) {
    /**
     * Wrap HTML content with RTL direction attribute if needed
     *
     * @param string $html
     * @param string|null $title Optional title to check for RTL
     * @return string
     */
    function wrapRtlContent(string $html, ?string $title = null): string
    {
        // Check title first (more reliable), then content
        $textToCheck = $title ?? strip_tags($html);

        if (isRtlText($textToCheck)) {
            // Wrap content in RTL div
            return '<div dir="rtl" style="text-align: right;">' . $html . '</div>';
        }

        return $html;
    }
}
