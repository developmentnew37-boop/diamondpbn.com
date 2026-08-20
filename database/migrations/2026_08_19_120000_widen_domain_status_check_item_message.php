<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('domain_status_check_items')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE domain_status_check_items MODIFY message TEXT NULL');

            return;
        }

        if ($driver === 'sqlite') {
            // SQLite affinity already stores long strings; no-op for tests / sqlite.
            return;
        }

        DB::statement('ALTER TABLE domain_status_check_items ALTER COLUMN message TYPE TEXT');
    }

    public function down(): void
    {
        if (! Schema::hasTable('domain_status_check_items')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE domain_status_check_items MODIFY message VARCHAR(255) NULL');
        }
    }
};
