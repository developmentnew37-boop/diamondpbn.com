<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('domain_status_check_items');
        Schema::dropIfExists('domain_status_checks');

        Schema::create('domain_status_checks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->string('source', 20)->default('manual');
            $table->string('status', 20)->default('queued');
            $table->string('phase', 20)->default('initial');
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('connected_count')->default(0);
            $table->unsignedInteger('disconnected_count')->default(0);
            $table->unsignedInteger('in_inventory_count')->default(0);
            $table->unsignedInteger('inventory_updated_count')->default(0);
            $table->boolean('update_inventory')->default(false);
            $table->foreignId('domain_category_id')->nullable()->constrained('domain_categories')->nullOnDelete();
            $table->text('status_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['admin_id', 'status'], 'dsc_admin_status_idx');
            $table->index('created_at', 'dsc_created_at_idx');
        });

        Schema::create('domain_status_check_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_status_check_id')->constrained('domain_status_checks')->cascadeOnDelete();
            $table->string('domain');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('check_status', 20)->default('pending');
            $table->boolean('connected')->nullable();
            $table->string('message')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->boolean('in_inventory')->default(false);
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->string('category')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->index(['domain_status_check_id', 'check_status'], 'dsc_items_status_idx');
            $table->index(['domain_status_check_id', 'sort_order'], 'dsc_items_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_status_check_items');
        Schema::dropIfExists('domain_status_checks');
    }
};
