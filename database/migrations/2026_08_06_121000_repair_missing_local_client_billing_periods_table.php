<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('local_client_billing_periods')) {
            return;
        }

        Schema::create('local_client_billing_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('local_client_id')->constrained('local_clients')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('campaign_count');
            $table->decimal('total_amount', 12, 2);
            $table->string('currency', 3);
            $table->timestamp('paid_at');
            $table->foreignId('paid_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('payment_note')->nullable();
            $table->timestamps();

            $table->index(['local_client_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_client_billing_periods');
    }
};
