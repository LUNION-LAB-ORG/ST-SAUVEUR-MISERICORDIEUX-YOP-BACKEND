<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participant_events', function (Blueprint $table) {
            if (!Schema::hasColumn('participant_events', 'tier_label')) {
                $table->string('tier_label')->nullable()->after('amount')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('participant_events', function (Blueprint $table) {
            $table->dropIndex(['tier_label']);
            $table->dropColumn('tier_label');
        });
    }
};
