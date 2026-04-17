<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messes', function (Blueprint $table) {
            $table->string('wave_reference')->nullable()->after('request_status')->index();
            $table->string('wave_checkout_id')->nullable()->after('wave_reference')->index();
            $table->string('payment_status')->nullable()->after('wave_checkout_id');
        });
    }

    public function down(): void
    {
        Schema::table('messes', function (Blueprint $table) {
            $table->dropIndex(['wave_reference']);
            $table->dropIndex(['wave_checkout_id']);
            $table->dropColumn(['wave_reference', 'wave_checkout_id', 'payment_status']);
        });
    }
};
