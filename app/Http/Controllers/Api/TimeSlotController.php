<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TimeSlot\StoreRequest;
use App\Http\Requests\TimeSlot\UpdateRequest;
use App\Http\Resources\TimeSlotResource;
use App\Models\Listen;
use App\Models\Mess;
use App\Models\TimeSlot;
use App\Repositories\Contracts\TimeSlotRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TimeSlotController extends Controller
{
    protected TimeSlotRepositoryInterface $repo;

    public function __construct(TimeSlotRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Liste des slots configurés (admin).
     * Filtres : ?type=messe|ecoute|... ?priest_id=X ?weekday=0..6 ?is_available=0|1
     */
    public function index(Request $request)
    {
        $conditions = [];
        if ($request->filled('type'))         $conditions[] = ['type', '=', $request->type];
        if ($request->filled('priest_id'))    $conditions[] = ['priest_id', '=', $request->priest_id];
        if ($request->filled('weekday'))      $conditions[] = ['weekday', '=', $request->weekday];
        if ($request->filled('is_available')) $conditions[] = ['is_available', '=', $request->is_available];

        $timeSlots = $this->repo->paginate(
            with: ['priest'],
            page: (int) $request->input('per_page', 100),
            conditions: $conditions,
            skip: (int) $request->input('skip', 0),
            orderBy: $request->input('sort_by', 'weekday'),
            direction: $request->input('sort_dir', 'asc'),
        );

        return TimeSlotResource::collection($timeSlots);
    }

    /**
     * Endpoint PUBLIC : retourne les prochaines occurrences (calculées) de slots
     * disponibles pour un type donné, sur N semaines à venir.
     *
     * Format réponse : [{ slot_id, type, date, start_time, end_time, label, capacity, taken, available }]
     * Triées chronologiquement.
     *
     * Filtre les slots inactifs et ceux ayant atteint leur capacité pour cette occurrence.
     */
    public function available(Request $request): JsonResponse
    {
        $type = $request->input('type', 'ecoute');
        $weeks = (int) $request->input('weeks', 6);
        $weeks = max(1, min($weeks, 12));

        $slots = TimeSlot::where('type', $type)
            ->where('is_available', true)
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get();

        $now = Carbon::now();
        $occurrences = [];

        foreach ($slots as $slot) {
            // Pour chaque slot récurrent, générer les prochaines occurrences sur $weeks semaines
            $cursor = $now->copy()->startOfDay();
            $end = $now->copy()->addWeeks($weeks);

            while ($cursor->lte($end)) {
                if ((int) $cursor->dayOfWeekIso % 7 === (int) $slot->weekday) {
                    $startTime = self::extractTime($slot->start_time);
                    $endTime   = self::extractTime($slot->end_time);
                    $occDate   = $cursor->toDateString();
                    $occStart  = Carbon::parse("$occDate $startTime");

                    // Ne garder que les occurrences dans le futur
                    if ($occStart->gte($now)) {
                        $taken = self::countTakenForOccurrence($type, $slot->id, $occDate, $startTime);
                        $capacity = $slot->capacity;
                        $available = is_null($capacity) ? null : max(0, $capacity - $taken);

                        // Si capacité atteinte, on n'inclut pas l'occurrence
                        if (is_null($capacity) || $available > 0) {
                            $occurrences[] = [
                                'slot_id'    => $slot->id,
                                'type'       => $slot->type,
                                'date'       => $occDate,
                                'start_time' => $startTime,
                                'end_time'   => $endTime,
                                'capacity'   => $capacity,
                                'taken'      => $taken,
                                'available'  => $available,
                                'label'      => self::formatLabel($occDate, $startTime, $endTime),
                                'iso_datetime' => $occStart->toIso8601String(),
                            ];
                        }
                    }
                }
                $cursor->addDay();
            }
        }

        // Trier par date+heure
        usort($occurrences, fn($a, $b) => strcmp($a['iso_datetime'], $b['iso_datetime']));

        return response()->json(['data' => $occurrences]);
    }

    public function store(StoreRequest $request)
    {
        $timeSlot = $this->repo->create($request->validated());

        return (new TimeSlotResource($timeSlot))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(string $id)
    {
        return new TimeSlotResource($this->repo->find($id, ['priest']));
    }

    public function update(UpdateRequest $request, string $id)
    {
        $timeSlot = $this->repo->update($id, $request->validated());
        return new TimeSlotResource($timeSlot);
    }

    public function destroy(string $id)
    {
        $this->repo->delete($id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Créneau supprimé'
        ], Response::HTTP_NO_CONTENT);
    }

    /* ============== Helpers privés ============== */

    private static function extractTime(?string $value): string
    {
        if (!$value) return '00:00';
        // Si c'est déjà au format HH:MM:SS ou HH:MM
        if (preg_match('/^(\d{2}):(\d{2})/', $value, $m)) {
            return $m[1] . ':' . $m[2];
        }
        // Si datetime ISO
        try {
            return Carbon::parse($value)->format('H:i');
        } catch (\Throwable $e) {
            return '00:00';
        }
    }

    /**
     * Compte combien de réservations existent déjà sur cette occurrence précise.
     * Pour messe : compte les Messes avec date_at = $date et time_at = $startTime
     * Pour ecoute : compte les Listens avec time_slot_id = $slotId et listen_at = $date
     */
    private static function countTakenForOccurrence(string $type, int $slotId, string $date, string $startTime): int
    {
        if ($type === 'messe') {
            return Mess::whereDate('date_at', $date)
                ->where(function ($q) use ($startTime) {
                    $q->where('time_at', 'LIKE', "$startTime%")
                      ->orWhere('time_at', 'LIKE', "%T$startTime%");
                })
                ->whereIn('request_status', ['pending', 'accepted'])
                ->count();
        }
        if ($type === 'ecoute') {
            return Listen::where('time_slot_id', $slotId)
                ->whereDate('listen_at', $date)
                ->whereIn('request_status', ['pending', 'accepted'])
                ->count();
        }
        return 0;
    }

    private static function formatLabel(string $date, string $startTime, string $endTime): string
    {
        $d = Carbon::parse($date)->locale('fr');
        $dayLabel = $d->isoFormat('dddd D MMMM YYYY');
        $dayLabel = ucfirst($dayLabel);
        return "$dayLabel — $startTime à $endTime";
    }
}
