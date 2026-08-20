<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domain_status_check_items', function (Blueprint $table) {
            $table->timestamp('next_retry_at')->nullable()->after('checked_at');
            $table->index(['domain_status_check_id', 'check_status', 'next_retry_at'], 'dsc_items_retry_ready_idx');
        });
    }

    public function down(): void
    {
        Schema::table('domain_status_check_items', function (Blueprint $table) {
            $table->dropIndex('dsc_items_retry_ready_idx');
            $table->dropColumn('next_retry_at');
        });
    }
};
