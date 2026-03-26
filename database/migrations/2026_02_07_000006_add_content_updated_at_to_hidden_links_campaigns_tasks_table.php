<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hidden_links_campaigns_tasks', function (Blueprint $table) {
            $table->timestamp('content_updated_at')->nullable()->after('finished_at');
        });
    }

    public function down(): void
    {
        Schema::table('hidden_links_campaigns_tasks', function (Blueprint $table) {
            $table->dropColumn('content_updated_at');
        });
    }
};
