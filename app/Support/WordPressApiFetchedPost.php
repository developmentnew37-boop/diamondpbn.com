<?php

namespace App\Support;

/**
 * Normalizes external/WP REST post payloads for admin edit forms.
 * Titles often arrive HTML-entity-encoded (e.g. &#039;) while inputs need plain text.
 */
class WordPressApiFetchedPost
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{post_title: string, post_content: string}
     */
    public static function normalizeForEditForm(array $data): array
    {
        $title = (string) ($data['post_title'] ?? $data['title'] ?? $data['title']['rendered'] ?? '');
        $content = (string) ($data['post_content'] ?? $data['content'] ?? $data['content']['rendered'] ?? '');
        if ($content === '' && isset($data['content']['raw'])) {
            $content = (string) $data['content']['raw'];
        }

        return [
            'post_title'   => html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'post_content' => $content,
        ];
    }
}
