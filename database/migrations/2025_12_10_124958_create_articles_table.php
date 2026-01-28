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
        Schema::create('articles', function (Blueprint $table) {

            $table->bigIncrements('id');

            /* --------------------
            | Core Content
            |--------------------*/
            $table->string('name', 255);          // Title
            $table->string('slug', 255)->unique();
            $table->longText('description')->nullable(); // Article content
            // search text for making search very fast
            $table->longText('search_text')->nullable();


            /* --------------------
            | Relations
            |--------------------*/
            $table->foreignId('article_category_id')
                ->nullable()
                ->constrained('article_categories')
                ->nullOnDelete();

            $table->foreignId('article_language_id')
                ->nullable()
                ->constrained('article_languages')
                ->nullOnDelete();

            /* --------------------
            | Article Source
            |--------------------*/
            $table->tinyInteger('type')->comment(
                '0=manual, 1=file_upload, 2=ai_generated'
            );

            // $table->string('source_file', 255)->nullable()
            //     ->comment('Uploaded file name or source reference');

            /* --------------------
            | SEO (Indexed)
            |--------------------*/
            // $table->string('meta_title', 255)->nullable();
            // $table->string('meta_description', 255)->nullable();
            // $table->text('meta_keywords')->nullable();

            /* --------------------
            | Status & Lifecycle
            |--------------------*/
            $table->tinyInteger('status')->default(0)
                ->comment('0=unused, 1=used, 2=archived');


            $table->tinyInteger('is_lock')
                ->default(0)
                ->after('status')
                ->comment('0 = free, 1 = locked');


            $table->timestamp('used_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            /* --------------------
            | Admin Id
            |--------------------*/

            $table->foreignId('admin_id')
                ->constrained('admins')
                ->cascadeOnDelete();

            // $table->boolean('is_ai_processed')->default(false);

            $table->timestamps();
            $table->softDeletes();

            /* --------------------
            | Indexes (BIG DATA OPTIMIZATION)
            |--------------------*/
            $table->index('status');
            $table->index('type');
            $table->index('article_category_id');
            $table->index('article_language_id');
            $table->index('admin_id');
            $table->index('created_at');
        });

        DB::statement("
          ALTER TABLE articles 
          ADD FULLTEXT fulltext_title_content (name, description)");
    }



    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
