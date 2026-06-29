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
        Schema::create('webhook_secrets', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Identifier/label for this webhook secret');
            $table->string('secret', 128)->unique()->comment('Secret token for webhook validation');
            $table->boolean('is_active')->default(true)->comment('Whether this secret is currently active');
            $table->timestamp('last_used_at')->nullable()->comment('Last time this secret was used');
            $table->timestamps();

            $table->index('secret');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_secrets');
    }
};
