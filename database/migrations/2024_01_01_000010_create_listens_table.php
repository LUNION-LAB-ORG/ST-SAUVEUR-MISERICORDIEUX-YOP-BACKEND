<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listens', function (Blueprint $table) {
            $table->id();
            $table->string('type')->nullable();
            $table->string('fullname');
            $table->string('phone')->nullable();
            $table->text('message');
            $table->string('availability')->nullable();
            $table->foreignId('time_slot_id')->nullable()->constrained('time_slots')->nullOnDelete();
            $table->string('request_status')->default('pending'); // pending | accepted | canceled
            $table->timestamp('listen_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listens');
    }
};
