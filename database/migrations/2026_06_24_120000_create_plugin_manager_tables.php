<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugin_packages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->string('version');
            $table->string('original_filename');
            $table->string('storage_path');
            $table->unsignedBigInteger('file_size_bytes');
            $table->string('checksum_sha256', 64);
            $table->boolean('is_diamond_pbn_agent')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['slug', 'version']);
            $table->index('admin_id');
        });

        Schema::create('plugin_deployments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->foreignId('plugin_package_id')->constrained('plugin_packages')->cascadeOnDelete();
            $table->string('operation', 20);
            $table->string('source', 20)->default('manual');
            $table->foreignId('domain_category_id')->nullable()->constrained('domain_categories')->nullOnDelete();
            $table->string('status', 20)->default('queued');
            $table->string('phase', 20)->default('initial');
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->boolean('activate_after')->default(true);
            $table->boolean('skip_if_same_version')->default(true);
            $table->text('status_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['admin_id', 'status']);
            $table->index('created_at');
        });

        Schema::create('plugin_deployment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plugin_deployment_id')->constrained('plugin_deployments')->cascadeOnDelete();
            $table->string('domain');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('item_status', 20)->default('pending');
            $table->string('operation_result', 20)->nullable();
            $table->string('version_before')->nullable();
            $table->string('version_after')->nullable();
            $table->string('error_code')->nullable();
            $table->string('message')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->boolean('in_inventory')->default(false);
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->string('category')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['plugin_deployment_id', 'item_status']);
            $table->index(['plugin_deployment_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_deployment_items');
        Schema::dropIfExists('plugin_deployments');
        Schema::dropIfExists('plugin_packages');
    }
};
