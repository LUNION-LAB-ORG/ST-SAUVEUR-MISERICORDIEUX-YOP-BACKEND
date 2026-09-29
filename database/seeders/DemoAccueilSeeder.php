<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\ChurchProject;
use App\Models\HistoryMilestone;
use App\Models\Homily;
use App\Models\Priest;
use App\Models\ScheduleException;
use App\Models\Service;
use App\Models\Setting;
use App\Models\TimeSlot;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Contenus de démonstration de la maquette « Saint Sauveur Miséricordieux – Accueil ».
 *
 * Sert à vérifier le rendu de la nouvelle page d'accueil. Sans risque pour une
 * base en service : chaque bloc n'est rempli que si le module est encore vide,
 * et aucune donnée existante n'est modifiée. Les textes entre crochets sont les
 * emplacements de la maquette, à remplacer depuis le back-office.
 *
 *   php artisan db:seed --class=DemoAccueilSeeder --force
 */
class DemoAccueilSeeder extends Seeder
{
    public function run(): void
    {
        $this->horaires();
        $this->pretresEtHomelie();
        $this->mouvements();
        $this->projetEglise();
        $this->jalons();
        $this->annonce();
        $this->parametres();
    }

    /** Horaires récurrents (0 = dimanche). Uniquement si aucun créneau de célébration n'existe. */
    private function horaires(): void
    {
        if (TimeSlot::where('type', '!=', 'ecoute')->exists()) {
            $this->command?->warn('Horaires : des créneaux existent déjà, ignoré.');
            return;
        }

        $semaine = [
            ['messe', '06:30', '07:15', 'Messe matinale', 'Église'],
            ['adoration', '17:00', '18:00', 'Adoration du Saint-Sacrement', 'Chapelle'],
            ['messe', '18:30', '19:15', 'Messe du soir', 'Église'],
        ];
        $parJour = [
            1 => $semaine, 2 => $semaine, 3 => $semaine, 4 => $semaine,
            5 => [
                ['messe', '06:30', '07:15', 'Messe matinale', 'Église'],
                ['confession', '16:00', '17:30', 'Confessions', 'Église'],
                ['autre', '18:00', '19:00', 'Chemin de croix', 'Église'],
            ],
            6 => [
                ['confession', '09:00', '11:00', 'Confessions', 'Église'],
                ['autre', '11:00', '12:00', 'Baptêmes', 'Baptistère'],
                ['messe', '18:30', '19:30', 'Messe anticipée du dimanche', 'Église'],
            ],
            0 => [
                ['messe', '07:00', '08:30', 'Première messe dominicale', 'Église'],
                ['messe', '09:30', '11:00', 'Deuxième messe dominicale', 'Église'],
                ['messe', '17:00', '18:30', 'Messe des jeunes', 'Église'],
            ],
        ];

        foreach ($parJour as $weekday => $creneaux) {
            foreach ($creneaux as [$type, $debut, $fin, $libelle, $lieu]) {
                TimeSlot::create([
                    'type' => $type,
                    'weekday' => $weekday,
                    'start_time' => $debut,
                    'end_time' => $fin,
                    'label' => $libelle,
                    'location' => $lieu,
                    'is_available' => true,
                ]);
            }
        }

        // Exemple d'exception : veillée ponctuelle dans trois jours
        if (!ScheduleException::exists()) {
            ScheduleException::create([
                'date' => Carbon::today('Africa/Abidjan')->addDays(3)->toDateString(),
                'start_time' => '20:00',
                'label' => 'Veillée de prière pour la nouvelle église',
                'location' => 'Église',
                'is_cancelled' => false,
            ]);
        }
    }

    private function pretresEtHomelie(): void
    {
        if (!Priest::exists()) {
            $equipe = [
                ['Curé', 'Responsable de la paroisse'],
                ['Premier vicaire', '[Missions confiées]'],
                ['Vicaire', '[Missions confiées]'],
                ['Père résident', '[Mission ou ministère exercé]'],
            ];
            foreach ($equipe as $i => [$fonction, $missions]) {
                Priest::create([
                    'fullname' => '[Père Prénom NOM]',
                    'function' => $fonction,
                    'missions' => $missions,
                    'status' => 'published',
                    'sort_order' => $i,
                ]);
            }
        } else {
            $this->command?->warn('Prêtres : déjà renseignés, ignoré.');
        }

        $aujourdhui = Carbon::today('Africa/Abidjan')->toDateString();
        if (!Homily::whereDate('date', $aujourdhui)->exists()) {
            Homily::create([
                'date' => $aujourdhui,
                'priest_id' => Priest::orderBy('sort_order')->value('id'),
                'title' => 'Des messagers sur notre route',
                'content' => "Michel, Gabriel, Raphaël : trois noms, trois missions. L’un combat pour nous, l’autre annonce, le troisième accompagne et guérit.\n\nÀ Nathanaël, Jésus promet un ciel ouvert. Cette promesse nous rejoint aujourd’hui à Yopougon : Dieu ne cesse d’envoyer des messagers dans nos familles, nos quartiers, nos lieux de travail. Saurons-nous les reconnaître, et devenir à notre tour messagers de sa miséricorde ?",
                'status' => 'published',
            ]);
        }
    }

    /** Mouvements et groupes (table services). Uniquement si aucun mouvement n'existe. */
    private function mouvements(): void
    {
        if (Service::exists()) {
            $this->command?->warn('Mouvements : des mouvements existent déjà, ignoré (renseignez leur catégorie depuis l’admin).');
            return;
        }

        $mouvements = [
            ['Chorale paroissiale', 'Liturgie', 'Elle anime par le chant les messes et les grandes fêtes de la paroisse.', 'La chorale soutient la prière de l’assemblée par le chant liturgique : messes dominicales, solennités, mariages et funérailles. Répétitions régulières, formation vocale et découverte du répertoire.', 'Jeunes et adultes'],
            ['Servants de messe', 'Liturgie', 'Des enfants et des jeunes au service de l’autel pendant les célébrations.', 'Les servants de messe assistent le prêtre à l’autel et apprennent à mieux vivre la liturgie. Formation, tours de service et temps fraternels rythment l’année.', 'Enfants et adolescents'],
            ['Légion de Marie', 'Prière', 'Prière mariale et apostolat auprès des malades, des familles et des personnes seules.', 'Mouvement de laïcs placé sous la protection de la Vierge Marie. Ses membres se réunissent chaque semaine pour prier, puis partent en mission : visites aux malades, aux familles et aux personnes éloignées de l’Église.', 'Adultes'],
            ['Renouveau charismatique', 'Prière', 'Louange, adoration et intercession pour une foi vivante dans l’Esprit Saint.', 'Groupe de prière centré sur la louange, l’écoute de la Parole et l’intercession. Il propose des veillées, des séminaires de vie dans l’Esprit et un accompagnement fraternel.', 'Tous publics'],
            ['Scouts et guides', 'Jeunesse', 'Grandir dans la foi par la vie en équipe, le jeu et le service.', 'Le scoutisme éduque les jeunes à l’autonomie, à la responsabilité et au service, à la lumière de l’Évangile : activités en plein air, camps, projets solidaires.', 'Enfants et jeunes'],
            ['Jeunesse paroissiale', 'Jeunesse', 'Un lieu de rencontre, de formation et d’engagement pour les jeunes.', 'La jeunesse paroissiale rassemble les jeunes autour de temps de prière, de formation, de sport et de culture, et les engage dans la vie de la communauté.', 'Jeunes'],
            ['Équipe Caritas', 'Charité', 'Soutien aux personnes et aux familles en difficulté du quartier.', 'L’équipe Caritas accueille, écoute et accompagne les personnes en difficulté : collectes, distributions, orientation vers les services sociaux, visites.', 'Bénévoles adultes'],
            ['Couples et familles', 'Familles', 'Accompagner les couples et les familles à chaque étape de leur vie.', 'Préparation au mariage, rencontres de couples, soutien aux parents : un espace d’échange et de prière pour faire grandir l’amour conjugal et familial.', 'Couples et parents'],
        ];

        foreach ($mouvements as $i => [$nom, $categorie, $resume, $description, $public]) {
            Service::create([
                'title' => $nom,
                'category' => $categorie,
                'description' => $resume,
                'content' => $description,
                'audience' => $public,
                'schedule' => '[jour, HH:MM]',
                'location' => '[salle]',
                'leader' => '[nom]',
                'whatsapp' => null,
                'status' => 'published',
                'sort_order' => $i,
            ]);
        }
    }

    /** Projet « Nouvelle église » : 38 % comme sur la maquette, si l'objectif n'est pas encore renseigné. */
    private function projetEglise(): void
    {
        $projet = ChurchProject::current();
        if ((int) $projet->goal_amount > 0) {
            $this->command?->warn('Nouvelle église : objectif déjà renseigné, ignoré.');
            return;
        }

        $projet->update([
            'presentation' => '[Présentation du projet : pourquoi une nouvelle église, capacité d’accueil, architecture, calendrier des travaux.]',
            'goal_amount' => 500000000,
            'adjustment_amount' => 190000000,
            'phases' => [
                ['name' => 'Études et permis', 'status' => 'done'],
                ['name' => 'Fondations', 'status' => 'done'],
                ['name' => 'Gros œuvre', 'status' => 'in_progress'],
                ['name' => 'Toiture', 'status' => 'upcoming'],
                ['name' => 'Finitions', 'status' => 'upcoming'],
            ],
        ]);
    }

    private function jalons(): void
    {
        if (HistoryMilestone::exists()) {
            return;
        }

        $jalons = [
            'Premières célébrations de la communauté',
            'Érection en paroisse',
            'Arrivée du curé actuel',
            'Lancement du projet de la nouvelle église',
        ];
        foreach ($jalons as $i => $titre) {
            HistoryMilestone::create(['year' => '[année]', 'title' => $titre, 'status' => 'published', 'sort_order' => $i]);
        }
    }

    private function annonce(): void
    {
        if (Announcement::exists()) {
            return;
        }

        Announcement::create([
            'category' => 'Chantier',
            'title' => 'Quête spéciale pour la construction de la nouvelle église',
            'content' => '[Texte de l’annonce principale : date de la quête, modalités, possibilité de don en ligne par Mobile Money.]',
            'contact' => 'Secrétariat paroissial',
            'is_featured' => true,
            'status' => 'published',
        ]);
    }

    /** Mot du curé : seulement si les paramètres sont vides. */
    private function parametres(): void
    {
        $valeurs = [
            'pastor_word.message' => 'Chers frères et sœurs en Christ, [mot d’accueil du curé].',
            'pastor_word.signature' => '[Père Prénom NOM], curé de la paroisse',
        ];

        foreach ($valeurs as $cle => $valeur) {
            $setting = Setting::find($cle);
            if ($setting && blank($setting->value)) {
                $setting->value = $valeur;
                $setting->save();
            }
        }
    }
}
