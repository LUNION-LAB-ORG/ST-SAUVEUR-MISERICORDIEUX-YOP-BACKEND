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

    Route::middleware('auth:sanctum')->group(function () {
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
Route::middleware('auth:sanctum')->group(function () {
    // Profil utilisateur connecté
    Route::get('/me', [UserController::class, 'me']);
    Route::post('/me', [UserController::class, 'updateMe']); // POST avec _method=PUT pour multipart

    // Users (admin)
    Route::apiResource('users', UserController::class);

    // Donations
    Route::apiResource('donations', DonationController::class);

    // Events
    Route::apiResource('events', EventController::class)->except('index');

    // Listens
    Route::apiResource('listens', ListenController::class);

    // Mediations
    Route::apiResource('mediations', MediationController::class);

    // Messes
    Route::apiResource('messes', MesseController::class);

    // News
    Route::apiResource('news', NewsController::class)->except('index');

    // Pastors
    Route::apiResource('pastors', PastorController::class)->except('index');

    // Programmations
    Route::apiResource('programmations', ProgrammationController::class)->except('index');

    // Services
    Route::apiResource('services', ServiceController::class)->except('index');

    // Time Slots
    Route::apiResource('time-slots', TimeSlotController::class);

    Route::apiResource('participants', ParticipantEventController::class);

    // Organisations — gestion admin (list/show/update/destroy)
    Route::apiResource('organisations', OrganisationController::class)->except('store');

    // Convertir une demande d'organisation acceptée en événement officiel
    Route::post('/organisations/{id}/convert-to-event', [OrganisationController::class, 'convertToEvent']);

    // Notifications admin
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

    // Paramètres admin (mutations)
    Route::put('/settings', [SettingController::class, 'updateMany']);
    Route::post('/settings/upload-image', [SettingController::class, 'uploadImage']);

    /*
    | Refonte de l'accueil — mutations admin
    | Routes spécifiques déclarées avant les apiResource qui pourraient les capturer.
    */

    // Liturgie du jour (AELF)
    Route::get('/liturgy/days', [LiturgyController::class, 'days']);
    Route::post('/liturgy/import', [LiturgyController::class, 'import']);
    Route::put('/liturgy/{date}', [LiturgyController::class, 'update'])
        ->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}');

    // Contenus éditoriaux
    Route::apiResource('homilies', HomilyController::class)->except('index', 'show');
    Route::apiResource('priests', PriestController::class)->except('index', 'show');
    Route::apiResource('history-milestones', HistoryMilestoneController::class)->except('index', 'show');
    Route::apiResource('announcements', AnnouncementController::class)->except('index', 'show');

    // Projet « Nouvelle église »
    Route::put('/church-project', [ChurchProjectController::class, 'update']);
    Route::post('/church-project/gallery', [ChurchProjectController::class, 'addGalleryImage']);
    Route::delete('/church-project/gallery/{index}', [ChurchProjectController::class, 'removeGalleryImage'])
        ->whereNumber('index');

    // Exceptions au planning hebdomadaire
    Route::apiResource('schedule-exceptions', ScheduleExceptionController::class)->except('index', 'show');

    // Abonnés WhatsApp (export avant la liste)
    Route::get('/subscriptions/export', [SubscriptionController::class, 'export']);
    Route::get('/subscriptions', [SubscriptionController::class, 'index']);
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

// WAVE PAYMENT (PUBLIC - les paroissiens doivent pouvoir payer sans auth admin)
Route::prefix('wave')->group(function () {
    Route::post('checkout', [WaveCheckoutController::class, 'createSession']);
    Route::get('checkout/{id}/status', [WaveCheckoutController::class, 'checkStatus']);
    Route::post('webhook', [WaveCheckoutController::class, 'webhook']);
});
