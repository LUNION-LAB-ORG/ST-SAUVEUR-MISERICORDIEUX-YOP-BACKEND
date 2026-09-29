<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Demande de messe en ligne (4 étapes) : numéro SSM-AAAA-NNNN, jeton du reçu,
 * intention, formule (messe unique / triduum / neuvaine), créneau et paiement.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('messes', function (Blueprint $table) {
            if (!Schema::hasColumn('messes', 'number')) {
                $table->string('number', 20)->nullable()->unique();
            }
            if (!Schema::hasColumn('messes', 'access_token')) {
                $table->string('access_token', 40)->nullable();
            }
            if (!Schema::hasColumn('messes', 'intention_type')) {
                $table->string('intention_type', 100)->nullable();
            }
            if (!Schema::hasColumn('messes', 'for_whom')) {
                $table->string('for_whom', 150)->nullable();
            }
            if (!Schema::hasColumn('messes', 'is_confidential')) {
                $table->boolean('is_confidential')->default(false);
            }
            if (!Schema::hasColumn('messes', 'formula')) {
                $table->string('formula', 20)->default('single'); // single | triduum | novena
            }
            if (!Schema::hasColumn('messes', 'masses_count')) {
                $table->unsignedTinyInteger('masses_count')->default(1);
            }
            if (!Schema::hasColumn('messes', 'time_slot_id')) {
                $table->foreignId('time_slot_id')->nullable()->constrained('time_slots')->nullOnDelete();
            }
            if (!Schema::hasColumn('messes', 'will_attend')) {
                $table->boolean('will_attend')->default(false);
            }
            if (!Schema::hasColumn('messes', 'reminder')) {
                $table->boolean('reminder')->default(true);
            }
            if (!Schema::hasColumn('messes', 'payment_method')) {
                $table->string('payment_method', 20)->nullable(); // wave | orange | mtn | moov | card | secretariat
            }
            if (!Schema::hasColumn('messes', 'needs_review')) {
                $table->boolean('needs_review')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('messes', function (Blueprint $table) {
            if (Schema::hasIndex('messes', 'messes_number_unique')) {
                $table->dropUnique(['number']);
            }
            if (Schema::hasColumn('messes', 'time_slot_id') && DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['time_slot_id']);
            }
        });

        $columns = array_values(array_filter([
            'number', 'access_token', 'intention_type', 'for_whom', 'is_confidential', 'formula',
            'masses_count', 'time_slot_id', 'will_attend', 'reminder', 'payment_method', 'needs_review',
        ], fn ($column) => Schema::hasColumn('messes', $column)));

        if ($columns) {
            Schema::table('messes', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
