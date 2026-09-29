<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Liturgy\ImportRequest;
use App\Http\Requests\Liturgy\UpdateRequest;
use App\Http\Resources\LiturgyDayResource;
use App\Models\LiturgyDay;
use App\Services\AelfService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LiturgyController extends Controller
{
    /** Délai avant de retenter un import à la volée après un échec AELF (secondes). */
    private const FAILURE_COOLDOWN = 600;

    public function __construct(protected AelfService $aelf)
    {
    }

    /**
     * Liturgie d'une date (public). GET /liturgy?date=YYYY-MM-DD (défaut : aujourd'hui, Africa/Abidjan)
     *
     * Date absente en base : import à la volée ; si AELF échoue, renvoie la dernière
     * journée importée antérieure avec is_fallback = true ; 404 si rien du tout.
     */
    public function show(Request $request)
    {
        $request->validate(['date' => 'nullable|date_format:Y-m-d']);

        $date = $request->input('date') ?: Carbon::now(AelfService::TIMEZONE)->toDateString();

        $day = LiturgyDay::where('date', $date)->first() ?? $this->importOnTheFly($date);

        if ($day) {
            // 200 explicite (sinon 201 quand la journée vient d'être importée)
            return (new LiturgyDayResource($day))->response()->setStatusCode(200);
        }

        $fallback = LiturgyDay::where('date', '<=', $date)->orderByDesc('date')->first();
        if (!$fallback) {
            return response()->json(['message' => 'Aucune liturgie disponible pour cette date.'], 404);
        }

        return (new LiturgyDayResource($fallback))->fallback()->response()->setStatusCode(200);
    }

    /**
     * Import manuel (admin). Body : { date?: YYYY-MM-DD, days?: 7 }
     */
    public function import(ImportRequest $request): JsonResponse
    {
        $start = $request->filled('date')
            ? Carbon::createFromFormat('!Y-m-d', $request->input('date'), AelfService::TIMEZONE)
            : Carbon::now(AelfService::TIMEZONE)->startOfDay();
        $days = (int) $request->input('days', 7);

        $imported = [];
        $failed   = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i)->toDateString();

            if ($this->aelf->import($date)) {
                $imported[] = $date;
                Cache::forget($this->failureKey($date));
            } else {
                $failed[] = $date;
            }
        }

        return response()->json(['data' => ['imported' => $imported, 'failed' => $failed]]);
    }

    /**
     * Surcharge de la fête / couleur d'une journée (admin). PUT /liturgy/{date}
     */
    public function update(UpdateRequest $request, string $date)
    {
        $day = LiturgyDay::where('date', $date)->firstOrFail();
        $day->update($request->validated());

        return new LiturgyDayResource($day->fresh());
    }

    /**
     * Journées en base (admin). GET /liturgy/days?from=&to=
     */
    public function days(Request $request)
    {
        $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to'   => 'nullable|date_format:Y-m-d',
        ]);

        $query = LiturgyDay::query();
        if ($request->filled('from')) {
            $query->where('date', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->where('date', '<=', $request->input('to'));
        }

        return LiturgyDayResource::collection($query->orderBy('date')->get());
    }

    /**
     * Import à la volée, limité à ±1 an autour d'aujourd'hui et temporisé après un échec
     * (évite de solliciter AELF à chaque requête publique quand l'API est indisponible).
     */
    private function importOnTheFly(string $date): ?LiturgyDay
    {
        $today = Carbon::now(AelfService::TIMEZONE)->startOfDay();
        $target = Carbon::createFromFormat('!Y-m-d', $date, AelfService::TIMEZONE);

        if (abs($today->diffInDays($target, false)) > 366 || Cache::has($this->failureKey($date))) {
            return null;
        }

        $day = $this->aelf->import($date);
        if (!$day) {
            Cache::put($this->failureKey($date), true, self::FAILURE_COOLDOWN);
        }

        return $day;
    }

    private function failureKey(string $date): string
    {
        return 'liturgy:aelf-failed:' . $date;
    }
}
