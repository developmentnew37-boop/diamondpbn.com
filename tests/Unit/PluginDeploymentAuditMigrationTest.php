<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PluginDeploymentAuditMigrationTest extends TestCase
{
    protected function tearDown(): void
    {
        Schema::dropIfExists('plugin_deployment_items');

        parent::tearDown();
    }

    public function test_audit_migration_adds_and_removes_audit_columns(): void
    {
        Schema::create('plugin_deployment_items', function (Blueprint $table) {
            $table->id();
            $table->string('message')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
        });

        $migration = require database_path(
            'migrations/2026_07_19_010000_add_audit_fields_to_plugin_deployment_items.php'
        );
        $migration->up();

        $this->assertTrue(Schema::hasColumns('plugin_deployment_items', [
            'http_status',
            'request_url',
            'probe_method',
            'audit_trail',
            'retry_count',
        ]));

        $migration->down();

        $this->assertFalse(Schema::hasColumn('plugin_deployment_items', 'audit_trail'));
        $this->assertTrue(Schema::hasColumn('plugin_deployment_items', 'message'));
    }
}
