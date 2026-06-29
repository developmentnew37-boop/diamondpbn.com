<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_domains', function (Blueprint $table) {
            $table->string('normalized_domain_name', 255)->nullable()->after('domain_name');
            $table->index(['status', 'normalized_domain_name'], 'pending_domains_status_normalized_idx');
        });

        DB::table('pending_domains')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $normalized = normalizeDomainName($row->domain_name);
                if ($normalized !== '') {
                    DB::table('pending_domains')
                        ->where('id', $row->id)
                        ->update(['normalized_domain_name' => $normalized]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('pending_domains', function (Blueprint $table) {
            $table->dropIndex('pending_domains_status_normalized_idx');
            $table->dropColumn('normalized_domain_name');
        });
    }
};
