<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('local_client_manual_billings')) {
            return;
        }

        if (! Schema::hasColumn('local_client_manual_billings', 'report_token')) {
            Schema::table('local_client_manual_billings', function (Blueprint $table) {
                $table->string('report_token', 64)->nullable()->unique()->after('campaign_no');
            });
        }

        $ids = DB::table('local_client_manual_billings')
            ->whereNull('report_token')
            ->orderBy('id')
            ->pluck('id');

        foreach ($ids as $id) {
            DB::table('local_client_manual_billings')
                ->where('id', $id)
                ->update(['report_token' => Str::random(64)]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('local_client_manual_billings')) {
            return;
        }

        if (Schema::hasColumn('local_client_manual_billings', 'report_token')) {
            Schema::table('local_client_manual_billings', function (Blueprint $table) {
                $table->dropUnique(['report_token']);
                $table->dropColumn('report_token');
            });
        }
    }
};
