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
        Schema::create('domains', function (Blueprint $table) {
            $table->id('id');
            $table->string('name')->unique();
            $table->integer('da')->default(0); // domain authority
            $table->integer('dr')->default(0); // domain rating
            $table->integer('tf')->default(0); // Trust Flow
            $table->integer('ss')->default(0); // spam score
            $table->string('ip')->nullable(); // ip address
            $table->string('api_key'); // ip address
            $table->unsignedBigInteger('domain_category_id');
            $table->integer('status')->default(0); // it should be 0 or 1
            $table->foreign('domain_category_id')
                ->references('id')
                ->on('domain_categories')
                ->onDelete('cascade');
            $table->unsignedBigInteger('admin_id');
            $table->foreign('admin_id')
                ->references('id')
                ->on('admins')
                ->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
