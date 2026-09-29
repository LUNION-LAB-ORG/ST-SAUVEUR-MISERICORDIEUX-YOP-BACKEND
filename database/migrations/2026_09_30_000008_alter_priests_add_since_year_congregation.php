<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Équipe pastorale : « en poste depuis » et congrégation / diocèse.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('priests', function (Blueprint $table) {
            if (!Schema::hasColumn('priests', 'since_year')) {
                $table->unsignedSmallInteger('since_year')->nullable();
            }
            if (!Schema::hasColumn('priests', 'congregation')) {
                $table->string('congregation')->nullable();
            }
        });
    }

    public function down(): void
    {
        $columns = array_values(array_filter(
            ['since_year', 'congregation'],
            fn ($column) => Schema::hasColumn('priests', $column)
        ));
        if ($columns) {
            Schema::table('priests', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
