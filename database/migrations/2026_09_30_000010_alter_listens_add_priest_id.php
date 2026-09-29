<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rendez-vous avec un prêtre : prêtre choisi (facultatif) et message facultatif.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('listens', function (Blueprint $table) {
            if (!Schema::hasColumn('listens', 'priest_id')) {
                $table->foreignId('priest_id')->nullable()->constrained('priests')->nullOnDelete();
            }
        });

        // message facultatif (SQL natif sur MySQL : doctrine/dbal n'est pas installé en production)
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `listens` MODIFY `message` TEXT NULL');
        } else {
            Schema::table('listens', fn (Blueprint $table) => $table->text('message')->nullable()->change());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('listens', 'priest_id')) {
            Schema::table('listens', function (Blueprint $table) {
                // SQLite : la contrainte disparaît avec la colonne (reconstruction de table)
                if (DB::getDriverName() !== 'sqlite') {
                    $table->dropForeign(['priest_id']);
                }
                $table->dropColumn('priest_id');
            });
        }
        // message reste nullable : le rendre obligatoire échouerait sur les lignes sans message
    }
};
