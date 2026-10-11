<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('local_client_installments')) {
            Schema::create('local_client_installments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('local_client_id')->constrained('local_clients')->cascadeOnDelete();
                $table->decimal('amount', 12, 2);
                $table->string('currency', 3);
                $table->string('source', 32)->default('payment');
                $table->text('note')->nullable();
                $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['local_client_id', 'id'], 'local_client_installments_client_idx');
            });
        }

        if (! Schema::hasTable('local_client_installment_allocations')) {
            Schema::create('local_client_installment_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('installment_id')->constrained('local_client_installments')->cascadeOnDelete();
                $table->string('billable_type');
                $table->unsignedBigInteger('billable_id');
                $table->unsignedBigInteger('deleted_campaign_billing_id')->nullable();
                $table->string('campaign_no', 191)->nullable();
                $table->decimal('amount', 12, 2);
                $table->decimal('previous_collected_amount', 12, 2);
                $table->decimal('resulting_collected_amount', 12, 2);
                $table->decimal('billing_total', 12, 2);
                $table->string('old_status', 32);
                $table->string('new_status', 32);
                $table->timestamp('created_at')->useCurrent();

                $table->index(['billable_type', 'billable_id'], 'lc_installment_allocations_billable_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('local_client_installment_allocations');
        Schema::dropIfExists('local_client_installments');
    }
};
