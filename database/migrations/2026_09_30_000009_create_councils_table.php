<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conseils et services de la paroisse (conseil pastoral, conseil économique…).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('councils')) {
            return;
        }

        Schema::create('councils', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('role')->nullable();
            $table->string('leader_title', 100)->nullable();
            $table->string('leader_name', 150)->nullable();
            $table->string('status', 20)->default('published')->index();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('councils');
    }
};
