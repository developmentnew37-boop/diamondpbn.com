<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists('local_client_bill_lines');
    }
};
