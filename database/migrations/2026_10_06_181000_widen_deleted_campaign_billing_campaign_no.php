<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('local_client_deleted_campaign_billings')) {
            return;
        }

        Schema::table('local_client_deleted_campaign_billings', function (Blueprint $table) {
            $table->string('campaign_no', 191)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('local_client_deleted_campaign_billings')) {
            return;
        }

        Schema::table('local_client_deleted_campaign_billings', function (Blueprint $table) {
            $table->string('campaign_no', 64)->nullable()->change();
        });
    }
};
