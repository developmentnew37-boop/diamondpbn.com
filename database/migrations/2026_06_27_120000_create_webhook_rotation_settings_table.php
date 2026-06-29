<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_rotation_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('rotation_hours')->default(8)
                ->comment('0 = auto-rotation off; otherwise rotate active secrets after this many hours');
            $table->foreignId('updated_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_rotation_settings');
    }
};
