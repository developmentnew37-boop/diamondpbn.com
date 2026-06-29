<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE campaigns MODIFY COLUMN article_type ENUM('own_article', 'system_article', 'language_article') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("UPDATE campaigns SET article_type = 'system_article' WHERE article_type = 'language_article'");
        DB::statement("ALTER TABLE campaigns MODIFY COLUMN article_type ENUM('own_article', 'system_article') NOT NULL");
    }
};
