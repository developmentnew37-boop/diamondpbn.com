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
            if (! Schema::hasColumn($tableName, 'local_client_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignId('local_client_id')
                        ->nullable()
                        ->after('admin_id')
                        ->constrained('local_clients')
                        ->nullOnDelete();
                    $table->decimal('billing_total', 12, 2)->nullable()->after('local_client_id');
                    $table->string('billing_currency', 3)->nullable()->after('billing_total');
                    $table->json('billing_snapshot')->nullable()->after('billing_currency');
                    $table->enum('billing_payment_status', ['unpaid', 'paid'])->nullable()->after('billing_snapshot');
                    $table->timestamp('billing_paid_at')->nullable()->after('billing_payment_status');
                    $table->foreignId('billing_paid_by_admin_id')
                        ->nullable()
                        ->after('billing_paid_at')
                        ->constrained('admins')
                        ->nullOnDelete();
                    $table->text('billing_payment_note')->nullable()->after('billing_paid_by_admin_id');
                });
            }

            $existingIndexes = collect(Schema::getIndexes($tableName));

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $existingIndexes) {
                $localClientIdx = $this->localClientIndexName($tableName);
                $paymentStatusIdx = $this->paymentStatusIndexName($tableName);

                $hasLocalClientIndex = $existingIndexes->contains(
                    fn (array $idx) => in_array('local_client_id', $idx['columns'] ?? [], true)
                );
                $hasPaymentStatusIndex = $existingIndexes->contains(
                    fn (array $idx) => in_array('billing_payment_status', $idx['columns'] ?? [], true)
                );

                if (! $hasLocalClientIndex) {
                    $table->index('local_client_id', $localClientIdx);
                }

                if (! $hasPaymentStatusIndex) {
                    $table->index('billing_payment_status', $paymentStatusIdx);
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasColumn($tableName, 'local_client_id')) {
                continue;
            }

            $existingIndexes = collect(Schema::getIndexes($tableName));

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $existingIndexes) {
                foreach ($existingIndexes as $idx) {
                    $columns = $idx['columns'] ?? [];
                    if (in_array('local_client_id', $columns, true)) {
                        $table->dropIndex($idx['name']);
                    }
                    if (in_array('billing_payment_status', $columns, true)) {
                        $table->dropIndex($idx['name']);
                    }
                }

                $table->dropForeign(['local_client_id']);
                $table->dropForeign(['billing_paid_by_admin_id']);
                $table->dropColumn([
                    'local_client_id',
                    'billing_total',
                    'billing_currency',
                    'billing_snapshot',
                    'billing_payment_status',
                    'billing_paid_at',
                    'billing_paid_by_admin_id',
                    'billing_payment_note',
                ]);
            });
        }
    }

    private function localClientIndexName(string $tableName): string
    {
        return "{$tableName}_lc_id_idx";
    }

    private function paymentStatusIndexName(string $tableName): string
    {
        return "{$tableName}_bill_pay_st_idx";
    }
};
