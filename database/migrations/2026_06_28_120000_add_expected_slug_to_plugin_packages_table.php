<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plugin_packages', function (Blueprint $table) {
            $table->string('expected_slug')->nullable()->after('slug');
            $table->index('expected_slug');
        });

        DB::table('plugin_packages')
            ->whereNull('expected_slug')
            ->update(['expected_slug' => DB::raw('slug')]);

        Schema::table('plugin_deployment_items', function (Blueprint $table) {
            $table->string('plugin_file')->nullable()->after('version_after');
            $table->string('resolved_via')->nullable()->after('plugin_file');
        });
    }

    public function down(): void
    {
        Schema::table('plugin_deployment_items', function (Blueprint $table) {
            $table->dropColumn(['plugin_file', 'resolved_via']);
        });

        Schema::table('plugin_packages', function (Blueprint $table) {
            $table->dropIndex(['expected_slug']);
            $table->dropColumn('expected_slug');
        });
    }
};
