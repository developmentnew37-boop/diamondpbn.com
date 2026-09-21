<?php

namespace Tests\Unit;

use App\Jobs\PublishScheduledCampaignPostJob;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ScheduleCampaignBulkRetrySafetyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'queue.default' => 'sync',
        ]);
    }

    public function test_unique_id_is_post_and_generation(): void
    {
        $job = new PublishScheduledCampaignPostJob(42, 7);

        $this->assertInstanceOf(ShouldBeUniqueUntilProcessing::class, $job);
        $this->assertSame('42:7', $job->uniqueId());
    }

    public function test_duplicate_dispatch_same_generation_is_dropped(): void
    {
        Queue::fake();

        PublishScheduledCampaignPostJob::dispatch(10, 3)->onQueue('scheduled_campaigns');
        PublishScheduledCampaignPostJob::dispatch(10, 3)->onQueue('scheduled_campaigns');

        Queue::assertPushed(PublishScheduledCampaignPostJob::class, 1);
    }

    public function test_new_generation_is_not_dropped_as_duplicate(): void
    {
        Queue::fake();

        PublishScheduledCampaignPostJob::dispatch(10, 3)->onQueue('scheduled_campaigns');
        PublishScheduledCampaignPostJob::dispatch(10, 4)->onQueue('scheduled_campaigns');

        Queue::assertPushed(PublishScheduledCampaignPostJob::class, 2);
    }

    public function test_wordpress_write_url_creates_when_remote_id_missing(): void
    {
        $this->assertSame(
            'https://example.com/wp-json/external/v1/posts/create',
            PublishScheduledCampaignPostJob::wordpressWriteUrl('example.com', null)
        );
    }

    public function test_wordpress_write_url_updates_when_remote_id_present(): void
    {
        $this->assertSame(
            'https://example.com/wp-json/external/v1/posts/update/99',
            PublishScheduledCampaignPostJob::wordpressWriteUrl('example.com', '99')
        );
    }
}
