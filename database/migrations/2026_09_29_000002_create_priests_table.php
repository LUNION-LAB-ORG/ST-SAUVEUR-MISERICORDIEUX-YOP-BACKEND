<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Équipe presbytérale (curé, vicaires, pères résidents).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('priests')) {
            return;
        }

        Schema::create('priests', function (Blueprint $table) {
            $table->id();
            $table->string('fullname', 150);
            // Ex. « Curé », « Premier vicaire », « Vicaire », « Père résident »
            $table->string('function', 100);
            $table->string('missions', 500)->nullable();
            $table->longText('biography')->nullable();
            $table->unsignedSmallInteger('ordination_year')->nullable();
            $table->string('photo')->nullable();
            $table->string('status', 20)->default('published')->index();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('priests');
    }
};
