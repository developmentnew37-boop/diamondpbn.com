<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Shortens keyword/URL strings in admin report tables; full value is intended for title/tooltip.
 */
final class ReportDisplay
{
    public const KEYWORD_LIMIT = 42;

    public const URL_LIMIT = 72;

    /**
     * Turn stored DB values (plain text or JSON arrays) into human-readable text.
     */
    public static function normalizeStored(?string $text): string
    {
        if ($text === null) {
            return '';
        }

        $raw = trim($text);
        if ($raw === '' || $raw === '-') {
            return '';
        }

        if (str_starts_with($raw, '[') || str_starts_with($raw, '{') || str_starts_with($raw, '"')) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                if (is_array($decoded)) {
                    $values = self::flattenScalarList($decoded);
                    if ($values !== []) {
                        return implode(', ', $values);
                    }
                } elseif (is_string($decoded)) {
                    return trim($decoded);
                }
            }
        }

        return $raw;
    }

    /**
     * Plain display string for exports and tables.
     */
    public static function plain(?string $text, string $empty = '-'): string
    {
        $raw = self::normalizeStored($text);

        return $raw === '' ? $empty : $raw;
    }

    /**
     * @return array{display: string, title: string}
     */
    public static function keyword(?string $text): array
    {
        $raw = self::normalizeStored($text);
        if ($raw === '') {
            return ['display' => '-', 'title' => ''];
        }

        return [
            'display' => Str::limit($raw, self::KEYWORD_LIMIT),
            'title' => $raw,
        ];
    }

    /**
     * @return array{display: string, title: string}
     */
    public static function url(?string $text): array
    {
        $raw = self::normalizeStored($text);
        if ($raw === '') {
            return ['display' => '-', 'title' => ''];
        }

        return [
            'display' => Str::limit($raw, self::URL_LIMIT),
            'title' => $raw,
        ];
    }

    /**
     * Remote / blog post URL shown in a link cell (href stays full).
     *
     * @return array{display: string, title: string, href: string}
     */
    public static function externalLink(?string $url): array
    {
        $raw = self::normalizeStored($url);
        if ($raw === '') {
            return ['display' => '-', 'title' => '', 'href' => ''];
        }

        return [
            'display' => Str::limit($raw, self::URL_LIMIT),
            'title' => $raw,
            'href' => $raw,
        ];
    }

    /**
     * @param  array<mixed>  $values
     * @return array<int, string>
     */
    private static function flattenScalarList(array $values): array
    {
        $out = [];

        foreach ($values as $value) {
            if (is_array($value)) {
                $out = array_merge($out, self::flattenScalarList($value));

                continue;
            }

            if (is_scalar($value)) {
                $piece = trim((string) $value);
                if ($piece !== '') {
                    $out[] = $piece;
                }
            }
        }

        return $out;
    }
}
