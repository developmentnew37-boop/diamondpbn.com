<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pending_domains', function (Blueprint $table) {
            $table->id();
            $table->string('domain_name')->comment('Domain URL submitted via webhook');
            $table->text('api_key')->comment('API key for the domain');
            $table->boolean('viewed')->default(false)->comment('Whether admin has viewed this pending domain');
            $table->foreignId('webhook_secret_id')->constrained('webhook_secrets')->onDelete('cascade')->comment('Which webhook secret was used');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->comment('Current status of the domain');
            $table->text('notes')->nullable()->comment('Admin notes about this domain');
            $table->timestamp('approved_at')->nullable()->comment('When the domain was approved');
            $table->timestamp('rejected_at')->nullable()->comment('When the domain was rejected');
            $table->timestamps();

            $table->index('domain_name');
            $table->index('viewed');
            $table->index('status');
            $table->index('webhook_secret_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_domains');
    }
};
