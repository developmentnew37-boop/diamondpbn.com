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
        Schema::create('domain_sets', function (Blueprint $table) {
            $table->id();                                     // Primary Key
            $table->string('name')->unique();                // Unique set name
            $table->string('slug')->unique();                // Unique slug - good for URLs
            $table->integer('qty')->default(0);              // Domain count
            $table->json('domains');                         // Store selected domain IDs
            $table->unsignedBigInteger('domain_category_id'); // FK Category
            $table->unsignedBigInteger('admin_id');          // FK Admin
            $table->timestamps();                            // Created + Updated
            // $table->softDeletes();                         // Useful if you plan trash bin

            $table->foreign('domain_category_id')->references('id')->on('domain_categories')->onDelete('cascade');
            $table->foreign('admin_id')->references('id')->on('admins')->onDelete('cascade');

            $table->index('domain_category_id');             // Speed filters
            $table->index('admin_id');                       // Speed queries
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domain_sets');
    }
};
