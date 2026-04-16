<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('is_paid')->default(false)->after('image');
            $table->decimal('price', 10, 2)->nullable()->after('is_paid');
            $table->unsignedInteger('max_participants')->nullable()->after('price');
            $table->dateTime('registration_deadline')->nullable()->after('max_participants');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['is_paid', 'price', 'max_participants', 'registration_deadline']);
        });
    }
};
