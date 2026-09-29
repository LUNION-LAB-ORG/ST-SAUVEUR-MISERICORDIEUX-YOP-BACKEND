<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jalons de l'histoire de la paroisse.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('history_milestones')) {
            return;
        }

        Schema::create('history_milestones', function (Blueprint $table) {
            $table->id();
            // Chaîne libre, ex. « 1998 »
            $table->string('year', 20);
            $table->string('title');
            $table->string('status', 20)->default('published')->index();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('history_milestones');
    }
};
