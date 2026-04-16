<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('priest_id')->constrained('users')->onDelete('cascade');
            $table->unsignedTinyInteger('weekday'); // 0=Dimanche, 1=Lundi, ..., 6=Samedi
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_available')->nullable()->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_slots');
    }
};
