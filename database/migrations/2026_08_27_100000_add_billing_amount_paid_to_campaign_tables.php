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

            if (! Schema::hasColumn($tableName, 'billing_amount_paid')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->decimal('billing_amount_paid', 12, 2)
                        ->nullable()
                        ->after('billing_total');
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            if (Schema::hasColumn($tableName, 'billing_amount_paid')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('billing_amount_paid');
                });
            }
        }
    }
};
