<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_posts', function (Blueprint $table) {
            $table->boolean('auto_replace_domains')->default(false)->after('dispatch_generation');
        });

        $postIds = DB::table('campaign_domain_replacements')
            ->whereNotNull('campaign_post_id')
            ->distinct()
            ->pluck('campaign_post_id');

        if ($postIds->isNotEmpty()) {
            DB::table('campaign_posts')
                ->whereIn('id', $postIds)
                ->update(['auto_replace_domains' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('campaign_posts', function (Blueprint $table) {
            $table->dropColumn('auto_replace_domains');
        });
    }
};
