<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nouvelles clés de paramètres pour la refonte de l'accueil.
 * insertOrIgnore : ne jamais écraser une valeur déjà saisie.
 */
return new class extends Migration {
    private function rows(): array
    {
        return [
            ['key' => 'parish.tagline', 'group' => 'parish', 'type' => 'text', 'label' => 'Devise', 'value' => 'Le Sanctuaire de la Miséricorde'],
            ['key' => 'parish.diocese', 'group' => 'parish', 'type' => 'text', 'label' => 'Diocèse', 'value' => 'Diocèse de Yopougon'],
            ['key' => 'images.church_render', 'group' => 'images', 'type' => 'image', 'label' => "Vue d'architecte de la future église", 'value' => ''],
            ['key' => 'pastor_word.message', 'group' => 'pastor_word', 'type' => 'textarea', 'label' => 'Mot du curé', 'value' => ''],
            ['key' => 'pastor_word.signature', 'group' => 'pastor_word', 'type' => 'text', 'label' => 'Signature du mot du curé', 'value' => ''],
            ['key' => 'pastor_word.photo', 'group' => 'pastor_word', 'type' => 'image', 'label' => 'Photo du curé', 'value' => ''],
            ['key' => 'donation.amounts', 'group' => 'donation', 'type' => 'text', 'label' => 'Montants suggérés (FCFA, séparés par des virgules)', 'value' => '5000,10000,25000,50000,100000,250000'],
            ['key' => 'donation.project_label', 'group' => 'donation', 'type' => 'text', 'label' => "Projet associé aux dons de l'accueil", 'value' => 'Nouvelle église'],
        ];
    }

    public function up(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        $now = now();
        $rows = array_map(fn ($row) => $row + ['updated_at' => $now], $this->rows());

        DB::table('settings')->insertOrIgnore($rows);
    }

    public function down(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')->whereIn('key', array_column($this->rows(), 'key'))->delete();
    }
};
