<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Homélies (rattachées à une date liturgique et, optionnellement, à un prêtre).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('homilies')) {
            return;
        }

        Schema::create('homilies', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->foreignId('priest_id')->nullable()->constrained('priests')->nullOnDelete();
            $table->string('title');
            $table->longText('content');
            $table->string('audio_url')->nullable();
            $table->string('status', 20)->default('published')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homilies');
    }
};
