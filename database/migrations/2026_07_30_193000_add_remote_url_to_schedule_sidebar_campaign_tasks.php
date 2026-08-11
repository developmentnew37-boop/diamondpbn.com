<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('schedule_sidebar_campaign_tasks', 'remote_url')) {
            Schema::table('schedule_sidebar_campaign_tasks', function (Blueprint $table) {
                $table->string('remote_url', 2048)->nullable()->after('remote_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('schedule_sidebar_campaign_tasks', 'remote_url')) {
            Schema::table('schedule_sidebar_campaign_tasks', function (Blueprint $table) {
                $table->dropColumn('remote_url');
            });
        }
    }
};
