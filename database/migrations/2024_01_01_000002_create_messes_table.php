<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messes', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('fullname');
            $table->string('email')->nullable();
            $table->string('phone');
            $table->text('message')->nullable();
            $table->string('request_status')->default('pending');
            $table->decimal('amount', 12, 2)->default(0);
            $table->date('date_at');
            $table->time('time_at');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messes');
    }
};
