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
        Schema::table('campaign_articles', function (Blueprint $table) {
            $table->boolean('ugc')->default(false)->after('sponsored');
            $table->boolean('noopener')->default(false)->after('ugc');
            $table->boolean('noreferrer')->default(false)->after('noopener');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_articles', function (Blueprint $table) {
            $table->dropColumn(['ugc', 'noopener', 'noreferrer']);
        });
    }
};
