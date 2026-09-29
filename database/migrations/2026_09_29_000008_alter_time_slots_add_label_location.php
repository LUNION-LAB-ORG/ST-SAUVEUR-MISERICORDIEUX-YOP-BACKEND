<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Libellé (ex. « Messe du soir ») et lieu (ex. « Église ») des créneaux récurrents.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('time_slots', function (Blueprint $table) {
            if (!Schema::hasColumn('time_slots', 'label')) {
                $table->string('label', 150)->nullable()->after('type');
            }
            if (!Schema::hasColumn('time_slots', 'location')) {
                $table->string('location', 150)->nullable()->after('label');
            }
        });
    }

    public function down(): void
    {
        Schema::table('time_slots', function (Blueprint $table) {
            if (Schema::hasColumn('time_slots', 'location')) $table->dropColumn('location');
            if (Schema::hasColumn('time_slots', 'label')) $table->dropColumn('label');
        });
    }
};
