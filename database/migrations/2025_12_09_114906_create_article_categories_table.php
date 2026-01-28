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
        Schema::create('article_categories', function (Blueprint $table) {
            $table->id();

            // Main Fields
            $table->string('name')->unique();
            $table->string('slug')->unique();

            // Parent Category (optional)
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('article_categories')
                ->nullOnDelete();       // delete child on parent delete or change to ->cascadeOnDelete()

            // Description
            $table->text('description')->nullable();

            // Admin Owner
            $table->foreignId('admin_id')
                ->constrained('admins')
                ->cascadeOnDelete();    // remove categories if admin deleted

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_categories');
    }
};
