<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScheduleException;
use App\Models\TimeSlot;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    private const TIMEZONE = 'Africa/Abidjan';

    /** Libellés par défaut selon le type de créneau. */
    private const DEFAULT_LABELS = [
        'messe'      => 'Messe',
        'confession' => 'Confessions',
        'adoration'  => 'Adoration du Saint-Sacrement',
    ];

    /**
     * Horaires de la semaine (public).
     * GET /schedule/week?start=YYYY-MM-DD (défaut : lundi de la semaine courante)
     *
     * Pour chacun des 7 jours : créneaux récurrents disponibles (hors « ecoute »),
     * fusionnés avec les exceptions du jour (annulations, ajouts ponctuels), triés par heure.
     */
    public function week(Request $request): JsonResponse
    {
        $request->validate(['start' => 'nullable|date_format:Y-m-d']);

        $start = $request->filled('start')
            ? Carbon::createFromFormat('!Y-m-d', $request->input('start'), self::TIMEZONE)
            : Carbon::now(self::TIMEZONE)->startOfWeek(Carbon::MONDAY);
        $end = $start->copy()->addDays(6);

        $slots = TimeSlot::query()
            ->where('is_available', true)
            ->where('type', '!=', 'ecoute')
            ->get();

        $exceptions = ScheduleException::query()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('start_time')
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($e) => substr($e->date, 0, 10));

        $days = [];
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $date = $day->toDateString();
            $weekday = $day->dayOfWeek; // 0 = dimanche
            $dayExceptions = $exceptions->get($date, collect());
            $bySlot = $dayExceptions->whereNotNull('time_slot_id')->keyBy('time_slot_id');

            $items = [];

            // Créneaux récurrents du jour
            foreach ($slots->where('weekday', $weekday) as $slot) {
                $item = [
                    'time'      => self::hhmm($slot->getRawOriginal('start_time')),
                    'end_time'  => self::hhmm($slot->getRawOriginal('end_time')),
                    'label'     => $slot->label ?: (self::DEFAULT_LABELS[$slot->type] ?? 'Célébration'),
                    'location'  => $slot->location,
                    'type'      => $slot->type,
                    'cancelled' => false,
                ];

                $exception = $bySlot->get($slot->id);
                if ($exception) {
                    if ($exception->is_cancelled) {
                        $item['cancelled'] = true;
                    } else {
                        // Exception non annulante : modification ponctuelle du créneau
                        $item['time']     = self::hhmm($exception->start_time) ?? $item['time'];
                        $item['label']    = $exception->label ?: $item['label'];
                        $item['location'] = $exception->location ?: $item['location'];
                    }
                }

                $items[] = $item;
            }

            // Célébrations ponctuelles (sans créneau associé)
            foreach ($dayExceptions->whereNull('time_slot_id') as $exception) {
                $items[] = [
                    'time'      => self::hhmm($exception->start_time),
                    'end_time'  => null,
                    'label'     => $exception->label ?: 'Célébration',
                    'location'  => $exception->location,
                    'type'      => 'autre',
                    'cancelled' => (bool) $exception->is_cancelled,
                ];
            }

            // Tri par heure (éléments sans heure en fin de journée)
            usort($items, fn ($a, $b) => strcmp($a['time'] ?? '99:99', $b['time'] ?? '99:99'));

            $days[] = [
                'date'    => $date,
                'weekday' => $weekday,
                'items'   => $items,
            ];
        }

        return response()->json(['data' => $days]);
    }

    /** Extrait HH:MM d'une valeur « HH:MM[:SS] » ou datetime. */
    private static function hhmm(?string $value): ?string
    {
        if (!$value) {
            return null;
        }
        if (preg_match('/(\d{2}):(\d{2})/', $value, $m)) {
            return $m[1] . ':' . $m[2];
        }

        return null;
    }
}
