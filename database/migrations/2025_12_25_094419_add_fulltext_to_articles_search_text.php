<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration
{
    /**
     * Run the migrations.
     */
     public function up(): void
    {
        DB::statement("
            ALTER TABLE articles
            ADD FULLTEXT fulltext_search_text (search_text)
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE articles
            DROP INDEX fulltext_search_text
        ");
    }
};
