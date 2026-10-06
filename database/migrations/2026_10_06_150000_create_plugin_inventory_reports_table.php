<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugin_inventory_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->string('source', 20)->default('manual');
            $table->foreignId('domain_category_id')->nullable()->constrained('domain_categories')->nullOnDelete();
            $table->string('status', 20)->default('queued');
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->string('file_path')->nullable();
            $table->text('status_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('admin_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_inventory_reports');
    }
};
