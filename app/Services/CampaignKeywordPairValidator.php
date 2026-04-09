<?php

namespace App\Services;

/**
 * Validates keyword/URL rows on campaign edit (bulk + single post).
 */
class CampaignKeywordPairValidator
{
    /**
     * @param  list<array{0?: string, 1?: string}>  $newPairs
     * @return string|null Error message, or null if valid.
     */
    public static function validateEditPairs(array $newPairs): ?string
    {
        foreach ($newPairs as $p) {
            $kw  = trim((string) ($p[0] ?? ''));
            $url = trim((string) ($p[1] ?? ''));
            if (($kw === '') !== ($url === '')) {
                return 'Each row must have both keyword and URL, or leave both empty.';
            }
        }

        $complete = array_values(array_filter(
            $newPairs,
            fn ($p) => trim((string) ($p[0] ?? '')) !== '' && trim((string) ($p[1] ?? '')) !== ''
        ));

        if (count($complete) === 0) {
            return 'At least one keyword and URL pair is required.';
        }

        return null;
    }
}
