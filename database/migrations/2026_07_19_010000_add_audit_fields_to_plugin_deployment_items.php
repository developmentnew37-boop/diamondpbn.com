<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plugin_deployment_items', function (Blueprint $table) {
            $table->text('message')->nullable()->change();
            $table->unsignedSmallInteger('http_status')->nullable()->after('response_time_ms');
            $table->text('request_url')->nullable()->after('http_status');
            $table->string('probe_method', 50)->nullable()->after('request_url');
            $table->json('audit_trail')->nullable()->after('probe_method');
            $table->unsignedSmallInteger('retry_count')->default(0)->after('audit_trail');
        });
    }

    public function down(): void
    {
        Schema::table('plugin_deployment_items', function (Blueprint $table) {
            $table->dropColumn([
                'http_status',
                'request_url',
                'probe_method',
                'audit_trail',
                'retry_count',
            ]);
        });

        // Keep the safe text widening: narrowing could truncate deployment diagnostics.
    }
};
