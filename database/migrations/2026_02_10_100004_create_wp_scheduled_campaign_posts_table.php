<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per (domain, article, scheduled_date). Sent to WP with schedule date; WP stores remote_id and status.
     */
    public function up(): void
    {
        Schema::create('wp_scheduled_campaign_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wp_scheduled_campaign_id');
            $table->unsignedBigInteger('wp_scheduled_campaign_article_id');
            $table->unsignedBigInteger('wp_scheduled_campaign_domain_id');
            $table->foreign('wp_scheduled_campaign_id', 'wp_sc_p_cid_fk')->references('id')->on('wp_scheduled_campaigns')->cascadeOnDelete();
            $table->foreign('wp_scheduled_campaign_article_id', 'wp_sc_p_aid_fk')->references('id')->on('wp_scheduled_campaign_articles')->cascadeOnDelete();
            $table->foreign('wp_scheduled_campaign_domain_id', 'wp_sc_p_did_fk')->references('id')->on('wp_scheduled_campaign_domains')->cascadeOnDelete();

            /** User-chosen date (for reporting/display); may be past or future */
            $table->date('scheduled_date')->index();
            /** Datetime sent to WordPress (future = WP schedules; past = we send “publish now”) */
            $table->timestamp('scheduled_at')->nullable()->index();

            $table->enum('status', ['queued', 'publishing', 'success', 'failed'])->default('queued');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->string('lock_token')->nullable();

            $table->string('remote_id')->nullable()->index();
            /** WordPress status: scheduled | publish | etc. */
            $table->string('remote_status', 32)->nullable();
            $table->timestamp('remote_scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->integer('http_status')->nullable();
            $table->string('remote_title')->nullable();
            $table->string('remote_url')->nullable();
            $table->json('remote_response')->nullable();

            $table->timestamps();

            $table->unique(
                ['wp_scheduled_campaign_article_id', 'wp_scheduled_campaign_domain_id', 'scheduled_date'],
                'wp_sc_posts_article_domain_date_uq'
            );
            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wp_scheduled_campaign_posts');
    }
};
