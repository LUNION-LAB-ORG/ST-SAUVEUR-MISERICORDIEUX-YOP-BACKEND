<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisations', function (Blueprint $table) {
            $table->id();
            $table->enum('is_parish_member', ['yes', 'no'])->default('yes');
            $table->string('movement')->nullable();
            $table->string('email');
            $table->string('event_type');
            $table->date('date');
            $table->string('start_time');
            $table->string('end_time');
            $table->text('description');
            $table->string('estimated_participants')->nullable();
            $table->string('request_status')->default('pending')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisations');
    }
};
