<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('name_normalized', 255)
                ->nullable()
                ->after('name');

            $table->index(
                ['admin_id', 'article_language_id', 'name_normalized'],
                'articles_dup_lookup_idx'
            );
        });

        DB::statement(
            "UPDATE articles SET name_normalized = LOWER(TRIM(name)) WHERE name IS NOT NULL"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex('articles_dup_lookup_idx');
            $table->dropColumn('name_normalized');
        });
    }
};
