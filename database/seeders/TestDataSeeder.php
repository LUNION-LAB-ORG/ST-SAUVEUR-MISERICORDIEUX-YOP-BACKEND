<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // --- Pastors (curés, vicaires, équipe) ---
        DB::table('pastors')->insert([
            [
                'fullname'    => 'Père Joseph Kouadio',
                'photo'       => null,
                'started_at'  => '2020-09-01',
                'ended_at'    => null,
                'description' => 'Curé actuel de la paroisse depuis 2020. Diplômé en théologie à l\'Institut Catholique de Paris.',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'fullname'    => 'Père Marc N\'Guessan',
                'photo'       => null,
                'started_at'  => '2021-01-15',
                'ended_at'    => null,
                'description' => 'Vicaire paroissial, responsable de la pastorale des jeunes.',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'fullname'    => 'Père Paul Assamoi',
                'photo'       => null,
                'started_at'  => '2012-06-01',
                'ended_at'    => '2020-08-31',
                'description' => 'Ancien curé de la paroisse (2012-2020), désormais en mission à Bouaké.',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'fullname'    => 'Père Michel Traoré',
                'photo'       => null,
                'started_at'  => '2005-09-01',
                'ended_at'    => '2012-05-31',
                'description' => 'Ancien curé fondateur de nombreux mouvements paroissiaux.',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
        ]);

        // --- Services (mouvements & groupes) ---
        DB::table('services')->insert([
            [
                'title'       => 'Chorale Sainte Cécile',
                'description' => 'La chorale paroissiale anime les messes dominicales et les grandes fêtes liturgiques. Répétitions chaque samedi à 16h.',
                'image'       => null,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'title'       => 'Groupe des Servants de Messe',
                'description' => 'Les servants de messe accompagnent le célébrant lors des offices. Formation pour les enfants à partir de 8 ans.',
                'image'       => null,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'title'       => 'Mouvement Eucharistique des Jeunes',
                'description' => 'Mouvement d\'éveil et d\'éducation à la foi pour les enfants et adolescents. Rencontre chaque dimanche après la messe.',
                'image'       => null,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'title'       => 'Légion de Marie',
                'description' => 'Association pieuse dédiée à la prière et au service pastoral. Réunion chaque mardi à 17h.',
                'image'       => null,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'title'       => 'Renouveau Charismatique',
                'description' => 'Communauté de prière charismatique ouverte à tous. Louange et intercession chaque vendredi à 19h.',
                'image'       => null,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
        ]);

        // --- Mediations (méditations spirituelles) ---
        DB::table('mediations')->insert([
            [
                'title'            => 'La joie de l\'Évangile',
                'date_at'          => $now->copy()->subDays(2),
                'author'           => 'Père Joseph Kouadio',
                'category'         => 'Évangile',
                'mediation_status' => 'published',
                'views'            => 124,
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
            [
                'title'            => 'Marie, mère de l\'Église',
                'date_at'          => $now->copy()->subDays(7),
                'author'           => 'Père Marc N\'Guessan',
                'category'         => 'Mariologie',
                'mediation_status' => 'published',
                'views'            => 87,
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
            [
                'title'            => 'Le carême, chemin de conversion',
                'date_at'          => $now->copy()->subDays(30),
                'author'           => 'Père Joseph Kouadio',
                'category'         => 'Temps liturgique',
                'mediation_status' => 'published',
                'views'            => 212,
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
            [
                'title'            => 'Brouillon à retravailler',
                'date_at'          => $now,
                'author'           => 'Père Joseph Kouadio',
                'category'         => 'Évangile',
                'mediation_status' => 'draft',
                'views'            => 0,
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
        ]);

        // --- Programmations (horaires messes) ---
        DB::table('programmations')->insert([
            [
                'name'        => 'Messe dominicale',
                'date_at'     => $now->copy()->nextWeekday(Carbon::SUNDAY),
                'started_at'  => '09:00:00',
                'ended_at'    => '10:30:00',
                'description' => 'Messe paroissiale du dimanche, célébrée en français.',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'name'        => 'Messe de semaine',
                'date_at'     => $now->copy()->addDay(),
                'started_at'  => '06:30:00',
                'ended_at'    => '07:15:00',
                'description' => 'Messe matinale quotidienne du lundi au vendredi.',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
        ]);

        // --- Listens (demandes d'écoute) ---
        DB::table('listens')->insert([
            [
                'type'           => 'spirituelle',
                'fullname'       => 'Marie Diabaté',
                'phone'          => '+225 07 12 34 56 78',
                'message'        => 'J\'aimerais échanger avec un prêtre sur un discernement vocationnel.',
                'availability'   => 'Samedi matin',
                'time_slot_id'   => null,
                'listen_at'      => null,
                'request_status' => 'pending',
                'created_at'     => $now,
                'updated_at'     => $now,
            ],
            [
                'type'           => 'familiale',
                'fullname'       => 'Jean-Paul Konan',
                'phone'          => '+225 05 98 76 54 32',
                'message'        => 'Difficulté de couple, besoin d\'accompagnement.',
                'availability'   => 'Soir en semaine',
                'time_slot_id'   => null,
                'listen_at'      => $now->copy()->addDays(3),
                'request_status' => 'accepted',
                'created_at'     => $now,
                'updated_at'     => $now,
            ],
            [
                'type'           => 'spirituelle',
                'fullname'       => 'Aya Koffi',
                'phone'          => '+225 01 11 22 33 44',
                'message'        => 'Demande de prière pour la famille.',
                'availability'   => 'Dimanche après-messe',
                'time_slot_id'   => null,
                'listen_at'      => null,
                'request_status' => 'canceled',
                'created_at'     => $now,
                'updated_at'     => $now,
            ],
        ]);

        // --- Donations ---
        DB::table('donations')->insert([
            [
                'donator'         => 'Famille Kouamé',
                'amount'          => 50000,
                'project'         => 'Réfection toiture',
                'paymethod'       => 'wave',
                'paytransaction'  => 'TX-TEST-00001',
                'description'     => 'Don pour soutenir les travaux de réfection.',
                'donation_at'     => $now->copy()->subDays(4),
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
            [
                'donator'         => 'Anonyme',
                'amount'          => 25000,
                'project'         => 'Caisse paroisse',
                'paymethod'       => 'espèces',
                'paytransaction'  => 'ESP-001',
                'description'     => null,
                'donation_at'     => $now->copy()->subDays(1),
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
            [
                'donator'         => 'Olivier N\'Dri',
                'amount'          => 100000,
                'project'         => 'Chorale - instruments',
                'paymethod'       => 'orange_money',
                'paytransaction'  => 'OM-TEST-042',
                'description'     => 'Pour l\'achat d\'un nouveau clavier.',
                'donation_at'     => $now->copy()->subDays(10),
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
        ]);

        // --- Messes (demandes d'intentions) ---
        if (DB::getSchemaBuilder()->hasTable('messes')) {
            DB::table('messes')->insert([
                [
                    'type'           => 'action-de-grace',
                    'fullname'       => 'Famille Tano',
                    'email'          => 'tano@example.com',
                    'phone'          => '+225 07 00 00 00 01',
                    'message'        => 'Anniversaire des 50 ans de mariage.',
                    'request_status' => 'pending',
                    'payment_status' => 'pending',
                    'amount'         => 5000,
                    'date_at'        => $now->copy()->addDays(7),
                    'time_at'        => '09:00:00',
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ],
                [
                    'type'           => 'defunt',
                    'fullname'       => 'Rosalie Aka',
                    'email'          => null,
                    'phone'          => '+225 07 00 00 00 02',
                    'message'        => 'Messe pour le repos de l\'âme de mon père.',
                    'request_status' => 'accepted',
                    'payment_status' => 'succeeded',
                    'amount'         => 5000,
                    'date_at'        => $now->copy()->addDays(3),
                    'time_at'        => '18:00:00',
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ],
            ]);
        }
    }
}
