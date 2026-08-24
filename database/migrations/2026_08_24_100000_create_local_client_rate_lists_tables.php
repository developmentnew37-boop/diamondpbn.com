<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('local_client_rate_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_admin_id')->constrained('admins')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('local_client_rate_list_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rate_list_id')->constrained('local_client_rate_lists')->cascadeOnDelete();
            $table->foreignId('domain_category_id')->constrained('domain_categories')->cascadeOnDelete();
            $table->decimal('post_price', 12, 2)->nullable();
            $table->decimal('sidebar_price', 12, 2)->nullable();
            $table->decimal('hidden_links_price', 12, 2)->nullable();
            $table->decimal('sticky_price', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['rate_list_id', 'domain_category_id'], 'local_client_rate_list_prices_unique');
        });

        Schema::table('local_clients', function (Blueprint $table) {
            $table->foreignId('rate_list_id')
                ->nullable()
                ->after('created_by_admin_id')
                ->constrained('local_client_rate_lists')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('local_clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rate_list_id');
        });

        Schema::dropIfExists('local_client_rate_list_prices');
        Schema::dropIfExists('local_client_rate_lists');
    }
};
