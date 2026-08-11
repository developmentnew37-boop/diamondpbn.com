<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        if (! Schema::hasColumn('sidebar_campaigns', 'converted_to_schedule_sidebar_campaign_id')) {

            Schema::table('sidebar_campaigns', function (Blueprint $table) {

                $table->unsignedBigInteger('converted_to_schedule_sidebar_campaign_id')->nullable();

                $table->timestamp('conversion_locked_at')->nullable();

            });

        } elseif (! Schema::hasColumn('sidebar_campaigns', 'conversion_locked_at')) {

            Schema::table('sidebar_campaigns', function (Blueprint $table) {

                $table->timestamp('conversion_locked_at')->nullable();

            });

        }

        if (! Schema::hasColumn('schedule_sidebar_campaigns', 'converted_from_sidebar_campaign_id')) {

            Schema::table('schedule_sidebar_campaigns', function (Blueprint $table) {

                $table->unsignedBigInteger('converted_from_sidebar_campaign_id')->nullable();

                $table->date('conversion_run_date')->nullable();

                $table->string('conversion_mode', 32)->nullable();

                $table->string('conversion_pipeline_status', 32)->nullable();

            });

        } else {

            Schema::table('schedule_sidebar_campaigns', function (Blueprint $table) {

                if (! Schema::hasColumn('schedule_sidebar_campaigns', 'conversion_run_date')) {

                    $table->date('conversion_run_date')->nullable();

                }

                if (! Schema::hasColumn('schedule_sidebar_campaigns', 'conversion_mode')) {

                    $table->string('conversion_mode', 32)->nullable();

                }

                if (! Schema::hasColumn('schedule_sidebar_campaigns', 'conversion_pipeline_status')) {

                    $table->string('conversion_pipeline_status', 32)->nullable();

                }

            });

        }

        if (! Schema::hasColumn('schedule_sidebar_campaign_tasks', 'source_sidebar_campaign_task_id')) {

            Schema::table('schedule_sidebar_campaign_tasks', function (Blueprint $table) {

                $table->unsignedBigInteger('source_sidebar_campaign_task_id')->nullable();

                $table->boolean('is_converted_live')->default(false);

                $table->string('conversion_phase', 32)->nullable();

                $table->date('conversion_publish_date')->nullable();

                $table->text('last_conversion_error')->nullable();

                $table->timestamp('last_conversion_attempt_at')->nullable();

                $table->string('remote_status', 32)->nullable();

            });

        } else {

            Schema::table('schedule_sidebar_campaign_tasks', function (Blueprint $table) {

                if (! Schema::hasColumn('schedule_sidebar_campaign_tasks', 'is_converted_live')) {

                    $table->boolean('is_converted_live')->default(false);

                }

                if (! Schema::hasColumn('schedule_sidebar_campaign_tasks', 'conversion_phase')) {

                    $table->string('conversion_phase', 32)->nullable();

                }

                if (! Schema::hasColumn('schedule_sidebar_campaign_tasks', 'conversion_publish_date')) {

                    $table->date('conversion_publish_date')->nullable();

                }

                if (! Schema::hasColumn('schedule_sidebar_campaign_tasks', 'last_conversion_error')) {

                    $table->text('last_conversion_error')->nullable();

                }

                if (! Schema::hasColumn('schedule_sidebar_campaign_tasks', 'last_conversion_attempt_at')) {

                    $table->timestamp('last_conversion_attempt_at')->nullable();

                }

                if (! Schema::hasColumn('schedule_sidebar_campaign_tasks', 'remote_status')) {

                    $table->string('remote_status', 32)->nullable();

                }

            });

        }

        if (! $this->indexExists('sidebar_campaigns', 'sidebar_campaigns_converted_to_ssc_id_index')) {

            Schema::table('sidebar_campaigns', function (Blueprint $table) {

                $table->index('converted_to_schedule_sidebar_campaign_id', 'sidebar_campaigns_converted_to_ssc_id_index');

            });

        }

        if (! $this->foreignKeyExists('schedule_sidebar_campaigns', 'ssc_converted_from_sidebar_campaign_id_foreign')) {

            Schema::table('schedule_sidebar_campaigns', function (Blueprint $table) {

                $table->foreign('converted_from_sidebar_campaign_id', 'ssc_converted_from_sidebar_campaign_id_foreign')

                    ->references('id')

                    ->on('sidebar_campaigns')

                    ->nullOnDelete();

            });

        }

        if (! $this->foreignKeyExists('schedule_sidebar_campaign_tasks', 'ssct_source_sidebar_campaign_task_id_foreign')) {

            Schema::table('schedule_sidebar_campaign_tasks', function (Blueprint $table) {

                $table->foreign('source_sidebar_campaign_task_id', 'ssct_source_sidebar_campaign_task_id_foreign')

                    ->references('id')

                    ->on('sidebar_campaign_tasks')

                    ->nullOnDelete();

            });

        }

        if (! $this->indexExists('schedule_sidebar_campaign_tasks', 'ssct_converted_due_idx')) {

            Schema::table('schedule_sidebar_campaign_tasks', function (Blueprint $table) {

                $table->index(['is_converted_live', 'conversion_phase', 'schedule_at'], 'ssct_converted_due_idx');

            });

        }

    }

    public function down(): void
    {

        if ($this->indexExists('schedule_sidebar_campaign_tasks', 'ssct_converted_due_idx')) {

            Schema::table('schedule_sidebar_campaign_tasks', function (Blueprint $table) {

                $table->dropIndex('ssct_converted_due_idx');

            });

        }

        $this->dropForeignOnColumn('schedule_sidebar_campaign_tasks', 'source_sidebar_campaign_task_id');

        $this->dropForeignOnColumn('schedule_sidebar_campaigns', 'converted_from_sidebar_campaign_id');

        if ($this->indexExists('sidebar_campaigns', 'sidebar_campaigns_converted_to_ssc_id_index')) {

            Schema::table('sidebar_campaigns', function (Blueprint $table) {

                $table->dropIndex('sidebar_campaigns_converted_to_ssc_id_index');

            });

        }

        if (Schema::hasColumn('schedule_sidebar_campaign_tasks', 'source_sidebar_campaign_task_id')) {

            Schema::table('schedule_sidebar_campaign_tasks', function (Blueprint $table) {

                $table->dropColumn([

                    'source_sidebar_campaign_task_id',

                    'is_converted_live',

                    'conversion_phase',

                    'conversion_publish_date',

                    'last_conversion_error',

                    'last_conversion_attempt_at',

                    'remote_status',

                ]);

            });

        }

        if (Schema::hasColumn('schedule_sidebar_campaigns', 'converted_from_sidebar_campaign_id')) {

            Schema::table('schedule_sidebar_campaigns', function (Blueprint $table) {

                $table->dropColumn([

                    'converted_from_sidebar_campaign_id',

                    'conversion_run_date',

                    'conversion_mode',

                    'conversion_pipeline_status',

                ]);

            });

        }

        if (Schema::hasColumn('sidebar_campaigns', 'converted_to_schedule_sidebar_campaign_id')) {

            Schema::table('sidebar_campaigns', function (Blueprint $table) {

                $table->dropColumn(['converted_to_schedule_sidebar_campaign_id', 'conversion_locked_at']);

            });

        }

    }

    private function foreignKeyExists(string $table, string $constraintName): bool
    {

        foreach (Schema::getForeignKeys($table) as $foreignKey) {

            if (($foreignKey['name'] ?? null) === $constraintName) {

                return true;

            }

        }

        return false;

    }

    private function indexExists(string $table, string $indexName): bool
    {

        return Schema::hasIndex($table, $indexName);

    }

    private function dropForeignOnColumn(string $table, string $column): void
    {

        if (! Schema::hasColumn($table, $column)) {

            return;

        }

        $named = match ($table) {

            'schedule_sidebar_campaign_tasks' => 'ssct_source_sidebar_campaign_task_id_foreign',

            'schedule_sidebar_campaigns' => 'ssc_converted_from_sidebar_campaign_id_foreign',

            default => null,

        };

        if ($named && $this->foreignKeyExists($table, $named)) {

            Schema::table($table, function (Blueprint $blueprint) use ($named) {

                $blueprint->dropForeign($named);

            });

            return;

        }

        try {

            Schema::table($table, function (Blueprint $blueprint) use ($column) {

                $blueprint->dropForeign([$column]);

            });

        } catch (\Throwable) {

            // FK may not exist (e.g. partial migration).

        }

    }
};
