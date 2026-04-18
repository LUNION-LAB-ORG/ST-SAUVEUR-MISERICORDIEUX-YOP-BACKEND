<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed minimum requis pour démarrer le projet sur un environnement vierge.
     *
     * - SettingsSeeder   : valeurs par défaut des paramètres (paroisse, social, horaires)
     *                      éditables ensuite via le dashboard.
     * - AdminUserSeeder  : compte admin par défaut (configurable via .env).
     *
     * Aucune donnée de démo n'est insérée : tout le contenu (curés, mouvements,
     * actualités, événements, dons, etc.) doit être créé via le dashboard.
     */
    public function run(): void
    {
        $this->call([
            SettingsSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
