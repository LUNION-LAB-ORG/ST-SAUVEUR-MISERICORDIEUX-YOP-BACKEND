<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('mediations', function (Blueprint $table) {
            if (!Schema::hasColumn('mediations', 'image')) {
                $table->string('image', 255)->nullable()->after('category');
            }
            if (!Schema::hasColumn('mediations', 'content')) {
                $table->longText('content')->nullable()->after('image');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mediations', function (Blueprint $table) {
            if (Schema::hasColumn('mediations', 'content')) {
                $table->dropColumn('content');
            }
            if (Schema::hasColumn('mediations', 'image')) {
                $table->dropColumn('image');
            }
        });
    }
};
