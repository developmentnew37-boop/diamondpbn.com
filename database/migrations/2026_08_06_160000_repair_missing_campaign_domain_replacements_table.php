<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('campaign_domain_replacements')) {
            return;
        }

        Schema::create('campaign_domain_replacements', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_uuid')->unique();
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->foreignId('campaign_post_id')->nullable()->constrained('campaign_posts')->nullOnDelete();
            $table->foreignId('campaign_domain_id')->nullable()->constrained('campaign_domains')->nullOnDelete();
            $table->foreignId('old_domain_id')->nullable()->constrained('domains')->nullOnDelete();
            $table->foreignId('new_domain_id')->nullable()->constrained('domains')->nullOnDelete();
            $table->string('old_hostname');
            $table->string('new_hostname');
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->string('previous_status', 32);
            $table->string('result_status', 32)->nullable();
            $table->json('health_snapshot')->nullable();
            $table->unsignedInteger('dispatch_generation');
            $table->string('state', 32)->default('pending');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'created_at']);
            $table->index(['campaign_post_id', 'created_at']);
            $table->index(['campaign_domain_id', 'created_at']);
            $table->index(['state', 'created_at']);
            $table->index(['old_domain_id', 'new_domain_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_domain_replacements');
    }
};
