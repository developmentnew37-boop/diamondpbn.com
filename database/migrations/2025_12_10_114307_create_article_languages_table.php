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
        Schema::create('article_languages', function (Blueprint $table) {
            $table->id(); // sno

            $table->string('name')->unique();          // Language name (English, Hindi, Urdu etc.)
            $table->string('slug')->unique();          // SEO friendly slug (english, hindi)

            $table->foreignId('admin_id')              // Who created it
                ->constrained('admins')
                ->cascadeOnDelete();                   // If admin deleted, delete language too
            // If you want to keep language even if admin removed → replace with ->nullOnDelete()

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_languages');
    }
};
