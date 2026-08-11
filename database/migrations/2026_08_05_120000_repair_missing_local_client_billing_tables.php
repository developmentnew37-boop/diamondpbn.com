<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('local_client_domain_category_prices')) {
            Schema::create('local_client_domain_category_prices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('local_client_id')->constrained('local_clients')->cascadeOnDelete();
                $table->foreignId('domain_category_id')->constrained('domain_categories')->cascadeOnDelete();
                $table->decimal('post_price', 12, 2)->nullable();
                $table->decimal('sidebar_price', 12, 2)->nullable();
                $table->decimal('hidden_links_price', 12, 2)->nullable();
                $table->decimal('sticky_price', 12, 2)->nullable();
                $table->timestamps();

                $table->unique(['local_client_id', 'domain_category_id'], 'local_client_category_prices_unique');
            });
        }

        if (! Schema::hasTable('local_client_bill_lines')) {
            Schema::create('local_client_bill_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('local_client_id')->constrained('local_clients')->cascadeOnDelete();
                $table->foreignId('domain_id')->nullable()->constrained('domains')->nullOnDelete();
                $table->foreignId('domain_category_id')->nullable()->constrained('domain_categories')->nullOnDelete();
                $table->string('billing_campaign_type', 32);
                $table->decimal('unit_price', 12, 2);
                $table->decimal('line_total', 12, 2);
                $table->string('currency', 3)->default('USD');
                $table->string('billable_type');
                $table->unsignedBigInteger('billable_id');
                $table->string('campaign_no', 64)->nullable();
                $table->json('snapshot')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['billable_type', 'billable_id']);
                $table->index(['local_client_id', 'created_at']);
                $table->index('campaign_no');
            });
        }

        if (! Schema::hasTable('local_client_payment_events')) {
            Schema::create('local_client_payment_events', function (Blueprint $table) {
                $table->id();
                $table->string('billable_type');
                $table->unsignedBigInteger('billable_id');
                $table->foreignId('local_client_id')->nullable()->constrained('local_clients')->nullOnDelete();
                $table->string('campaign_no', 64)->nullable();
                $table->enum('old_status', ['unpaid', 'paid'])->nullable();
                $table->enum('new_status', ['unpaid', 'paid']);
                $table->text('note')->nullable();
                $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['billable_type', 'billable_id']);
            });
        }
    }

    public function down(): void
    {
        // Repair migration — no rollback.
    }
};
