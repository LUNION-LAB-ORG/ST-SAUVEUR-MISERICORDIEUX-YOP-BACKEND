<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            if (!Schema::hasColumn('services', 'content')) {
                $table->longText('content')->nullable()->after('description');
            }
            if (!Schema::hasColumn('services', 'leader')) {
                $table->string('leader', 150)->nullable()->after('content');
            }
            if (!Schema::hasColumn('services', 'schedule')) {
                $table->string('schedule', 255)->nullable()->after('leader');
            }
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            if (Schema::hasColumn('services', 'schedule')) $table->dropColumn('schedule');
            if (Schema::hasColumn('services', 'leader')) $table->dropColumn('leader');
            if (Schema::hasColumn('services', 'content')) $table->dropColumn('content');
        });
    }
};
