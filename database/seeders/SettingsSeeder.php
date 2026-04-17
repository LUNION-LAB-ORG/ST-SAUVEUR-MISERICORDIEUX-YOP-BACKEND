<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $defaults = [
            // Identité paroisse
            ['key' => 'parish.name', 'value' => 'Paroisse Saint Sauveur Miséricordieux', 'group' => 'parish', 'type' => 'text', 'label' => 'Nom de la paroisse'],
            ['key' => 'parish.description', 'value' => 'Une communauté vivante et accueillante à Yopougon Millionnaire.', 'group' => 'parish', 'type' => 'textarea', 'label' => 'Description courte'],
            ['key' => 'parish.address', 'value' => 'Yopougon Millionnaire, Abidjan', 'group' => 'parish', 'type' => 'text', 'label' => 'Adresse'],
            ['key' => 'parish.phone', 'value' => '+225 07 00 00 00 00', 'group' => 'parish', 'type' => 'phone', 'label' => 'Téléphone'],
            ['key' => 'parish.email', 'value' => 'contact@saintsauveurmisericordieux.org', 'group' => 'parish', 'type' => 'email', 'label' => 'Email de contact'],
            ['key' => 'parish.website', 'value' => '', 'group' => 'parish', 'type' => 'url', 'label' => 'Site web'],

            // Réseaux sociaux
            ['key' => 'social.facebook', 'value' => '', 'group' => 'social', 'type' => 'url', 'label' => 'Facebook'],
            ['key' => 'social.whatsapp', 'value' => '', 'group' => 'social', 'type' => 'url', 'label' => 'WhatsApp (lien ou numéro)'],
            ['key' => 'social.youtube', 'value' => '', 'group' => 'social', 'type' => 'url', 'label' => 'YouTube'],
            ['key' => 'social.instagram', 'value' => '', 'group' => 'social', 'type' => 'url', 'label' => 'Instagram'],

            // Images
            ['key' => 'images.logo', 'value' => '', 'group' => 'images', 'type' => 'image', 'label' => 'Logo'],
            ['key' => 'images.hero', 'value' => '', 'group' => 'images', 'type' => 'image', 'label' => 'Image d\'accueil (hero)'],

            // Horaires
            ['key' => 'hours.mass_sunday', 'value' => 'Dimanche à 09:00', 'group' => 'hours', 'type' => 'text', 'label' => 'Messe dominicale'],
            ['key' => 'hours.mass_weekday', 'value' => 'Lundi au vendredi à 06:30', 'group' => 'hours', 'type' => 'text', 'label' => 'Messes de semaine'],
            ['key' => 'hours.confession', 'value' => 'Samedi de 16:00 à 18:00', 'group' => 'hours', 'type' => 'text', 'label' => 'Confessions'],
            ['key' => 'hours.adoration', 'value' => 'Jeudi à 19:00', 'group' => 'hours', 'type' => 'text', 'label' => 'Adoration eucharistique'],
        ];

        foreach ($defaults as $row) {
            DB::table('settings')->updateOrInsert(
                ['key' => $row['key']],
                array_merge($row, ['updated_at' => $now]),
            );
        }
    }
}
