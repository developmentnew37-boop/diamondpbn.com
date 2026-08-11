<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'campaigns',
        'sidebar_campaigns',
        'hidden_links_campaigns',
        'schedule_campaigns',
        'schedule_sidebar_campaigns',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $statusIndex = substr($tableName, 0, 20).'_lc_status_idx';
                $createdIndex = substr($tableName, 0, 20).'_lc_created_idx';

                if (! Schema::hasIndex($tableName, $statusIndex)) {
                    $table->index(['local_client_id', 'billing_payment_status'], $statusIndex);
                }

                if (! Schema::hasIndex($tableName, $createdIndex)) {
                    $table->index(['local_client_id', 'created_at'], $createdIndex);
                }
            });
        }

        if (Schema::hasTable('local_client_payment_events') && ! Schema::hasIndex('local_client_payment_events', 'lc_payment_events_client_created_idx')) {
            Schema::table('local_client_payment_events', function (Blueprint $table) {
                $table->index(['local_client_id', 'created_at'], 'lc_payment_events_client_created_idx');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropIndex(substr($tableName, 0, 20).'_lc_status_idx');
                $table->dropIndex(substr($tableName, 0, 20).'_lc_created_idx');
            });
        }

        if (Schema::hasTable('local_client_payment_events')) {
            Schema::table('local_client_payment_events', function (Blueprint $table) {
                $table->dropIndex('lc_payment_events_client_created_idx');
            });
        }
    }
};
