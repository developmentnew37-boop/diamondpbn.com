<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('local_client_manual_billings')) {
            return;
        }

        Schema::table('local_client_manual_billings', function (Blueprint $table) {
            $table->text('keyword')->nullable()->change();
            $table->text('target_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('local_client_manual_billings')) {
            return;
        }

        Schema::table('local_client_manual_billings', function (Blueprint $table) {
            $table->string('keyword', 500)->nullable()->change();
            $table->string('target_url', 2000)->nullable()->change();
        });
    }
};
