<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inscriptions : nombre de personnes (la jauge compte la somme) et rappel WhatsApp.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('participant_events', function (Blueprint $table) {
            if (!Schema::hasColumn('participant_events', 'attendees')) {
                $table->unsignedInteger('attendees')->default(1);
            }
            if (!Schema::hasColumn('participant_events', 'reminder')) {
                $table->boolean('reminder')->default(true);
            }
        });
    }

    public function down(): void
    {
        $columns = array_values(array_filter(
            ['attendees', 'reminder'],
            fn ($column) => Schema::hasColumn('participant_events', $column)
        ));
        if ($columns) {
            Schema::table('participant_events', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
