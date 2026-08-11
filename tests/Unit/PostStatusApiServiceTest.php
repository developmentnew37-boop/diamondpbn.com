<?php

namespace Tests\Unit;

use App\Services\PostStatusApiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PostStatusApiServiceTest extends TestCase
{
    public function test_draft_interprets_success_and_skipped(): void
    {
        Http::fake([
            'https://example.test/wp-json/external/v1/posts/draft/99' => Http::response([
                'success' => true,
                'action' => 'skipped',
                'message' => 'Already draft',
            ], 200),
        ]);

        $result = app(PostStatusApiService::class)->draftPost('example.test', 'key', '99');

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['skipped']);
    }

    public function test_fetch_post_extracts_status_and_url(): void
    {
        Http::fake([
            'https://example.test/wp-json/external/v1/posts/42*' => Http::response([
                'ID' => 42,
                'post_status' => 'publish',
                'remote_url' => 'https://example.test/my-post/',
            ], 200),
        ]);

        $result = app(PostStatusApiService::class)->fetchPost('example.test', 'key', '42');

        $this->assertTrue($result['ok']);
        $this->assertSame('publish', $result['status']);
        $this->assertSame('https://example.test/my-post/', $result['remote_url']);
    }
}
