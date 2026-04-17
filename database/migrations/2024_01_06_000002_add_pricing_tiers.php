<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'pricing_tiers')) {
                $table->json('pricing_tiers')->nullable()->after('price');
            }
        });

        Schema::table('organisations', function (Blueprint $table) {
            if (!Schema::hasColumn('organisations', 'pricing_tiers')) {
                $table->json('pricing_tiers')->nullable()->after('price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('pricing_tiers');
        });
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn('pricing_tiers');
        });
    }
};
