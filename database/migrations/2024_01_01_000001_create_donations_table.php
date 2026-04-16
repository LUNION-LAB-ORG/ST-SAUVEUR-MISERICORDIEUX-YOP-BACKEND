<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->string('donator');
            $table->decimal('amount', 12, 2);
            $table->string('project');
            $table->string('paymethod');
            $table->string('paytransaction')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('donation_at');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
