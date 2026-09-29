<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $campaignTables = [
        'campaigns',
        'sidebar_campaigns',
        'hidden_links_campaigns',
        'schedule_campaigns',
        'schedule_sidebar_campaigns',
    ];

    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->campaignTables as $table) {
            if (! Schema::hasColumn($table, 'billing_payment_status')) {
                continue;
            }

            DB::statement("ALTER TABLE `{$table}` MODIFY `billing_payment_status` ENUM('unpaid', 'paid', 'semi_paid') NULL");
        }

        if (! Schema::hasTable('local_client_payment_events')) {
            return;
        }

        DB::statement("ALTER TABLE `local_client_payment_events` MODIFY `old_status` ENUM('unpaid', 'paid', 'semi_paid') NULL");
        DB::statement("ALTER TABLE `local_client_payment_events` MODIFY `new_status` ENUM('unpaid', 'paid', 'semi_paid') NOT NULL");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->campaignTables as $table) {
            if (! Schema::hasColumn($table, 'billing_payment_status')) {
                continue;
            }

            DB::statement("UPDATE `{$table}` SET `billing_payment_status` = 'unpaid' WHERE `billing_payment_status` = 'semi_paid'");
            DB::statement("ALTER TABLE `{$table}` MODIFY `billing_payment_status` ENUM('unpaid', 'paid') NULL");
        }

        if (! Schema::hasTable('local_client_payment_events')) {
            return;
        }

        DB::statement("UPDATE `local_client_payment_events` SET `old_status` = 'unpaid' WHERE `old_status` = 'semi_paid'");
        DB::statement("UPDATE `local_client_payment_events` SET `new_status` = 'unpaid' WHERE `new_status` = 'semi_paid'");
        DB::statement("ALTER TABLE `local_client_payment_events` MODIFY `old_status` ENUM('unpaid', 'paid') NULL");
        DB::statement("ALTER TABLE `local_client_payment_events` MODIFY `new_status` ENUM('unpaid', 'paid') NOT NULL");
    }
};
