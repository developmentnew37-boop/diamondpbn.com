<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domain_status_checks', function (Blueprint $table) {
            $table->boolean('use_authenticated_check')->default(false)->after('update_inventory');
        });
    }

    public function down(): void
    {
        Schema::table('domain_status_checks', function (Blueprint $table) {
            $table->dropColumn('use_authenticated_check');
        });
    }
};
