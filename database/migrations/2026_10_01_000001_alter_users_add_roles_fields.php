<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rôles du back-office : admin | priest | secretariat | communication | treasurer | movement_leader.
 * Les rôles existants « admin » et « priest » sont conservés ; toute autre valeur devient « admin »
 * (aucun accès existant n'est retiré). Ajout du mouvement géré et de la dernière connexion.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'service_id')) {
                $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            }
            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable();
            }
        });

        if (Schema::hasColumn('users', 'role')) {
            DB::table('users')
                ->where(fn ($q) => $q->whereNull('role')->orWhereNotIn('role', ['admin', 'priest']))
                ->update(['role' => 'admin']);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'service_id') && DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['service_id']);
            }
        });

        $columns = array_values(array_filter(
            ['service_id', 'last_login_at'],
            fn ($column) => Schema::hasColumn('users', $column)
        ));
        if ($columns) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn($columns));
        }
        // Les rôles normalisés ne sont pas restaurés (valeurs d'origine inconnues)
    }
};
