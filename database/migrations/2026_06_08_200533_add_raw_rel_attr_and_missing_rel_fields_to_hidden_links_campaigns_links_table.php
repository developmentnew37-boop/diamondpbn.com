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
        Schema::table('hidden_links_campaigns_links', function (Blueprint $table) {
            // Add missing rel attribute boolean fields
            $table->boolean('ugc')->default(false)->after('sponsored');
            $table->boolean('noopener')->default(false)->after('ugc');
            $table->boolean('noreferrer')->default(false)->after('noopener');

            // Add raw_rel_attr to store complete rel attribute string from raw HTML anchors
            $table->text('raw_rel_attr')->nullable()->after('noreferrer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hidden_links_campaigns_links', function (Blueprint $table) {
            $table->dropColumn(['ugc', 'noopener', 'noreferrer', 'raw_rel_attr']);
        });
    }
};
