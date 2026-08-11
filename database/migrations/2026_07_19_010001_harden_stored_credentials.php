<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasIndex('webhook_secrets', 'webhook_secrets_secret_unique')) {
            Schema::table('webhook_secrets', function (Blueprint $table) {
                $table->dropUnique('webhook_secrets_secret_unique');
            });
        }

        if (Schema::hasIndex('webhook_secrets', 'webhook_secrets_secret_index')) {
            Schema::table('webhook_secrets', function (Blueprint $table) {
                $table->dropIndex('webhook_secrets_secret_index');
            });
        }

        Schema::table('domains', function (Blueprint $table) {
            $table->text('api_key')->nullable()->change();
            $table->char('api_key_lookup_hash', 64)->nullable()->after('api_key');
            $table->index('api_key_lookup_hash', 'domains_api_key_lookup_hash_index');
        });

        Schema::table('pending_domains', function (Blueprint $table) {
            $table->text('api_key')->nullable()->change();
        });

        Schema::table('webhook_secrets', function (Blueprint $table) {
            $table->text('secret')->change();
            $table->char('secret_lookup_hash', 64)->nullable()->after('secret');
            $table->unique('secret_lookup_hash', 'webhook_secrets_secret_lookup_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropIndex('domains_api_key_lookup_hash_index');
            $table->dropColumn('api_key_lookup_hash');
        });

        Schema::table('webhook_secrets', function (Blueprint $table) {
            $table->dropUnique('webhook_secrets_secret_lookup_hash_unique');
            $table->dropColumn('secret_lookup_hash');
        });

        /*
         * Credential columns intentionally remain TEXT. Encrypted Laravel
         * envelopes can exceed their former VARCHAR lengths, and narrowing or
         * recreating a ciphertext unique index could truncate data or fail a
         * production rollback.
         */
    }
};
