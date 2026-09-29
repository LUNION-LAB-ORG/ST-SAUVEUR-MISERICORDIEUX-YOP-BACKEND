<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Exceptions au planning hebdomadaire :
 *  - time_slot_id + is_cancelled : annule ce créneau ce jour-là ;
 *  - time_slot_id null : ajoute une célébration ponctuelle.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('schedule_exceptions')) {
            return;
        }

        Schema::create('schedule_exceptions', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->time('start_time')->nullable();
            $table->string('label', 150)->nullable();
            $table->string('location', 150)->nullable();
            $table->boolean('is_cancelled')->default(false);
            $table->foreignId('time_slot_id')->nullable()->constrained('time_slots')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_exceptions');
    }
};
