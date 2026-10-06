<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plugin_deployments', function (Blueprint $table) {
            $table->uuid('batch_uuid')->nullable()->after('uuid');
            $table->unsignedSmallInteger('batch_position')->nullable()->after('batch_uuid');
            $table->unsignedSmallInteger('batch_size')->nullable()->after('batch_position');
            $table->index('batch_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('plugin_deployments', function (Blueprint $table) {
            $table->dropIndex(['batch_uuid']);
            $table->dropColumn(['batch_uuid', 'batch_position', 'batch_size']);
        });
    }
};
