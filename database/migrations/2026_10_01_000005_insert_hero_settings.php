<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bannière d'accueil entièrement pilotable : sur-titre, titre, légende de l'image,
 * lien « Suivre le projet » et destinations des deux boutons.
 * insertOrIgnore : ne jamais écraser une valeur déjà saisie.
 */
return new class extends Migration {
    private function rows(): array
    {
        $valeur = fn (string $key, string $defaut) => (string) (DB::table('settings')->where('key', $key)->value('value') ?: $defaut);

        return [
            ['key' => 'hero.eyebrow', 'group' => 'home', 'type' => 'text', 'label' => 'Sur-titre de la bannière', 'value' => $valeur('parish.tagline', 'Le Sanctuaire de la Miséricorde')],
            ['key' => 'hero.title', 'group' => 'home', 'type' => 'text', 'label' => 'Titre de la bannière', 'value' => $valeur('parish.name', 'Paroisse Saint Sauveur Miséricordieux')],
            ['key' => 'hero.primary_url', 'group' => 'home', 'type' => 'text', 'label' => 'Lien du bouton principal', 'value' => '/nouvelle-eglise'],
            ['key' => 'hero.secondary_url', 'group' => 'home', 'type' => 'text', 'label' => 'Lien du bouton secondaire', 'value' => '/horaires'],
            ['key' => 'hero.image_caption', 'group' => 'home', 'type' => 'text', 'label' => "Légende de l'image", 'value' => "Vue d'architecte de la future église"],
            ['key' => 'hero.link_label', 'group' => 'home', 'type' => 'text', 'label' => "Lien sous l'image", 'value' => 'Suivre le projet'],
            ['key' => 'hero.link_url', 'group' => 'home', 'type' => 'text', 'label' => "Destination du lien sous l'image", 'value' => '/nouvelle-eglise'],
        ];
    }

    public function up(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        $now = now();
        DB::table('settings')->insertOrIgnore(array_map(fn ($row) => $row + ['updated_at' => $now], $this->rows()));
    }

    public function down(): void
    {
        if (Schema::hasTable('settings')) {
            DB::table('settings')->whereIn('key', array_column($this->rows(), 'key'))->delete();
        }
    }
};
