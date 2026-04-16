<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programmations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('date_at');
            $table->time('started_at');
            $table->time('ended_at')->nullable();
            $table->text('description');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programmations');
    }
};
