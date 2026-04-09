<?php

namespace App\Services;

/**
 * Builds "multi-level" editor boxes from ordered campaign article rows (group consecutive identical payloads).
 *
 * @phpstan-type Box array{quantity: int, media: ?string, nofollow: bool, rows: array<int, array{url: string, keyword: string}>}
 */
final class EditCampaignMultiLevelKeywordState
{
    /**
     * @template T of object
     *
     * @param  array<int, int>  $orderedArticleIds
     * @param  callable(int): ?T  $loadArticle
     * @param  callable(T): array{media: ?string, nofollow: bool, rows: array<int, array{url: string, keyword: string}>}  $payloadFn
     * @return array<int, Box>
     */
    public static function buildBoxes(array $orderedArticleIds, callable $loadArticle, callable $payloadFn): array
    {
        if (count($orderedArticleIds) === 0) {
            return [];
        }

        $boxes = [];
        $groupSig = null;
        $groupQty = 0;
        /** @var array|null $groupPayload */
        $groupPayload = null;

        $flush = function () use (&$boxes, &$groupSig, &$groupQty, &$groupPayload) {
            if ($groupSig === null || $groupPayload === null) {
                return;
            }
            $boxes[] = [
                'quantity' => $groupQty,
                'media'    => $groupPayload['media'],
                'nofollow' => $groupPayload['nofollow'],
                'rows'     => $groupPayload['rows'],
            ];
        };

        foreach ($orderedArticleIds as $aid) {
            $ca = $loadArticle((int) $aid);
            if ($ca === null) {
                continue;
            }
            $payload = $payloadFn($ca);
            $sig = json_encode(
                [
                    'm'    => $payload['media'],
                    'nf'   => $payload['nofollow'],
                    'rows' => $payload['rows'],
                ],
                JSON_UNESCAPED_UNICODE
            );

            if ($groupSig === null) {
                $groupSig = $sig;
                $groupPayload = $payload;
                $groupQty = 1;
            } elseif ($groupSig === $sig) {
                $groupQty++;
            } else {
                $flush();
                $groupSig = $sig;
                $groupPayload = $payload;
                $groupQty = 1;
            }
        }
        $flush();

        return $boxes;
    }

    /**
     * @return array{media: ?string, nofollow: bool, rows: array<int, array{url: string, keyword: string}>}
     */
    public static function payloadFromKeywordColumns(object $ca): array
    {
        $kw = $ca->keyword;
        $url = $ca->url;

        if (($ca->keyword_type ?? '') === 'json') {
            $kwArr = json_decode((string) $kw, true);
            $urlArr = json_decode((string) $url, true);
            if (! is_array($kwArr)) {
                $kwArr = [];
            }
            if (! is_array($urlArr)) {
                $urlArr = [];
            }
        } else {
            $kwArr = [trim((string) $kw)];
            $urlArr = [trim((string) $url)];
        }

        $n = min(count($kwArr), count($urlArr));
        $rows = [];
        for ($i = 0; $i < $n; $i++) {
            $rows[] = [
                'url'     => (string) ($urlArr[$i] ?? ''),
                'keyword' => (string) ($kwArr[$i] ?? ''),
            ];
        }
        if (count($rows) === 0) {
            $rows[] = ['url' => '', 'keyword' => ''];
        }

        $media = $ca->media ?? null;
        $media = $media === null || trim((string) $media) === '' ? null : trim((string) $media);

        return [
            'media'    => $media,
            'nofollow' => (bool) ($ca->nofollow ?? false),
            'rows'     => $rows,
        ];
    }

    /**
     * Sidebar / hidden-link / schedule-blogroll link rows (anchor_keyword + target_url, optional nofollow).
     *
     * @return array{media: null, nofollow: bool, rows: array<int, array{url: string, keyword: string}>}
     */
    public static function payloadFromAnchorKeywordUrl(object $link): array
    {
        return [
            'media'    => null,
            'nofollow' => (bool) ($link->nofollow ?? false),
            'rows'     => [
                [
                    'keyword' => trim((string) ($link->anchor_keyword ?? '')),
                    'url'     => trim((string) ($link->target_url ?? '')),
                ],
            ],
        ];
    }
}
