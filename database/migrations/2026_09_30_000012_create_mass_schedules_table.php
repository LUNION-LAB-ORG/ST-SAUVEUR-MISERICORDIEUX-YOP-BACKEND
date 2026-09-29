<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messes programmées d'une demande (1, 3 ou 9 selon la formule).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('mass_schedules')) {
            return;
        }

        Schema::create('mass_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mess_id')->constrained('messes')->cascadeOnDelete();
            $table->date('date');
            $table->time('time');
            $table->foreignId('time_slot_id')->nullable()->constrained('time_slots')->nullOnDelete();
            $table->string('label', 150)->nullable();
            // Vrai si la messe a été décalée faute de créneau disponible le jour prévu
            $table->boolean('shifted')->default(false);
            $table->timestamps();
            $table->index(['date', 'time_slot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mass_schedules');
    }
};
