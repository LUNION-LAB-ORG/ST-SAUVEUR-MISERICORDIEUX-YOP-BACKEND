<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * « J'aime » sur les commentaires : un par appareil.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('comment_likes')) {
            return;
        }

        Schema::create('comment_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained('publication_comments')->cascadeOnDelete();
            $table->string('device_hash', 64);
            $table->timestamps();
            $table->unique(['comment_id', 'device_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comment_likes');
    }
};
