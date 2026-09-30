<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paramètres du back-office (accueil, histoire, paiement, WhatsApp).
 * insertOrIgnore : ne jamais écraser une valeur déjà saisie.
 */
return new class extends Migration {
    public const HOME_SECTIONS = ['infos', 'horaires', 'parole', 'eglise', 'mouvements', 'actualites', 'histoire', 'equipe', 'whatsapp'];

    private function rows(): array
    {
        $heroText = (string) (DB::table('settings')->where('key', 'parish.description')->value('value') ?? '');
        $sections = json_encode(array_map(fn ($key) => ['key' => $key, 'visible' => true], self::HOME_SECTIONS));

        $rows = [
            ['key' => 'hero.text', 'group' => 'home', 'type' => 'textarea', 'label' => "Phrase d'accueil", 'value' => $heroText],
            ['key' => 'hero.primary_label', 'group' => 'home', 'type' => 'text', 'label' => 'Bouton principal', 'value' => 'Soutenir la construction'],
            ['key' => 'hero.secondary_label', 'group' => 'home', 'type' => 'text', 'label' => 'Bouton secondaire', 'value' => 'Horaires des messes'],
            ['key' => 'home.sections', 'group' => 'home', 'type' => 'json', 'label' => "Sections de l'accueil", 'value' => $sections],
            ['key' => 'history.full_text', 'group' => 'history', 'type' => 'textarea', 'label' => 'Histoire complète', 'value' => ''],
            ['key' => 'pastor_word.full_message', 'group' => 'pastor_word', 'type' => 'textarea', 'label' => 'Message complet du curé', 'value' => ''],
            ['key' => 'payment.aggregator', 'group' => 'payment', 'type' => 'text', 'label' => 'Agrégateur de paiement', 'value' => ''],
            ['key' => 'images.logo_custom', 'group' => 'images', 'type' => 'boolean', 'label' => 'Logo personnalisé actif', 'value' => '0'],
            ['key' => 'payment.api_key', 'group' => 'payment', 'type' => 'secret', 'label' => "Clé d'API de l'agrégateur", 'value' => ''],
        ];

        foreach (['parole' => 'Parole du jour', 'annonces' => 'Annonces', 'rappels' => 'Rappels', 'confirmations' => 'Confirmations'] as $key => $label) {
            $rows[] = ['key' => "whatsapp.auto_{$key}", 'group' => 'whatsapp', 'type' => 'boolean', 'label' => "Envois automatiques : {$label}", 'value' => '1'];
        }

        return $rows;
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
