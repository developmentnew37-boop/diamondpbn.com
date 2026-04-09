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
     * @return array{display: string, title: string}
     */
    public static function keyword(?string $text): array
    {
        $raw = $text === null ? '' : trim($text);
        if ($raw === '') {
            return ['display' => '-', 'title' => ''];
        }

        return [
            'display' => Str::limit($raw, self::KEYWORD_LIMIT),
            'title'   => $raw,
        ];
    }

    /**
     * @return array{display: string, title: string}
     */
    public static function url(?string $text): array
    {
        $raw = $text === null ? '' : trim($text);
        if ($raw === '' || $raw === '-') {
            return ['display' => '-', 'title' => ''];
        }

        return [
            'display' => Str::limit($raw, self::URL_LIMIT),
            'title'   => $raw,
        ];
    }

    /**
     * Remote / blog post URL shown in a link cell (href stays full).
     *
     * @return array{display: string, title: string, href: string}
     */
    public static function externalLink(?string $url): array
    {
        $raw = $url === null ? '' : trim($url);
        if ($raw === '') {
            return ['display' => '-', 'title' => '', 'href' => ''];
        }

        return [
            'display' => Str::limit($raw, self::URL_LIMIT),
            'title'   => $raw,
            'href'    => $raw,
        ];
    }
}
