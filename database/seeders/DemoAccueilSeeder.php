<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\ChurchProject;
use App\Models\Council;
use App\Models\Event;
use App\Models\HistoryMilestone;
use App\Models\Homily;
use App\Models\Priest;
use App\Models\Publication;
use App\Models\ScheduleException;
use App\Models\Service;
use App\Models\Setting;
use App\Models\TimeSlot;
use App\Models\Donation;
use App\Models\Listen;
use App\Models\Mess;
use App\Models\PublicationComment;
use App\Models\User;
use App\Models\WhatsappSubscriber;
use App\Services\MassRequestService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
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
        // Les données de démonstration n'alimentent pas le journal d'activité
        \App\Services\ActivityLogger::withoutLogging(fn () => $this->remplir());
    }

    private function remplir(): void
    {
        $this->horaires();
        $this->pretresEtHomelie();
        $this->mouvements();
        $this->projetEglise();
        $this->jalons();
        $this->annonces();
        $this->parametres();

        // Sous-pages (lot 2)
        $this->capaciteMesses();
        $this->evenements();
        $this->publications();
        $this->conseils();

        // Back-office (lot 3)
        $this->utilisateursDemo();
        $this->demandesDeMesse();
        $this->dons();
        $this->rendezVous();
        $this->commentairesEnAttente();
        $this->abonnes();
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
                    // Capacité d'intentions par messe (demande de messe en ligne)
                    'capacity' => $type === 'messe' ? 10 : null,
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

    /**
     * Annonces de la maquette. Ajoutées une à une (par titre) tant que la base ne contient
     * que des annonces de démonstration : aucune annonce réelle n'est jamais complétée.
     */
    private function annonces(): void
    {
        $annonces = [
            ['Chantier', 'Quête spéciale pour la construction de la nouvelle église', '[Texte de l’annonce principale : date de la quête, modalités, possibilité de don en ligne par Mobile Money.]', 'Secrétariat paroissial', true],
            ['Sacrements', 'Inscriptions au catéchisme et à la préparation aux sacrements', 'Les inscriptions pour le baptême, la première communion et la confirmation sont ouvertes au secrétariat. Munissez-vous de l’extrait de naissance et, le cas échéant, du certificat de baptême.', 'Secrétariat paroissial', false],
            ['Sacrements', 'Préparation au mariage : prochaine session', 'Les fiancés qui souhaitent se marier dans l’année sont invités à s’inscrire au moins six mois avant la date envisagée. La session comprend quatre rencontres avec un couple accompagnateur et un prêtre.', 'Équipe de pastorale familiale', false],
            ['Liturgie', 'Recrutement de lecteurs et de servants de messe', 'L’équipe liturgique accueille de nouveaux lecteurs et servants. Une formation est proposée le samedi après la messe du matin.', 'Équipe liturgique', false],
            ['Vie paroissiale', 'Journée paroissiale de rentrée', 'Toute la communauté est invitée à la journée paroissiale : messe, présentation des mouvements, repas partagé et animations pour les enfants.', 'Conseil pastoral', false],
            ['Vie paroissiale', 'Horaires du secrétariat pendant les congés', '[Horaires d’ouverture exceptionnels du secrétariat paroissial.]', 'Secrétariat paroissial', false],
        ];

        $titres = array_column($annonces, 1);
        if (Announcement::whereNotIn('title', $titres)->exists()) {
            $this->command?->warn('Annonces : des annonces réelles existent, ignoré.');
            return;
        }

        foreach ($annonces as $i => [$categorie, $titre, $contenu, $contact, $aLaUne]) {
            Announcement::firstOrCreate(['title' => $titre], [
                'category' => $categorie,
                'content' => $contenu,
                'contact' => $contact,
                'is_featured' => $aLaUne,
                'status' => 'published',
                'sort_order' => $i,
            ]);
        }
    }

    /** Mot du curé : seulement si les paramètres sont vides. */
    private function parametres(): void
    {
        $valeurs = [
            'pastor_word.message' => 'Chers frères et sœurs en Christ, [mot d’accueil du curé].',
            'pastor_word.signature' => '[Père Prénom NOM], curé de la paroisse',
            'mass.offering_amount' => '2000',
            'parish.office_hours' => 'Lundi au samedi, 8 h – 12 h et 15 h – 18 h',
        ];

        foreach ($valeurs as $cle => $valeur) {
            $setting = Setting::find($cle);
            if ($setting && blank($setting->value)) {
                $setting->value = $valeur;
                $setting->save();
            }
        }
    }

    /** Capacité de 10 intentions sur les créneaux « messe » créés par ce seeder (si non renseignée). */
    private function capaciteMesses(): void
    {
        $libelles = [
            'Messe matinale', 'Messe du soir', 'Messe anticipée du dimanche',
            'Première messe dominicale', 'Deuxième messe dominicale', 'Messe des jeunes',
        ];

        TimeSlot::where('type', 'messe')
            ->whereIn('label', $libelles)
            ->whereNull('capacity')
            ->update(['capacity' => 10]);
    }

    /** Agenda : événement de démonstration + 3 autres, uniquement si aucun événement à venir n'existe. */
    private function evenements(): void
    {
        $aujourdhui = Carbon::today('Africa/Abidjan');
        if (Event::whereDate('date_at', '>=', $aujourdhui->toDateString())->exists()) {
            $this->command?->warn('Agenda : des événements à venir existent déjà, ignoré.');
            return;
        }

        Event::create([
            'title' => 'Messe d’ouverture de l’année pastorale et journée paroissiale',
            'summary' => 'Une journée pour lancer ensemble la nouvelle année pastorale : messe solennelle, présentation des mouvements et repas partagé.',
            'description' => '[Présentation détaillée de la journée : thème de l’année pastorale, invités, organisation pratique.]',
            'category' => 'Événement paroissial',
            'audience' => 'Toute la communauté',
            'date_at' => $aujourdhui->copy()->addDays(10)->toDateString(),
            'time_at' => '09:00',
            'end_time' => '16:00',
            'location_at' => 'Église Saint Sauveur Miséricordieux',
            'programme' => [
                ['time' => '09:00', 'label' => 'Messe d’ouverture présidée par le curé'],
                ['time' => '11:00', 'label' => 'Présentation des mouvements et services'],
                ['time' => '12:30', 'label' => 'Repas partagé'],
                ['time' => '14:00', 'label' => 'Animations pour les enfants et les jeunes'],
                ['time' => '15:30', 'label' => 'Action de grâce et bénédiction finale'],
            ],
            'is_paid' => false,
            'status' => 'published',
        ]);

        $autres = [
            [17, '18:30', '21:00', 'Veillée de prière pour la nouvelle église', 'Prière', 'Toute la communauté', 'Louange, adoration et intercession pour le chantier de la nouvelle église.'],
            [24, '08:00', '13:00', 'Journée de récollection des servants de messe', 'Formation', 'Servants de messe et leurs parents', 'Temps de formation, de prière et de détente pour les servants de messe.'],
            [31, '10:00', '17:00', 'Kermesse paroissiale', 'Vie paroissiale', 'Familles et amis de la paroisse', 'Stands, jeux et spécialités culinaires au profit de la construction de la nouvelle église.'],
        ];
        foreach ($autres as [$jours, $debut, $fin, $titre, $categorie, $public, $resume]) {
            Event::create([
                'title' => $titre,
                'summary' => $resume,
                'description' => $resume,
                'category' => $categorie,
                'audience' => $public,
                'date_at' => $aujourdhui->copy()->addDays($jours)->toDateString(),
                'time_at' => $debut,
                'end_time' => $fin,
                'location_at' => 'Paroisse Saint Sauveur Miséricordieux',
                'programme' => [],
                'is_paid' => false,
                'status' => 'published',
            ]);
        }
    }

    /** Publications de la communauté (dont une vidéo à la une) et deux commentaires publiés. */
    private function publications(): void
    {
        if (Publication::exists()) {
            $this->command?->warn('Publications : déjà renseignées, ignoré.');
            return;
        }

        $maintenant = Carbon::now('Africa/Abidjan');
        $publications = [
            ['video', 'Vidéo', 'Liturgie', 'Retour en vidéo sur la messe de rentrée pastorale', 'Les temps forts de la célébration qui a rassemblé toute la communauté pour ouvrir la nouvelle année.', null, true, 1, '4:32'],
            ['photo', 'Album photo', 'Vie paroissiale', 'La kermesse paroissiale en images', 'Stands, jeux et bonne humeur : retour en photos sur une journée de fête au profit de la nouvelle église.', null, false, 3, null],
            ['text', 'Témoignage', 'Charité', '« L’équipe Caritas m’a aidée à me relever »', 'Une paroissienne raconte comment l’accompagnement de la Caritas a changé son quotidien.', 'On ne m’a pas seulement donné à manger : on m’a redonné confiance.', false, 6, null],
            ['text', 'Article', 'Chantier', 'Nouvelle église : où en est le chantier ?', 'Le gros œuvre avance : point d’étape sur les travaux et les prochaines échéances.', null, false, 9, null],
            ['photo', 'Album photo', 'Sacrements', 'Confirmations : nos jeunes reçoivent l’Esprit Saint', 'Retour en images sur la célébration des confirmations présidée par l’évêque.', null, false, 12, null],
            ['text', 'Article', 'Liturgie', 'La chorale paroissiale fête ses vingt ans', 'Vingt ans de chant au service de la liturgie : histoire, souvenirs et projets de la chorale.', 'Chanter, c’est prier deux fois.', false, 15, null],
        ];

        foreach ($publications as $i => [$type, $format, $categorie, $titre, $chapeau, $citation, $aLaUne, $joursAvant, $duree]) {
            Publication::create([
                'type' => $type,
                'format' => $format,
                'category' => $categorie,
                'title' => $titre,
                'lead' => $chapeau,
                'body' => "[Premier paragraphe du texte de la publication.]\n\n[Deuxième paragraphe du texte de la publication.]",
                'quote' => $citation,
                'video_url' => null, // lien YouTube à renseigner depuis le back-office
                'video_duration' => $duree,
                'is_featured' => $aLaUne,
                'published_at' => $maintenant->copy()->subDays($joursAvant),
                'status' => 'published',
                'sort_order' => $i,
            ]);
        }

        $video = Publication::where('type', 'video')->where('is_featured', true)->first();
        foreach ([
            ['Marie-Claire', 'Quelle belle célébration ! Merci à la chorale et à tous les servants.'],
            ['Jean-Baptiste', 'Que Dieu bénisse notre paroisse pour cette nouvelle année pastorale.'],
        ] as [$auteur, $texte]) {
            $video->comments()->create(['author' => $auteur, 'content' => $texte, 'status' => 'published']);
        }
    }

    /** Conseils et services de la paroisse. */
    private function conseils(): void
    {
        if (Council::exists()) {
            return;
        }

        $conseils = [
            ['Conseil pastoral paroissial', 'Discerne et coordonne les orientations pastorales de la paroisse.', 'Coordinateur'],
            ['Conseil pour les affaires économiques', 'Veille à la bonne gestion des biens et des ressources de la paroisse.', 'Président'],
            ['Comité de construction de la nouvelle église', 'Suit le chantier et la mobilisation des fonds pour la nouvelle église.', 'Président'],
            ['Équipe liturgique', 'Prépare les célébrations et coordonne lecteurs, chorales et servants.', 'Responsable'],
            ['Secrétariat et accueil paroissial', 'Accueille, informe et oriente les fidèles ; enregistre les demandes.', 'Responsable'],
        ];
        foreach ($conseils as $i => [$nom, $role, $titre]) {
            Council::create([
                'name' => $nom,
                'role' => $role,
                'leader_title' => $titre,
                'leader_name' => '[Prénom NOM]',
                'status' => 'published',
                'sort_order' => $i,
            ]);
        }
    }

    /**
     * Un compte de démonstration par rôle (jamais en production). Mot de passe : password123.
     */
    private function utilisateursDemo(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('Comptes de démonstration : ignorés en production.');
            return;
        }

        $comptes = [
            ['secretariat@demo.local', 'Secrétariat (démo)', 'secretariat', null],
            ['pretre@demo.local', 'Père (démo)', 'priest', null],
            ['communication@demo.local', 'Communication (démo)', 'communication', null],
            ['tresorier@demo.local', 'Trésorier (démo)', 'treasurer', null],
            ['mouvement@demo.local', 'Responsable de mouvement (démo)', 'movement_leader', Service::orderBy('sort_order')->orderBy('id')->value('id')],
        ];

        foreach ($comptes as $i => [$email, $nom, $role, $serviceId]) {
            if (User::withTrashed()->where('email', $email)->exists()) {
                continue;
            }
            User::create([
                'name' => $nom,
                'fullname' => $nom,
                'email' => $email,
                'phone' => '+22500000000' . ($i + 1),
                'password' => Hash::make('password123'),
                'role' => $role,
                'status' => 'active',
                'service_id' => $serviceId,
            ]);
        }
    }

    /** Demandes de messe : à traiter, à régler au secrétariat, payées. Si aucune demande en ligne n'existe. */
    private function demandesDeMesse(): void
    {
        if (Mess::whereNotNull('number')->exists()) {
            return;
        }

        $service = app(MassRequestService::class);
        $demandes = [
            ['Action de grâce', 'Famille Kouadio', 'Pour 25 ans de mariage', false, 'single', 'secretariat', 'to_pay', 'pending'],
            ['Repos de l’âme', 'Feu Jean-Baptiste Yao', 'Pour le repos de son âme', false, 'triduum', 'wave', 'succeeded', 'accepted'],
            ['Intention particulière', 'Une intention confidentielle', null, true, 'single', 'secretariat', 'to_pay', 'pending'],
            ['Pour un malade', 'Marie-Thérèse', 'Pour sa guérison', false, 'single', 'wave', 'succeeded', 'pending'],
            ['Action de grâce', 'Promotion 2026 du lycée', 'Réussite aux examens', false, 'single', 'cash', 'succeeded', 'accepted'],
        ];

        foreach ($demandes as $i => [$type, $pourQui, $intention, $confidentiel, $formule, $moyen, $paiement, $statut]) {
            $creneau = $this->prochainCreneauMesse($service, $i + 1);
            if (!$creneau) {
                $this->command?->warn('Demandes de messe : aucun créneau de messe disponible, ignoré.');
                return;
            }
            [$date, $slotId] = $creneau;

            try {
                $service->create([
                    'type' => $type,
                    'intention_type' => $type,
                    'for_whom' => $pourQui,
                    'message' => $intention,
                    'is_confidential' => $confidentiel,
                    'formula' => $formule,
                    'fullname' => '[Demandeur ' . ($i + 1) . ']',
                    'phone' => '+2250700000' . str_pad((string) ($i + 10), 3, '0', STR_PAD_LEFT),
                    'amount' => MassRequestService::FORMULAS[$formule] * 2000,
                    'payment_method' => $moyen,
                    'payment_status' => $paiement,
                    'request_status' => $statut,
                ], $date, $slotId, MassRequestService::FORMULAS[$formule], false);
            } catch (\Illuminate\Validation\ValidationException $e) {
                $this->command?->warn('Demande de messe de démonstration ignorée : ' . collect($e->errors())->flatten()->first());
            }
        }
    }

    /** Prochain créneau de messe non complet à partir de J+offset (14 jours de recherche). */
    private function prochainCreneauMesse(MassRequestService $service, int $offset): ?array
    {
        $jour = Carbon::today('Africa/Abidjan')->addDays($offset);

        for ($i = 0; $i < 14; $i++, $jour->addDay()) {
            foreach ($service->celebratedSlots($jour) as $slot) {
                if ($service->slotInfo($jour, $slot)['status'] !== 'full') {
                    return [$jour->copy(), $slot->id];
                }
            }
        }

        return null;
    }

    /** Dons de la semaine (uniquement si aucun don n'existe). */
    private function dons(): void
    {
        if (Donation::exists()) {
            return;
        }

        $debut = Carbon::now('Africa/Abidjan')->startOfWeek(Carbon::MONDAY);
        $dons = [
            ['Anonyme', 10000, 'Nouvelle église', 'wave', 'succeeded', true],
            ['Famille Koffi', 50000, 'Nouvelle église', 'wave', 'succeeded', true],
            ['[Donateur]', 25000, 'Nouvelle église', 'especes', 'succeeded', false],
            ['[Donateur]', 5000, 'Fonctionnement', 'wave', 'succeeded', false],
            ['[Donateur]', 100000, 'Nouvelle église', 'especes', 'pending', false],
        ];

        foreach ($dons as $i => [$donateur, $montant, $projet, $moyen, $statut, $nomAffiche]) {
            Donation::create([
                'donator' => $donateur,
                'display_name' => $nomAffiche,
                'donation_type' => 'monetaire',
                'amount' => $montant,
                'project' => $projet,
                'paymethod' => $moyen,
                'payment_status' => $statut,
                'donation_at' => $debut->copy()->addHours(9 + $i * 5)->min(Carbon::now('Africa/Abidjan')),
            ]);
        }
    }

    /** Deux rendez-vous en attente (uniquement si aucun rendez-vous n'existe). */
    private function rendezVous(): void
    {
        if (Listen::exists()) {
            return;
        }

        $pretre = Priest::orderBy('sort_order')->value('id');
        foreach ([['Accompagnement spirituel', 'Je souhaite être accompagné pendant ce temps de discernement.'], ['Préparation au mariage', null]] as $i => [$motif, $message]) {
            Listen::create([
                'type' => $motif,
                'fullname' => '[Paroissien ' . ($i + 1) . ']',
                'phone' => '+2250500000' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'message' => $message,
                'priest_id' => $pretre,
                'request_status' => 'pending',
            ]);
        }
    }

    /** Quatre commentaires à modérer (uniquement si aucun commentaire n'est en attente). */
    private function commentairesEnAttente(): void
    {
        if (PublicationComment::where('status', 'pending')->exists()) {
            return;
        }

        $publications = Publication::orderByDesc('published_at')->limit(2)->get();
        if ($publications->isEmpty()) {
            return;
        }

        $commentaires = [
            ['Awa', 'Merci pour ces belles photos, quelle joie de revoir cette journée !'],
            ['Serge', 'Est-il possible d’avoir la vidéo complète de la célébration ?'],
            ['Christelle', 'Que Dieu bénisse tous les bénévoles de la paroisse.'],
            ['Paul', 'Très beau témoignage, merci de l’avoir partagé.'],
        ];
        foreach ($commentaires as $i => [$auteur, $texte]) {
            $publications[$i % $publications->count()]->comments()->create([
                'author' => $auteur,
                'content' => $texte,
                'status' => 'pending',
            ]);
        }
    }

    /** Trois abonnés WhatsApp (uniquement si la liste est vide). */
    private function abonnes(): void
    {
        if (WhatsappSubscriber::exists()) {
            return;
        }

        foreach ([['+2250700000101', ['parole', 'annonces'], 'home'], ['+2250700000102', ['parole'], 'announcements'], ['+2250700000103', ['annonces'], 'mass_request']] as [$tel, $listes, $source]) {
            WhatsappSubscriber::create([
                'phone' => $tel,
                'lists' => $listes,
                'source' => $source,
                'consented_at' => now(),
            ]);
        }
    }
}
