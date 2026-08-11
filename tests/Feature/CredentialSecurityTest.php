<?php

namespace Tests\Feature;

use App\Jobs\CheckDomainStatus;
use App\Models\Admin\Domain;
use App\Models\Admin\PendingDomain;
use App\Models\Admin\WebhookSecret;
use App\Rules\UniqueCredential;
use App\Services\CredentialBlindIndex;
use App\Services\PendingDomainTransferService;
use App\Services\WordPressAgentStatusService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class CredentialSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        config()->set('credentials.lookup_key', 'base64:'.base64_encode(str_repeat('b', 32)));

        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('api_key')->nullable();
            $table->char('api_key_lookup_hash', 64)->nullable()->index();
            $table->unsignedBigInteger('domain_category_id')->default(1);
            $table->unsignedBigInteger('admin_id')->default(1);
            $table->integer('da')->default(0);
            $table->integer('dr')->default(0);
            $table->integer('tf')->default(0);
            $table->integer('ss')->default(0);
            $table->string('ip')->nullable();
            $table->integer('status')->default(0);
            $table->timestamps();
        });

        Schema::create('webhook_secrets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('secret');
            $table->char('secret_lookup_hash', 64)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('secret_rotated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pending_domains', function (Blueprint $table) {
            $table->id();
            $table->string('domain_name');
            $table->string('normalized_domain_name')->nullable();
            $table->text('api_key')->nullable();
            $table->boolean('viewed')->default(false);
            $table->unsignedBigInteger('webhook_secret_id');
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('pending_domains');
        Schema::dropIfExists('webhook_secrets');
        Schema::dropIfExists('domains');

        parent::tearDown();
    }

    public function test_models_encrypt_at_rest_decrypt_and_hide_credentials(): void
    {
        $domain = Domain::create([
            'name' => 'example.com',
            'api_key' => 'domain-secret',
            'admin_id' => 1,
        ]);
        $webhook = WebhookSecret::create([
            'name' => 'Partner',
            'secret' => 'webhook-secret',
        ]);
        $pending = PendingDomain::create([
            'domain_name' => 'pending.example',
            'api_key' => 'pending-secret',
            'webhook_secret_id' => $webhook->id,
        ]);

        $this->assertNotSame('domain-secret', DB::table('domains')->value('api_key'));
        $this->assertNotSame('webhook-secret', DB::table('webhook_secrets')->value('secret'));
        $this->assertNotSame('pending-secret', DB::table('pending_domains')->value('api_key'));
        $this->assertSame('domain-secret', $domain->fresh()->api_key);
        $this->assertSame('webhook-secret', $webhook->fresh()->secret);
        $this->assertSame('pending-secret', $pending->fresh()->api_key);
        $this->assertArrayNotHasKey('api_key', $domain->toArray());
        $this->assertArrayNotHasKey('api_key_lookup_hash', $domain->toArray());
        $this->assertArrayNotHasKey('secret', $webhook->toArray());
        $this->assertArrayNotHasKey('secret_lookup_hash', $webhook->toArray());
        $this->assertArrayNotHasKey('api_key', $pending->toArray());
    }

    public function test_webhook_authenticates_by_blind_index_and_constant_time_verification(): void
    {
        WebhookSecret::create([
            'name' => 'Partner',
            'secret' => 'correct-secret',
            'is_active' => true,
        ]);

        $this->withoutMiddleware()
            ->postJson('/api/webhook/domains', [
                'domain_name' => 'incoming.example',
                'api_key' => 'incoming-key',
                'secret' => 'wrong-secret',
            ])
            ->assertForbidden();

        $this->withoutMiddleware()
            ->postJson('/api/webhook/domains', [
                'domain_name' => 'incoming.example',
                'api_key' => 'incoming-key',
                'secret' => 'correct-secret',
            ])
            ->assertCreated();

        $this->assertSame('incoming-key', PendingDomain::firstOrFail()->api_key);
    }

    public function test_conversion_is_dry_run_safe_mixed_state_and_idempotent(): void
    {
        DB::table('domains')->insert([
            'name' => 'plain.example',
            'api_key' => 'plain-key',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('pending_domains')->insert([
            'domain_name' => 'encrypted.example',
            'api_key' => Crypt::encryptString('already-encrypted'),
            'webhook_secret_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('webhook_secrets')->insert([
            'name' => 'Existing',
            'secret' => Crypt::encryptString('existing-secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('credentials:encrypt', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame('plain-key', DB::table('domains')->value('api_key'));

        $this->artisan('credentials:encrypt')->assertSuccessful();
        $firstCiphertext = DB::table('domains')->value('api_key');
        $this->assertSame('plain-key', Crypt::decryptString($firstCiphertext));
        $this->assertNotNull(DB::table('domains')->value('api_key_lookup_hash'));
        $this->assertNotNull(DB::table('webhook_secrets')->value('secret_lookup_hash'));

        $this->artisan('credentials:encrypt')->assertSuccessful();
        $this->assertSame($firstCiphertext, DB::table('domains')->value('api_key'));
    }

    public function test_conversion_fails_and_reports_likely_corrupt_envelopes(): void
    {
        $corrupt = base64_encode(json_encode(['iv' => 'broken', 'value' => 'broken']));
        DB::table('webhook_secrets')->insert([
            'name' => 'Broken',
            'secret' => $corrupt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('credentials:encrypt')
            ->expectsOutputToContain('webhook_secrets ID')
            ->assertFailed();

        $this->assertSame($corrupt, DB::table('webhook_secrets')->value('secret'));
    }

    public function test_pending_transfer_uses_encrypted_eloquent_write_and_hash(): void
    {
        $webhook = WebhookSecret::create(['name' => 'Partner', 'secret' => 'hook-secret']);
        $pending = PendingDomain::create([
            'domain_name' => 'transfer.example',
            'api_key' => 'transfer-key',
            'webhook_secret_id' => $webhook->id,
        ]);

        $service = new PendingDomainTransferService(app(WordPressAgentStatusService::class));
        $result = $service->bulkTransfer([$pending->id], 4, 9, queueStatusCheck: false);

        $this->assertSame(1, $result['success']);
        $domain = Domain::firstOrFail();
        $this->assertSame('transfer-key', $domain->api_key);
        $this->assertNotSame('transfer-key', DB::table('domains')->value('api_key'));
        $this->assertSame(
            app(CredentialBlindIndex::class)->hash('transfer-key', CredentialBlindIndex::DOMAIN_API_KEY),
            DB::table('domains')->value('api_key_lookup_hash')
        );
    }

    public function test_api_key_uniqueness_uses_hashes_and_legacy_fallback(): void
    {
        Domain::create([
            'name' => 'hashed.example',
            'api_key' => 'hashed-key',
            'admin_id' => 1,
        ]);
        DB::table('domains')->insert([
            'name' => 'legacy.example',
            'api_key' => 'legacy-key',
            'api_key_lookup_hash' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rule = new UniqueCredential(
            'domains',
            'api_key_lookup_hash',
            CredentialBlindIndex::DOMAIN_API_KEY,
            credentialColumn: 'api_key',
        );

        $this->assertTrue(Validator::make(['api_key' => 'hashed-key'], ['api_key' => [$rule]])->fails());
        $this->assertTrue(Validator::make(['api_key' => 'legacy-key'], ['api_key' => [$rule]])->fails());
        $this->assertFalse(Validator::make(['api_key' => 'new-key'], ['api_key' => [$rule]])->fails());
    }

    public function test_domain_status_job_payload_contains_only_domain_id(): void
    {
        $domain = Domain::create([
            'name' => 'queued.example',
            'api_key' => 'plaintext-api-key',
            'admin_id' => 1,
        ]);
        $job = new CheckDomainStatus($domain->id);
        $serialized = serialize($job);

        $this->assertStringContainsString('domainId', $serialized);
        $this->assertStringNotContainsString('plaintext-api-key', $serialized);
        $this->assertSame($domain->id, unserialize($serialized)->domainId);
    }

    public function test_admin_credential_views_do_not_embed_stored_secrets(): void
    {
        $views = [
            'resources/views/admin/domains/domains.blade.php',
            'resources/views/admin/domains/edit-domains.blade.php',
            'resources/views/admin/domains/domain-set-detail.blade.php',
            'resources/views/admin/domains/pending/show.blade.php',
            'resources/views/admin/domains/transfer/step1.blade.php',
            'resources/views/admin/domains/webhook-secrets/index.blade.php',
            'resources/views/admin/domains/webhook-secrets/edit.blade.php',
        ];

        foreach ($views as $view) {
            $contents = file_get_contents(base_path($view));

            $this->assertStringNotContainsString('$domain->api_key', $contents, $view);
            $this->assertStringNotContainsString('$pendingDomain->api_key', $contents, $view);
            $this->assertDoesNotMatchRegularExpression('/\\$secret->secret(?![_a-zA-Z0-9])/', $contents, $view);
            $this->assertDoesNotMatchRegularExpression('/\\$webhookSecret->secret(?![_a-zA-Z0-9])/', $contents, $view);
            $this->assertStringNotContainsString('data-api-key=', $contents, $view);
            $this->assertStringNotContainsString('data-secret=', $contents, $view);
        }
    }
}
