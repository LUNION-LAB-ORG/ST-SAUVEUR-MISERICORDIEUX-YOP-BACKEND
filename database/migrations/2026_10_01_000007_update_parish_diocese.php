<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La paroisse relève du Diocèse de Yopougon (et non de l'Archidiocèse d'Abidjan).
 * Ne remplace que l'ancienne valeur par défaut : une saisie du back-office est conservée.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')
            ->where('key', 'parish.diocese')
            ->where(fn ($q) => $q->whereNull('value')->orWhere('value', '')
                ->orWhereIn('value', ['Archidiocèse d’Abidjan', "Archidiocèse d'Abidjan"]))
            ->update(['value' => 'Diocèse de Yopougon', 'updated_at' => now()]);

        Cache::forget('settings:all');
    }

    public function down(): void
    {
        // Pas de retour à une valeur erronée
    }
};
