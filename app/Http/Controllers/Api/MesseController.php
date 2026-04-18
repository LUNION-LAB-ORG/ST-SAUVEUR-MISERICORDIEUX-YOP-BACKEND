<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mess\StoreRequest;
use App\Http\Requests\Mess\UpdateRequest;
use App\Http\Resources\MessResource;
use App\Repositories\Contracts\MessRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MesseController extends Controller
{
    protected MessRepositoryInterface $repo;

    public function __construct(MessRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    /**
     * List messes (paginated)
     */
    public function index(Request $request)
    {
        // Auto-expiration défensive : les demandes de messe Wave restées en
        // "pending" depuis plus d'1h sans webhook de confirmation sont
        // considérées comme abandonnées et marquées "failed" + "canceled".
        \App\Models\Mess::query()
            ->where('payment_status', 'pending')
            ->whereNotNull('wave_checkout_id')
            ->where('created_at', '<', now()->subHour())
            ->update([
                'payment_status' => 'failed',
                'request_status' => 'canceled',
            ]);

        $conditions = [];

        // Filters
        if ($request->filled('type')) {
            $conditions[] = ['type', 'LIKE', '%' . $request->type . '%'];
        }

        if ($request->filled('fullname')) {
            $conditions[] = ['fullname', 'LIKE', '%' . $request->fullname . '%'];
        }

        if ($request->filled('phone')) {
            $conditions[] = ['phone', 'LIKE', '%' . $request->phone . '%'];
        }

        if ($request->filled('status')) {
            $conditions[] = ['request_status', '=', $request->status];
        }

        if ($request->filled('from')) {
            $conditions[] = ['date_at', '>=', $request->from];
        }

        if ($request->filled('to')) {
            $conditions[] = ['date_at', '<=', $request->to];
        }

        $messes = $this->repo->paginate(
            with: [],
            page: (int) $request->input('per_page', 15),
            conditions: $conditions,
            skip: (int) $request->input('skip', 0),
            orderBy: $request->input('sort_by', 'id'),
            direction: $request->input('sort_dir', 'desc'),
        );

        return MessResource::collection($messes);
    }

    /**
     * Store a new messe
     */
    public function store(StoreRequest $request)
    {
        $data = $request->validated();

        // Validation : la date + heure doivent correspondre à un slot 'messe' actif
        $error = self::ensureSlotMatch($data['date_at'] ?? null, $data['time_at'] ?? null, 'messe');
        if ($error) {
            return response()->json(['error' => $error], 422);
        }

        $messe = $this->repo->create($data);

        // Notification admin
        try { \App\Services\NotificationService::forMesse($messe); } catch (\Throwable $e) {}

        return (new MessResource($messe))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Vérifie que la date+heure choisies correspondent à un slot configuré actif.
     * Renvoie une string d'erreur si invalide, null si OK.
     */
    private static function ensureSlotMatch(?string $dateAt, ?string $timeAt, string $type): ?string
    {
        if (!$dateAt || !$timeAt) return null;

        try {
            $date = \Carbon\Carbon::parse($dateAt);
        } catch (\Throwable $e) {
            return "Date invalide.";
        }
        $weekday = (int) $date->dayOfWeek; // 0 = dimanche
        // Extraire HH:MM de time_at (peut être "09:00" ou "09:00:00" ou ISO)
        $hm = '00:00';
        if (preg_match('/^(\d{2}):(\d{2})/', $timeAt, $m)) {
            $hm = $m[1] . ':' . $m[2];
        } else {
            try { $hm = \Carbon\Carbon::parse($timeAt)->format('H:i'); } catch (\Throwable $e) {}
        }

        $exists = \App\Models\TimeSlot::where('type', $type)
            ->where('weekday', $weekday)
            ->where('is_available', true)
            ->where('start_time', 'LIKE', $hm . '%')
            ->exists();

        if (!$exists) {
            return "Ce créneau n'est pas disponible. Veuillez choisir parmi les horaires proposés.";
        }
        return null;
    }

    /**
     * Display messe
     */
    public function show(string $id)
    {
        return new MessResource($this->repo->find($id));
    }

    /**
     * Update messe
     */
    public function update(UpdateRequest $request, string $id)
    {
        $messe = $this->repo->update($id, $request->validated());
        return new MessResource($messe);
    }

    /**
     * Soft delete messe
     */
    public function destroy(string $id)
    {
        $this->repo->delete($id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Messe supprimée'
        ], Response::HTTP_NO_CONTENT);
    }
}
