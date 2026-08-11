<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('local_client_payment_events')) {
            return;
        }

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

    public function down(): void
    {
        // Repair migration — no rollback.
    }
};
