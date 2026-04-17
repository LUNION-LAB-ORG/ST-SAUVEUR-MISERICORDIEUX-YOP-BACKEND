<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            if (!Schema::hasColumn('organisations', 'title')) {
                $table->string('title')->nullable()->after('id');
            }
            if (!Schema::hasColumn('organisations', 'location_at')) {
                $table->string('location_at')->nullable()->after('description');
            }
            if (!Schema::hasColumn('organisations', 'image')) {
                $table->string('image')->nullable()->after('location_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn(['title', 'location_at', 'image']);
        });
    }
};
