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


/*
|--------------------------------------------------------------------------
| API Routes (Protected)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    // Users
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
});

// PUBLIC ROUTES (accessible sans authentification)
Route::apiResource('news', NewsController::class)->only('index', 'show');
Route::apiResource('messes', MesseController::class)->only('store');
Route::apiResource('events', EventController::class)->only('index', 'show');
Route::apiResource('pastors', PastorController::class)->only('index', 'show');
Route::apiResource('programmations', ProgrammationController::class)->only('index');
Route::apiResource('services', ServiceController::class)->only('index');
Route::apiResource('participants', ParticipantEventController::class)->only('store');
Route::apiResource('listens', ListenController::class)->only('store');
Route::apiResource('mediations', MediationController::class)->only('index', 'show');

// WAVE PAYMENT (PUBLIC - les paroissiens doivent pouvoir payer sans auth admin)
Route::prefix('wave')->group(function () {
    Route::post('checkout', [WaveCheckoutController::class, 'createSession']);
    Route::get('checkout/{id}/status', [WaveCheckoutController::class, 'checkStatus']);
    Route::post('webhook', [WaveCheckoutController::class, 'webhook']);
});
