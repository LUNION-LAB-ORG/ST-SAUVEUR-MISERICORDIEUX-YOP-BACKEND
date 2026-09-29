<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * « Faire figurer mon nom parmi les bienfaiteurs ».
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            if (!Schema::hasColumn('donations', 'display_name')) {
                $table->boolean('display_name')->default(false)->after('donator');
            }
        });
    }

    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            if (Schema::hasColumn('donations', 'display_name')) {
                $table->dropColumn('display_name');
            }
        });
    }
};
