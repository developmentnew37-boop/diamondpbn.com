<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * MySQL can auto-update the first TIMESTAMP column (ON UPDATE CURRENT_TIMESTAMP),
     * which overwrites schedule_at when the row is updated (e.g. on publish).
     * Change schedule_at to DATETIME so the original scheduled date is preserved.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE schedule_campaigns_posts MODIFY schedule_at DATETIME NOT NULL');
        } else {
            Schema::table('schedule_campaigns_posts', function (Blueprint $table) {
                $table->dateTime('schedule_at')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE schedule_campaigns_posts MODIFY schedule_at TIMESTAMP NOT NULL');
        } else {
            Schema::table('schedule_campaigns_posts', function (Blueprint $table) {
                $table->timestamp('schedule_at')->nullable(false)->change();
            });
        }
    }
};
