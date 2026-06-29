<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_secrets', function (Blueprint $table) {
            $table->timestamp('secret_rotated_at')
                ->nullable()
                ->after('last_used_at')
                ->comment('Last time the secret token was rotated');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_secrets', function (Blueprint $table) {
            $table->dropColumn('secret_rotated_at');
        });
    }
};
