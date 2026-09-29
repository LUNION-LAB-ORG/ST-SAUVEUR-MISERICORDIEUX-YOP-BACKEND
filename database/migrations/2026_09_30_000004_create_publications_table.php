<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Publications de la communauté (albums photo, vidéos, témoignages, articles).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('publications')) {
            return;
        }

        Schema::create('publications', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('type', 20)->default('text')->index(); // photo | video | text
            $table->string('format', 100)->nullable();            // « Album photo », « Vidéo », « Témoignage »…
            $table->string('category', 100)->nullable()->index();
            $table->string('title');
            $table->text('lead')->nullable();
            // Paragraphes séparés par une ligne vide
            $table->longText('body')->nullable();
            $table->text('quote')->nullable();
            $table->string('cover')->nullable();
            $table->json('gallery')->nullable();
            $table->string('video_url')->nullable();
            $table->string('video_duration', 20)->nullable();
            $table->string('author_label')->default('Service communication de la paroisse');
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('likes_count')->default(0);
            $table->dateTime('published_at')->nullable()->index();
            $table->string('status', 20)->default('published')->index();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publications');
    }
};
