    <?php

    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration
    {
        public function up(): void
        {
            Schema::table('schedule_campaigns', function (Blueprint $table) {
                $table->boolean('is_sticky_campaign')->default(false)->after('total_targets');
            });
        }

        public function down(): void
        {
            Schema::table('schedule_campaigns', function (Blueprint $table) {
                $table->dropColumn('is_sticky_campaign');
            });
        }
    };
