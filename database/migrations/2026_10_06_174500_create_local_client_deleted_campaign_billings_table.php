<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('local_client_deleted_campaign_billings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('local_client_id');
            $table->string('billable_type');
            $table->unsignedBigInteger('billable_id');
            $table->string('campaign_type', 64);
            $table->string('campaign_no', 191)->nullable();
            $table->decimal('billing_total', 12, 2);
            $table->decimal('billing_amount_paid', 12, 2)->nullable();
            $table->string('billing_currency', 3)->default('USD');
            $table->string('billing_payment_status', 32);
            $table->timestamp('billing_paid_at')->nullable();
            $table->timestamp('campaign_created_at')->nullable();
            $table->timestamps();

            $table->foreign('local_client_id', 'deleted_campaign_billings_client_fk')
                ->references('id')
                ->on('local_clients')
                ->cascadeOnDelete();
            $table->unique(['billable_type', 'billable_id'], 'deleted_campaign_billings_billable_uq');
            $table->index(['local_client_id', 'campaign_created_at'], 'deleted_campaign_billings_client_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_client_deleted_campaign_billings');
    }
};
