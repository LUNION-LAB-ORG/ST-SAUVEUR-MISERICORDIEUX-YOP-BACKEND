<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Annonces paroissiales (avec fenêtre de visibilité).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('announcements')) {
            return;
        }

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('category', 100)->nullable()->index();
            $table->string('title');
            $table->longText('content');
            $table->string('contact')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->date('visible_from')->nullable();
            $table->date('visible_until')->nullable();
            $table->string('status', 20)->default('published')->index();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
