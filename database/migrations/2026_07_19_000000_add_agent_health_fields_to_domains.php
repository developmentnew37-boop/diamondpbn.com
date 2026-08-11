<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->string('agent_version', 32)->nullable()->after('status');
            $table->timestamp('last_seen_at')->nullable()->after('agent_version');
            $table->string('last_status_code', 64)->nullable()->after('last_seen_at');
            $table->string('last_status_probe', 32)->nullable()->after('last_status_code');
            $table->text('last_status_message')->nullable()->after('last_status_probe');

            $table->index('last_status_code', 'domains_last_status_code_idx');
            $table->index('last_seen_at', 'domains_last_seen_at_idx');
        });

        Schema::table('domain_status_check_items', function (Blueprint $table) {
            $table->string('status_code', 64)->nullable()->after('connected');
            $table->string('probe_method', 32)->nullable()->after('status_code');
            $table->string('agent_version', 32)->nullable()->after('probe_method');
            $table->unsignedSmallInteger('http_status')->nullable()->after('agent_version');
        });
    }

    public function down(): void
    {
        Schema::table('domain_status_check_items', function (Blueprint $table) {
            $table->dropColumn([
                'status_code',
                'probe_method',
                'agent_version',
                'http_status',
            ]);
        });

        Schema::table('domains', function (Blueprint $table) {
            $table->dropIndex('domains_last_status_code_idx');
            $table->dropIndex('domains_last_seen_at_idx');
            $table->dropColumn([
                'agent_version',
                'last_seen_at',
                'last_status_code',
                'last_status_probe',
                'last_status_message',
            ]);
        });
    }
};
