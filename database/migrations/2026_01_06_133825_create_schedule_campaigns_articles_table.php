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
        // Schema::create('schedule_campaigns_articles', function (Blueprint $table) {
        //     $table->id();

        //     $table->foreignId('schedule_campaign_id')
        //         ->constrained('schedule_campaigns')
        //         ->cascadeOnDelete();

        //     $table->foreignId('article_id')
        //         ->constrained('articles')
        //         ->cascadeOnDelete();

        //     $table->timestamps();

        //     // ✅ ONE unique constraint — SHORT NAME
        //     $table->unique(
        //         ['schedule_campaign_id', 'article_id'],
        //         'sc_articles_campaign_article_uq'
        //     );

        //     // Indexes
        //     $table->index('schedule_campaign_id');
        //     $table->index('article_id');
        // });
        Schema::create('schedule_campaigns_articles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('schedule_campaign_id')
                ->constrained('schedule_campaigns')
                ->cascadeOnDelete();

            $table->foreignId('article_id')
                ->nullable()
                ->constrained('articles')
                ->nullOnDelete();

            // 🔑 Keyword & URL data (copied at schedule time)
            $table->text('keyword')->nullable();
            $table->text('url')->nullable();

            // Explicit type flags
            $table->enum('keyword_type', ['single', 'json'])->default('single');
            $table->enum('url_type', ['single', 'json'])->default('single');

            // SEO options
            $table->boolean('nofollow')->default(false);
            $table->string('media')->nullable(); // image/video/custom media

            $table->timestamps();

            // ✅ Prevent duplicate article per schedule
            $table->unique(
                ['schedule_campaign_id', 'article_id'],
                'sc_articles_campaign_article_uq'
            );

            // Indexes
            $table->index('schedule_campaign_id');
            $table->index('article_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_campaigns_articles');
    }
};
