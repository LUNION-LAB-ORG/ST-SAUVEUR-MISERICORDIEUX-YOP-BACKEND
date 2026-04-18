<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('time_slots', function (Blueprint $table) {
            if (!Schema::hasColumn('time_slots', 'type')) {
                // messe | ecoute | confession | adoration | autre
                $table->string('type', 20)->default('ecoute')->after('id')->index();
            }
            if (!Schema::hasColumn('time_slots', 'notes')) {
                $table->string('notes', 255)->nullable()->after('end_time');
            }
            if (!Schema::hasColumn('time_slots', 'capacity')) {
                // null = illimité
                $table->unsignedInteger('capacity')->nullable()->after('notes');
            }
        });

        // Rendre priest_id nullable (les slots messe/confession ne sont pas li\u00e9s \u00e0 un pr\u00eatre sp\u00e9cifique)
        Schema::table('time_slots', function (Blueprint $table) {
            $table->unsignedBigInteger('priest_id')->nullable()->change();
        });

        // Backfill: tous les slots existants restent en type 'ecoute' (cf. usage Listen actuel)
        DB::table('time_slots')->whereNull('type')->update(['type' => 'ecoute']);
    }

    public function down(): void
    {
        Schema::table('time_slots', function (Blueprint $table) {
            if (Schema::hasColumn('time_slots', 'capacity')) $table->dropColumn('capacity');
            if (Schema::hasColumn('time_slots', 'notes')) $table->dropColumn('notes');
            if (Schema::hasColumn('time_slots', 'type')) $table->dropColumn('type');
        });
    }
};
