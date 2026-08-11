<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists('local_client_domain_category_prices');
    }
};
