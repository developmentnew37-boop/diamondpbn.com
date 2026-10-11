<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('local_client_manual_billings')) {
            return;
        }

        Schema::create('local_client_manual_billings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('local_client_id')->constrained('local_clients')->cascadeOnDelete();
            $table->string('campaign_no', 191);
            $table->string('billing_campaign_type', 32);
            $table->text('post_links')->nullable();
            $table->string('keyword', 500)->nullable();
            $table->string('target_url', 2000)->nullable();
            $table->decimal('billing_total', 12, 2);
            $table->decimal('billing_amount_paid', 12, 2)->nullable();
            $table->string('billing_currency', 3);
            $table->json('billing_snapshot');
            $table->string('billing_payment_status', 32)->default('unpaid');
            $table->timestamp('billing_paid_at')->nullable();
            $table->unsignedBigInteger('billing_paid_by_admin_id')->nullable();
            $table->text('billing_payment_note')->nullable();
            $table->unsignedBigInteger('admin_id');
            $table->timestamps();

            $table->index(['local_client_id', 'billing_payment_status'], 'local_client_manual_billings_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_client_manual_billings');
    }
};
