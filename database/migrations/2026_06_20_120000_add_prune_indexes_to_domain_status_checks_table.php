<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domain_status_checks', function (Blueprint $table) {
            $table->index(['admin_id', 'status', 'id'], 'dsc_admin_status_id_idx');
            $table->index('completed_at', 'dsc_completed_at_idx');
            $table->index(['status', 'created_at'], 'dsc_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('domain_status_checks', function (Blueprint $table) {
            $table->dropIndex('dsc_admin_status_id_idx');
            $table->dropIndex('dsc_completed_at_idx');
            $table->dropIndex('dsc_status_created_idx');
        });
    }
};
