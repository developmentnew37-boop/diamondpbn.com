<?php

namespace Tests\Feature;

use App\Data\AgentStatusResult;
use App\Jobs\CheckDomainStatus;
use App\Jobs\RefreshTransferredDomainsStatusJob;
use App\Models\Admin\Domain;
use App\Services\WordPressAgentStatusService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

class DomainHealthCheckHardeningTest extends TestCase
{
    use DatabaseTransactions;

    public function test_status_job_persists_agent_health_fields(): void
    {
        $categoryId = (int) (Domain::query()->value('domain_category_id') ?? 1);
        $domain = Domain::query()->create([
            'name' => 'health-'.uniqid().'.example',
            'admin_id' => 1,
            'domain_category_id' => $categoryId,
        ]);
        $status = Mockery::mock(WordPressAgentStatusService::class);
        $status->shouldReceive('probeWithAuthFallback')
            ->once()
            ->with($domain->name, '')
            ->andReturn(new AgentStatusResult(
                true,
                'online',
                'Connected',
                true,
                '8.2.0',
                'fallback',
                200,
                17,
            ));

        (new CheckDomainStatus($domain->id))->handle($status);

        $domain->refresh();
        $this->assertSame(1, $domain->status);
        $this->assertSame('8.2.0', $domain->agent_version);
        $this->assertNotNull($domain->last_seen_at);
        $this->assertSame('online', $domain->last_status_code);
        $this->assertSame('fallback', $domain->last_status_probe);
        $this->assertSame('Connected', $domain->last_status_message);
    }

    public function test_health_command_chunks_ids_without_queueing_credentials(): void
    {
        Bus::fake();

        $suffix = uniqid();
        $categoryId = (int) (Domain::query()->value('domain_category_id') ?? 1);
        Domain::query()->insert([
            ['name' => "one-{$suffix}.example", 'api_key' => 'must-not-be-queued', 'admin_id' => 1, 'domain_category_id' => $categoryId, 'created_at' => now(), 'updated_at' => now()],
            ['name' => "two-{$suffix}.example", 'api_key' => 'must-not-be-queued', 'admin_id' => 1, 'domain_category_id' => $categoryId, 'created_at' => now(), 'updated_at' => now()],
            ['name' => "three-{$suffix}.example", 'api_key' => 'must-not-be-queued', 'admin_id' => 1, 'domain_category_id' => $categoryId, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $chunkSize = 2;
        $totalDomains = Domain::query()->count();
        $expectedJobs = (int) ceil($totalDomains / $chunkSize);

        $this->artisan('domains:health-check', ['--chunk' => $chunkSize])
            ->expectsOutput("Queued {$totalDomains} domain(s) in {$expectedJobs} health-check job(s).")
            ->assertSuccessful();

        Bus::assertDispatchedTimes(RefreshTransferredDomainsStatusJob::class, $expectedJobs);
        Bus::assertDispatched(RefreshTransferredDomainsStatusJob::class, function ($job) {
            $serialized = serialize($job);

            return count($job->domainIds) <= 2
                && ! str_contains($serialized, 'must-not-be-queued')
                && $job->queue === 'domainHealthSync';
        });
    }

    public function test_refresh_job_uses_health_sync_queue_by_default(): void
    {
        $job = new RefreshTransferredDomainsStatusJob([1, 2, 3]);

        $this->assertSame('domainHealthSync', $job->queue);
    }

    public function test_health_command_is_registered_every_fifteen_minutes(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'domains:health-check'));

        $this->assertNotNull($event);
        $this->assertSame('*/15 * * * *', $event->expression);
    }
}
