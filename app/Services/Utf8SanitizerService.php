<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * UTF-8 Sanitization Service
 *
 * Handles multilingual content (Chinese, Thai, Persian, Arabic, emoji, etc.)
 * Removes malformed UTF-8 bytes and invalid control characters
 * Prevents json_encode failures in WordPress API posting
 */
class Utf8SanitizerService
{
    /**
     * Clean text to valid UTF-8, removing malformed bytes and invalid control characters
     * CRITICAL: Preserves valid UTF-8 multilingual content (Chinese, Thai, Arabic, Persian, emoji)
     *
     * @param string|null $text
     * @param array $options
     * @return string|null
     */
    public static function clean(?string $text, array $options = []): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        $original = $text;
        $originalLength = mb_strlen($text, '8bit');
        $wasModified = false;

        // Step 1: Check if text is already valid UTF-8
        $isValidUtf8 = mb_check_encoding($text, 'UTF-8');

        if ($isValidUtf8) {
            // Text is already valid UTF-8 - DO NOT convert encoding
            // Only remove dangerous control characters and zero-width spaces

            // Remove control characters (except newlines, tabs, carriage returns)
            // Keep: \n (0x0A), \r (0x0D), \t (0x09)
            // Remove: 0x00-0x08, 0x0B-0x0C, 0x0E-0x1F, 0x7F
            $cleaned = preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/u', '', $text);

            // Remove zero-width characters (optional, usually safe to remove)
            $cleaned = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $cleaned);

            // Normalize Unicode (NFC form - canonical composition)
            if (class_exists('Normalizer') && \Normalizer::isNormalized($cleaned, \Normalizer::FORM_C) === false) {
                $cleaned = \Normalizer::normalize($cleaned, \Normalizer::FORM_C);
            }

            $wasModified = ($cleaned !== $text);
            $text = $cleaned;

        } else {
            // Text is NOT valid UTF-8 - attempt repair

            // First, check if this is mojibake (double-encoded UTF-8)
            $repaired = self::repairMojibake($text);
            if ($repaired !== null && mb_check_encoding($repaired, 'UTF-8')) {
                $text = $repaired;
                $wasModified = true;
            } else {
                // Try to detect and convert encoding
                $detected = mb_detect_encoding($text, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'GB2312', 'GBK', 'Big5', 'Shift_JIS', 'EUC-KR'], true);

                if ($detected && $detected !== 'UTF-8') {
                    $text = mb_convert_encoding($text, 'UTF-8', $detected);
                    $wasModified = true;
                } else {
                    // Use iconv to strip invalid sequences
                    $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
                    if ($converted !== false) {
                        $text = $converted;
                        $wasModified = true;
                    }
                }

                // Remove control characters
                $text = preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/', '', $text);

                // Remove zero-width characters (but preserve ZWNJ/ZWJ for Persian/Arabic)
                // U+200B = Zero Width Space (removed)
                // U+200C = Zero Width Non-Joiner (PRESERVED for Persian/Arabic)
                // U+200D = Zero Width Joiner (PRESERVED for Persian/Arabic)
                // U+FEFF = BOM (removed)
                $text = preg_replace('/[\x{200B}\x{FEFF}]/u', '', $text);

                // Normalize Unicode
                if (class_exists('Normalizer')) {
                    $text = \Normalizer::normalize($text, \Normalizer::FORM_C);
                }
            }
        }

        // Final validation - ensure result is valid UTF-8
        if (!mb_check_encoding($text, 'UTF-8')) {
            // Last resort: try one more repair attempt
            $lastAttempt = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
            if ($lastAttempt !== false && mb_check_encoding($lastAttempt, 'UTF-8')) {
                $text = $lastAttempt;
            } else {
                // If still invalid, log error and return original
                Log::error('UTF-8 sanitization failed - returning original text', [
                    'context' => $options['context'] ?? 'unknown',
                    'article_id' => $options['article_id'] ?? null,
                    'preview' => mb_substr($original, 0, 100, '8bit'),
                ]);
                return $original;
            }
        }

        // Log if significant changes were made
        $newLength = mb_strlen($text, '8bit');
        if ($wasModified && $originalLength !== $newLength && ($options['log'] ?? true)) {
            $context = $options['context'] ?? 'unknown';
            $bytesRemoved = $originalLength - $newLength;

            Log::warning('UTF-8 sanitization removed malformed bytes', [
                'context' => $context,
                'bytes_removed' => $bytesRemoved,
                'original_length' => $originalLength,
                'cleaned_length' => $newLength,
                'preview_original' => mb_substr($original, 0, 100, '8bit'),
                'preview_cleaned' => mb_substr($text, 0, 100),
                'article_id' => $options['article_id'] ?? null,
                'language' => $options['language'] ?? null,
                'field' => $options['field'] ?? null,
            ]);
        }

        return $text;
    }

    /**
     * Attempt to repair mojibake (double-encoded UTF-8)
     *
     * Mojibake occurs when UTF-8 text is incorrectly interpreted as ISO-8859-1/Windows-1252
     * and then converted to UTF-8 again, causing patterns like "æ", "ä¸", "ç", "å"
     *
     * @param string $text
     * @return string|null Returns repaired text or null if not mojibake
     */
    private static function repairMojibake(string $text): ?string
    {
        // Check for common mojibake patterns (Chinese, Japanese, Korean)
        // These byte sequences appear when UTF-8 is misinterpreted as ISO-8859-1
        $mojibakePatterns = [
            '/[æçèéêëìíîï]/',  // Common in Chinese mojibake
            '/[ä¸å]/',           // Very common in Chinese mojibake
            '/[ã€ã€‚ã€ï¼]/',    // Chinese/Japanese punctuation mojibake
        ];

        $hasMojibake = false;
        foreach ($mojibakePatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                $hasMojibake = true;
                break;
            }
        }

        if (!$hasMojibake) {
            return null;
        }

        // Attempt to repair by treating the text as ISO-8859-1 and converting to UTF-8
        $repaired = @mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');

        if ($repaired === false) {
            return null;
        }

        // Verify the repair looks like valid Chinese/Japanese/Korean
        // Check for common CJK Unicode ranges
        if (preg_match('/[\x{4E00}-\x{9FFF}\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{AC00}-\x{D7AF}]/u', $repaired)) {
            return $repaired;
        }

        return null;
    }

    /**
     * Clean array of strings recursively
     *
     * @param array $data
     * @param array $options
     * @return array
     */
    public static function cleanArray(array $data, array $options = []): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = self::clean($value, $options);
            } elseif (is_array($value)) {
                $data[$key] = self::cleanArray($value, $options);
            }
        }

        return $data;
    }

    /**
     * Safe JSON encode with UTF-8 handling
     *
     * @param mixed $data
     * @param int $flags
     * @param int $depth
     * @return string|false
     */
    public static function jsonEncode($data, int $flags = 0, int $depth = 512)
    {
        // Add UTF-8 safe flags
        $flags |= JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;

        $json = json_encode($data, $flags, $depth);

        if ($json === false) {
            $error = json_last_error_msg();
            Log::error('JSON encode failed after UTF-8 sanitization', [
                'error' => $error,
                'data_type' => gettype($data),
                'data_preview' => is_string($data) ? mb_substr($data, 0, 200) : null,
            ]);
        }

        return $json;
    }

    /**
     * Validate if text is valid UTF-8
     *
     * @param string|null $text
     * @return bool
     */
    public static function isValidUtf8(?string $text): bool
    {
        if ($text === null || $text === '') {
            return true;
        }

        return mb_check_encoding($text, 'UTF-8');
    }

    /**
     * Check if mbstring extension is available
     *
     * @return bool
     */
    public static function hasMbstringSupport(): bool
    {
        return extension_loaded('mbstring');
    }

    /**
     * Check if iconv extension is available
     *
     * @return bool
     */
    public static function hasIconvSupport(): bool
    {
        return extension_loaded('iconv');
    }

    /**
     * Get system UTF-8 support status
     *
     * @return array
     */
    public static function getSystemStatus(): array
    {
        return [
            'mbstring' => self::hasMbstringSupport(),
            'iconv' => self::hasIconvSupport(),
            'intl' => extension_loaded('intl'),
            'normalizer' => class_exists('Normalizer'),
        ];
    }
}
