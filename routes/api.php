<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NewsController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\MesseController;
use App\Http\Controllers\Api\ListenController;
use App\Http\Controllers\Api\PastorController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\DonationController;
use App\Http\Controllers\Api\TimeSlotController;
use App\Http\Controllers\Api\MediationController;
use App\Http\Controllers\Api\ProgrammationController;
use App\Http\Controllers\Api\ParticipantEventController;
use App\Http\Controllers\Api\WaveCheckoutController;
use App\Http\Controllers\Api\OrganisationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\LiturgyController;
use App\Http\Controllers\Api\HomilyController;
use App\Http\Controllers\Api\PriestController;
use App\Http\Controllers\Api\ChurchProjectController;
use App\Http\Controllers\Api\HistoryMilestoneController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\ScheduleExceptionController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\PublicationController;
use App\Http\Controllers\Api\PublicationCommentController;
use App\Http\Controllers\Api\CouncilController;
use App\Http\Controllers\Api\MassRequestController;
use App\Http\Controllers\Api\Admin\ActivityController as AdminActivityController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\IntegrationController as AdminIntegrationController;
use App\Http\Controllers\Api\Admin\ReorderController as AdminReorderController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/


Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
    });
});



// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });


// PUBLIC : prochaines occurrences disponibles de slots (calculées) — DOIT être avant apiResource
Route::get('/time-slots/available', [\App\Http\Controllers\Api\TimeSlotController::class, 'available']);

/*
|--------------------------------------------------------------------------
| API Routes (Protected)
|--------------------------------------------------------------------------
*/
/*
| Rôles (admin passe partout) — contrat « back-office » §1.
| Les lectures (listes, détails, ?all=1, exports non financiers) restent ouvertes à tout
| utilisateur connecté ; les écritures sont limitées par `role:…`.
*/
Route::middleware(['auth:sanctum', 'active'])->group(function () {
    // Profil utilisateur connecté
    Route::get('/me', [UserController::class, 'me']);
    Route::post('/me', [UserController::class, 'updateMe']); // POST avec _method=PUT pour multipart

    // Utilisateurs : administrateur uniquement (lecture comprise)
    Route::apiResource('users', UserController::class)->middleware('role:admin');

    // Dons : saisie (trésorier, secrétariat) ; export financier (admin, trésorier, secrétariat)
    Route::get('/donations/export', [DonationController::class, 'export'])->middleware('role:treasurer,secretariat');
    Route::apiResource('donations', DonationController::class)->only('index', 'show');
    Route::apiResource('donations', DonationController::class)->only('store', 'update', 'destroy')->middleware('role:treasurer,secretariat');

    // Événements (+ inscriptions) : communication
    Route::get('/events/{id}/participants/export', [ParticipantEventController::class, 'exportForEvent']);
    Route::get('/events/{id}/participants', [ParticipantEventController::class, 'forEvent']);
    Route::apiResource('events', EventController::class)->only('store', 'update', 'destroy')->middleware('role:communication');
    Route::apiResource('participants', ParticipantEventController::class)->only('index', 'show');
    Route::apiResource('participants', ParticipantEventController::class)->only('update', 'destroy')->middleware('role:communication');

    // Rendez-vous (écoutes) : prêtre, secrétariat
    Route::apiResource('listens', ListenController::class)->only('index', 'show');
    Route::apiResource('listens', ListenController::class)->only('update', 'destroy')->middleware('role:priest,secretariat');

    // Médiations, actualités, pasteurs (historique), programmations : communication
    Route::apiResource('mediations', MediationController::class)->only('store', 'update', 'destroy')->middleware('role:communication');
    Route::apiResource('news', NewsController::class)->only('store', 'update', 'destroy')->middleware('role:communication');
    Route::apiResource('pastors', PastorController::class)->only('store', 'update', 'destroy')->middleware('role:communication');
    Route::apiResource('programmations', ProgrammationController::class)->only('show');
    Route::apiResource('programmations', ProgrammationController::class)->only('store', 'update', 'destroy')->middleware('role:secretariat,communication');

    // Demandes de messe : secrétariat (export avant la ressource)
    Route::get('/messes/export', [MesseController::class, 'export'])->middleware('role:treasurer,secretariat');
    Route::apiResource('messes', MesseController::class)->only('index', 'show');
    Route::apiResource('messes', MesseController::class)->only('update', 'destroy')->middleware('role:secretariat');

    // Mouvements : admin ; responsable de mouvement = sa seule fiche (contrôlé dans le contrôleur)
    Route::apiResource('services', ServiceController::class)->only('store', 'destroy')->middleware('role:admin');
    Route::apiResource('services', ServiceController::class)->only('update')->middleware('role:movement_leader');

    // Horaires : secrétariat
    Route::apiResource('time-slots', TimeSlotController::class)->only('index', 'show');
    Route::apiResource('time-slots', TimeSlotController::class)->only('store', 'update', 'destroy')->middleware('role:secretariat');

    // Organisations (demandes d'événements) : communication
    Route::apiResource('organisations', OrganisationController::class)->only('index', 'show');
    Route::apiResource('organisations', OrganisationController::class)->only('update', 'destroy')->middleware('role:communication');
    Route::post('/organisations/{id}/convert-to-event', [OrganisationController::class, 'convertToEvent'])->middleware('role:communication');

    // Notifications admin (tout utilisateur connecté)
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

    // Paramètres : droits vérifiés clé par clé dans le contrôleur
    Route::put('/settings', [SettingController::class, 'updateMany']);
    Route::post('/settings/upload-image', [SettingController::class, 'uploadImage']);
    Route::post('/settings/upload-file', [SettingController::class, 'uploadFile']);

    /*
    | Refonte de l'accueil — routes spécifiques avant les apiResource qui pourraient les capturer.
    */

    // Liturgie du jour (AELF) : prêtre
    Route::get('/liturgy/days', [LiturgyController::class, 'days']);
    Route::post('/liturgy/import', [LiturgyController::class, 'import'])->middleware('role:priest');
    Route::put('/liturgy/{date}', [LiturgyController::class, 'update'])
        ->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}')
        ->middleware('role:priest');

    // Homélies : prêtre
    Route::post('/homilies/{id}/audio', [HomilyController::class, 'uploadAudio'])->middleware('role:priest');
    Route::apiResource('homilies', HomilyController::class)->only('store', 'update', 'destroy')->middleware('role:priest');

    // Équipe pastorale et conseils : administrateur
    Route::apiResource('priests', PriestController::class)->only('store', 'update', 'destroy')->middleware('role:admin');
    Route::apiResource('councils', CouncilController::class)->only('store', 'update', 'destroy')->middleware('role:admin');

    // Histoire (jalons) : communication
    Route::apiResource('history-milestones', HistoryMilestoneController::class)->only('store', 'update', 'destroy')->middleware('role:communication');

    // Annonces : secrétariat
    Route::apiResource('announcements', AnnouncementController::class)->only('store', 'update', 'destroy')->middleware('role:secretariat');

    // Projet « Nouvelle église » : trésorier
    Route::middleware('role:treasurer')->group(function () {
        Route::put('/church-project', [ChurchProjectController::class, 'update']);
        Route::post('/church-project/gallery', [ChurchProjectController::class, 'addGalleryImage']);
        Route::delete('/church-project/gallery/{index}', [ChurchProjectController::class, 'removeGalleryImage'])
            ->whereNumber('index');
    });

    // Exceptions au planning hebdomadaire : secrétariat
    Route::apiResource('schedule-exceptions', ScheduleExceptionController::class)->only('store', 'update', 'destroy')->middleware('role:secretariat');

    // Abonnés WhatsApp (lecture ; export et stats avant la liste)
    Route::get('/subscriptions/export', [SubscriptionController::class, 'export']);
    Route::get('/subscriptions/stats', [SubscriptionController::class, 'stats']);
    Route::get('/subscriptions', [SubscriptionController::class, 'index']);

    /*
    | Sous-pages (lot 2)
    */

    // Publications : communication (le prêtre valide = modification)
    Route::apiResource('publications', PublicationController::class)->only('store', 'destroy')->middleware('role:communication');
    Route::apiResource('publications', PublicationController::class)->only('update')->middleware('role:communication,priest');
    Route::middleware('role:communication')->group(function () {
        Route::post('/publications/{id}/gallery', [PublicationController::class, 'addGalleryImage']);
        Route::delete('/publications/{id}/gallery/{index}', [PublicationController::class, 'removeGalleryImage'])
            ->whereNumber('index');
    });

    // Modération des commentaires : communication, prêtre
    Route::get('/comments', [PublicationCommentController::class, 'index']);
    Route::put('/comments/{id}', [PublicationCommentController::class, 'update'])->middleware('role:communication,priest');
    Route::delete('/comments/{id}', [PublicationCommentController::class, 'destroy'])->middleware('role:communication,priest');

    // Demandes de messe : liste du célébrant (lecture) ; déplacement : secrétariat
    Route::get('/mass-schedules', [MassRequestController::class, 'schedules']);
    Route::put('/mass-schedules/{id}', [MassRequestController::class, 'moveSchedule'])->middleware('role:secretariat');

    /*
    | Back-office (lot 3) : /admin/…
    */
    Route::prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'show']);
        Route::get('/activities', [AdminActivityController::class, 'index']);
        Route::get('/integrations', [AdminIntegrationController::class, 'index'])->middleware('role:admin');
        Route::post('/reorder', [AdminReorderController::class, 'store']); // rôle vérifié selon la ressource
        Route::post('/mass-requests', [MassRequestController::class, 'adminStore'])->middleware('role:secretariat');
    });
});

// PUBLIC ROUTES (accessible sans authentification)
Route::apiResource('news', NewsController::class)->only('index', 'show');
Route::apiResource('messes', MesseController::class)->only('store');
Route::apiResource('events', EventController::class)->only('index', 'show');

// Soumission publique d'un don "paroisse" (paiement en espèces)
// Les dons Wave passent par /wave/checkout
Route::post('/donations/public', [DonationController::class, 'publicStore']);

// Inscription publique à un événement (gratuit ou paiement Wave)
Route::post('/events/{id}/register', [ParticipantEventController::class, 'register']);
Route::apiResource('pastors', PastorController::class)->only('index', 'show');
Route::apiResource('programmations', ProgrammationController::class)->only('index');
Route::apiResource('services', ServiceController::class)->only('index', 'show');
Route::apiResource('participants', ParticipantEventController::class)->only('store');
Route::apiResource('listens', ListenController::class)->only('store');
Route::apiResource('mediations', MediationController::class)->only('index', 'show');

// Soumission publique d'une demande d'organisation
Route::apiResource('organisations', OrganisationController::class)->only('store');

// Settings publics (footer, header...)
Route::get('/settings', [SettingController::class, 'index']);
Route::get('/settings/map', [SettingController::class, 'map']);

// REFONTE DE L'ACCUEIL (PUBLIC)
// Liturgie du jour (import AELF à la volée si besoin)
Route::get('/liturgy', [LiturgyController::class, 'show']);

// Horaires de la semaine (créneaux récurrents + exceptions) — avant tout apiResource
Route::get('/schedule/week', [ScheduleController::class, 'week']);

// Projet « Nouvelle église »
Route::get('/church-project', [ChurchProjectController::class, 'show']);

// Contenus publiés (admin authentifié + ?all=1 : tous les statuts)
Route::apiResource('homilies', HomilyController::class)->only('index', 'show');
Route::apiResource('priests', PriestController::class)->only('index', 'show');
Route::apiResource('history-milestones', HistoryMilestoneController::class)->only('index', 'show');
Route::apiResource('announcements', AnnouncementController::class)->only('index', 'show');
Route::apiResource('schedule-exceptions', ScheduleExceptionController::class)->only('index', 'show');

// Abonnement / désabonnement WhatsApp (limité à 10 requêtes par minute)
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/subscriptions', [SubscriptionController::class, 'store']);
    Route::delete('/subscriptions', [SubscriptionController::class, 'destroy']);
});

// SOUS-PAGES (PUBLIC)
// Agenda : calendrier iCalendar d'un événement (id ou slug)
Route::get('/events/{event}/ics', [EventController::class, 'ics']);

// Publications de la communauté
Route::apiResource('publications', PublicationController::class)->only('index', 'show');
Route::get('/publications/{id}/comments', [PublicationCommentController::class, 'publicIndex']);
Route::middleware('throttle:5,1')->post('/publications/{id}/comments', [PublicationCommentController::class, 'store']);
Route::middleware('throttle:30,1')->group(function () {
    Route::get('/publications/{id}/like', [PublicationController::class, 'likeStatus']);
    Route::post('/publications/{id}/like', [PublicationController::class, 'like']);
    Route::post('/comments/{id}/like', [PublicationCommentController::class, 'like']);
});

// Équipe pastorale : conseils et services
Route::apiResource('councils', CouncilController::class)->only('index', 'show');

// Demande de messe (disponibilités avant la route {number})
Route::get('/mass-requests/availability', [MassRequestController::class, 'availability']);
Route::middleware('throttle:10,1')->post('/mass-requests', [MassRequestController::class, 'store']);
Route::get('/mass-requests/{number}/ics', [MassRequestController::class, 'ics']);
Route::get('/mass-requests/{number}', [MassRequestController::class, 'show']);

// WAVE PAYMENT (PUBLIC - les paroissiens doivent pouvoir payer sans auth admin)
Route::prefix('wave')->group(function () {
    Route::post('checkout', [WaveCheckoutController::class, 'createSession']);
    Route::get('checkout/{id}/status', [WaveCheckoutController::class, 'checkStatus']);
    Route::post('webhook', [WaveCheckoutController::class, 'webhook']);
});
