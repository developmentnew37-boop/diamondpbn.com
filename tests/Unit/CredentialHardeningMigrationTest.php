<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CredentialHardeningMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->string('api_key');
        });
        Schema::create('pending_domains', function (Blueprint $table) {
            $table->id();
            $table->text('api_key');
        });
        Schema::create('webhook_secrets', function (Blueprint $table) {
            $table->id();
            $table->string('secret', 128)->unique();
            $table->index('secret');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('webhook_secrets');
        Schema::dropIfExists('pending_domains');
        Schema::dropIfExists('domains');

        parent::tearDown();
    }

    public function test_migration_replaces_ciphertext_indexes_with_blind_indexes(): void
    {
        $migration = require database_path('migrations/2026_07_19_010001_harden_stored_credentials.php');

        $migration->up();

        $this->assertSame('text', Schema::getColumnType('domains', 'api_key'));
        $this->assertSame('text', Schema::getColumnType('pending_domains', 'api_key'));
        $this->assertSame('text', Schema::getColumnType('webhook_secrets', 'secret'));
        $this->assertTrue(Schema::hasIndex('domains', 'domains_api_key_lookup_hash_index'));
        $this->assertTrue(Schema::hasIndex('webhook_secrets', 'webhook_secrets_secret_lookup_hash_unique'));
        $this->assertFalse(Schema::hasIndex('webhook_secrets', 'webhook_secrets_secret_unique'));
        $this->assertFalse(Schema::hasIndex('webhook_secrets', 'webhook_secrets_secret_index'));

        $migration->down();

        $this->assertFalse(Schema::hasColumn('domains', 'api_key_lookup_hash'));
        $this->assertFalse(Schema::hasColumn('webhook_secrets', 'secret_lookup_hash'));
        $this->assertSame('text', Schema::getColumnType('webhook_secrets', 'secret'));
    }
}
