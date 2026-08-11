<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('campaigns', 'converted_to_schedule_campaign_id')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->unsignedBigInteger('converted_to_schedule_campaign_id')->nullable();
                $table->timestamp('conversion_locked_at')->nullable();
            });
        } elseif (! Schema::hasColumn('campaigns', 'conversion_locked_at')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->timestamp('conversion_locked_at')->nullable();
            });
        }

        if (! Schema::hasColumn('schedule_campaigns', 'converted_from_campaign_id')) {
            Schema::table('schedule_campaigns', function (Blueprint $table) {
                $table->unsignedBigInteger('converted_from_campaign_id')->nullable();
                $table->date('conversion_run_date')->nullable();
                $table->string('conversion_mode', 32)->nullable();
                $table->string('conversion_pipeline_status', 32)->nullable();
            });
        } else {
            Schema::table('schedule_campaigns', function (Blueprint $table) {
                if (! Schema::hasColumn('schedule_campaigns', 'conversion_run_date')) {
                    $table->date('conversion_run_date')->nullable();
                }
                if (! Schema::hasColumn('schedule_campaigns', 'conversion_mode')) {
                    $table->string('conversion_mode', 32)->nullable();
                }
                if (! Schema::hasColumn('schedule_campaigns', 'conversion_pipeline_status')) {
                    $table->string('conversion_pipeline_status', 32)->nullable();
                }
            });
        }

        if (! Schema::hasColumn('schedule_campaigns_posts', 'source_campaign_post_id')) {
            Schema::table('schedule_campaigns_posts', function (Blueprint $table) {
                $table->unsignedBigInteger('source_campaign_post_id')->nullable();
                $table->boolean('is_converted_live')->default(false);
                $table->string('conversion_phase', 32)->nullable();
                $table->date('conversion_publish_date')->nullable();
                $table->text('last_conversion_error')->nullable();
                $table->timestamp('last_conversion_attempt_at')->nullable();
            });
        } else {
            Schema::table('schedule_campaigns_posts', function (Blueprint $table) {
                if (! Schema::hasColumn('schedule_campaigns_posts', 'is_converted_live')) {
                    $table->boolean('is_converted_live')->default(false);
                }
                if (! Schema::hasColumn('schedule_campaigns_posts', 'conversion_phase')) {
                    $table->string('conversion_phase', 32)->nullable();
                }
                if (! Schema::hasColumn('schedule_campaigns_posts', 'conversion_publish_date')) {
                    $table->date('conversion_publish_date')->nullable();
                }
                if (! Schema::hasColumn('schedule_campaigns_posts', 'last_conversion_error')) {
                    $table->text('last_conversion_error')->nullable();
                }
                if (! Schema::hasColumn('schedule_campaigns_posts', 'last_conversion_attempt_at')) {
                    $table->timestamp('last_conversion_attempt_at')->nullable();
                }
            });
        }

        // Index on campaigns (no FK: avoids circular campaigns <-> schedule_campaigns constraint issues).
        if (! $this->indexExists('campaigns', 'campaigns_converted_to_schedule_campaign_id_index')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->index('converted_to_schedule_campaign_id', 'campaigns_converted_to_schedule_campaign_id_index');
            });
        }

        if (! $this->foreignKeyExists('schedule_campaigns', 'schedule_campaigns_converted_from_campaign_id_foreign')) {
            Schema::table('schedule_campaigns', function (Blueprint $table) {
                $table->foreign('converted_from_campaign_id', 'schedule_campaigns_converted_from_campaign_id_foreign')
                    ->references('id')
                    ->on('campaigns')
                    ->nullOnDelete();
            });
        }

        if (! $this->foreignKeyExists('schedule_campaigns_posts', 'schedule_campaigns_posts_source_campaign_post_id_foreign')) {
            Schema::table('schedule_campaigns_posts', function (Blueprint $table) {
                $table->foreign('source_campaign_post_id', 'schedule_campaigns_posts_source_campaign_post_id_foreign')
                    ->references('id')
                    ->on('campaign_posts')
                    ->nullOnDelete();
            });
        }

        if (! $this->indexExists('schedule_campaigns_posts', 'scp_converted_due_idx')) {
            Schema::table('schedule_campaigns_posts', function (Blueprint $table) {
                $table->index(['is_converted_live', 'conversion_phase', 'schedule_at'], 'scp_converted_due_idx');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('schedule_campaigns_posts', 'scp_converted_due_idx')) {
            Schema::table('schedule_campaigns_posts', function (Blueprint $table) {
                $table->dropIndex('scp_converted_due_idx');
            });
        }

        $this->dropForeignOnColumn('schedule_campaigns_posts', 'source_campaign_post_id');
        $this->dropForeignOnColumn('schedule_campaigns', 'converted_from_campaign_id');
        $this->dropForeignOnColumn('campaigns', 'converted_to_schedule_campaign_id');

        if ($this->indexExists('campaigns', 'campaigns_converted_to_schedule_campaign_id_index')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->dropIndex('campaigns_converted_to_schedule_campaign_id_index');
            });
        }

        if (Schema::hasColumn('schedule_campaigns_posts', 'source_campaign_post_id')) {
            Schema::table('schedule_campaigns_posts', function (Blueprint $table) {
                $table->dropColumn([
                    'source_campaign_post_id',
                    'is_converted_live',
                    'conversion_phase',
                    'conversion_publish_date',
                    'last_conversion_error',
                    'last_conversion_attempt_at',
                ]);
            });
        }

        if (Schema::hasColumn('schedule_campaigns', 'converted_from_campaign_id')) {
            Schema::table('schedule_campaigns', function (Blueprint $table) {
                $table->dropColumn([
                    'converted_from_campaign_id',
                    'conversion_run_date',
                    'conversion_mode',
                    'conversion_pipeline_status',
                ]);
            });
        }

        if (Schema::hasColumn('campaigns', 'converted_to_schedule_campaign_id')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->dropColumn(['converted_to_schedule_campaign_id', 'conversion_locked_at']);
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
            'schedule_campaigns_posts' => 'schedule_campaigns_posts_source_campaign_post_id_foreign',
            'schedule_campaigns' => 'schedule_campaigns_converted_from_campaign_id_foreign',
            'campaigns' => 'campaigns_converted_to_schedule_campaign_id_foreign',
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
