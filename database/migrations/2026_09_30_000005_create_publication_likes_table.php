<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * « J'aime » sur les publications : un par appareil (identifiant client haché).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('publication_likes')) {
            return;
        }

        Schema::create('publication_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publication_id')->constrained('publications')->cascadeOnDelete();
            $table->string('device_hash', 64);
            $table->timestamps();
            $table->unique(['publication_id', 'device_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publication_likes');
    }
};
