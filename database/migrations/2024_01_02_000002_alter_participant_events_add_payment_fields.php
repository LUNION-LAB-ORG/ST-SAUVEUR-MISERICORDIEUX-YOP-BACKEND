<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participant_events', function (Blueprint $table) {
            // pending | succeeded | failed | free
            $table->string('payment_status')->default('free')->after('message');
            $table->string('wave_checkout_id')->nullable()->after('payment_status');
            $table->string('payment_reference')->nullable()->after('wave_checkout_id');
            $table->decimal('amount', 10, 2)->nullable()->after('payment_reference');
        });
    }

    public function down(): void
    {
        Schema::table('participant_events', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'wave_checkout_id', 'payment_reference', 'amount']);
        });
    }
};
