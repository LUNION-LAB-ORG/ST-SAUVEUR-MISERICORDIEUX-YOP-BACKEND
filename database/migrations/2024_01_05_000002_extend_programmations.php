<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('programmations', function (Blueprint $table) {
            if (!Schema::hasColumn('programmations', 'image')) {
                $table->string('image', 255)->nullable()->after('name');
            }
            if (!Schema::hasColumn('programmations', 'category')) {
                // exemples : Temps liturgique, Solennité, Fête patronale, Retraite, Mois marial...
                $table->string('category', 100)->nullable()->after('image');
            }
            if (!Schema::hasColumn('programmations', 'location')) {
                $table->string('location', 200)->nullable()->after('description');
            }
            if (!Schema::hasColumn('programmations', 'is_published')) {
                $table->boolean('is_published')->default(true)->after('location')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('programmations', function (Blueprint $table) {
            if (Schema::hasColumn('programmations', 'is_published')) $table->dropColumn('is_published');
            if (Schema::hasColumn('programmations', 'location')) $table->dropColumn('location');
            if (Schema::hasColumn('programmations', 'category')) $table->dropColumn('category');
            if (Schema::hasColumn('programmations', 'image')) $table->dropColumn('image');
        });
    }
};
