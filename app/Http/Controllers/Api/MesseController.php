<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mess\StoreRequest;
use App\Http\Requests\Mess\UpdateRequest;
use App\Http\Resources\MessResource;
use App\Models\Mess;
use App\Support\CsvExport;
use Illuminate\Database\Eloquent\Builder;
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

        $query = $this->filteredQuery($request)->with(['schedules', 'timeSlot']);

        $messes = $query
            ->orderBy($request->input('sort_by', 'id'), $request->input('sort_dir', 'desc'))
            ->paginate((int) $request->input('per_page', 15));

        return MessResource::collection($messes);
    }

    /**
     * Export CSV des demandes de messe (admin). ?status=&from=&to=
     */
    public function export(Request $request)
    {
        $messes = $this->filteredQuery($request)->with(['schedules', 'timeSlot'])->orderBy('id')->get();

        $formulas = ['single' => 'Messe unique', 'triduum' => 'Triduum', 'novena' => 'Neuvaine'];
        $methods = ['wave' => 'Wave', 'secretariat' => 'Secrétariat', 'cash' => 'Espèces', 'orange' => 'Orange Money', 'mtn' => 'MTN MoMo', 'moov' => 'Moov Money', 'card' => 'Carte'];

        $rows = $messes->map(function (Mess $m) use ($formulas, $methods) {
            $dates = $m->schedules->isNotEmpty()
                ? $m->schedules->map(fn ($s) => CsvExport::date($s->date) . ' ' . $s->hhmm())->implode(' / ')
                : CsvExport::date($m->getRawOriginal('date_at')) . ' ' . substr((string) $m->getRawOriginal('time_at'), -8, 5);

            return [
                $m->number ?: '#' . $m->id,
                trim($dates),
                $m->schedules->first()?->label ?? $m->timeSlot?->label,
                $m->intention_type ?: $m->type,
                $m->is_confidential ? 'Intention confidentielle' : $m->for_whom,
                $m->is_confidential ? '' : $m->message,
                $formulas[$m->formula ?? 'single'] ?? $m->formula,
                (int) $m->amount,
                trim(($methods[$m->payment_method] ?? (string) $m->payment_method) . ' — ' . CsvExport::paymentStatus($m->payment_status), ' —'),
                $m->fullname,
                $m->phone,
            ];
        });

        return CsvExport::download(
            'demandes-de-messe-' . now()->format('Y-m-d') . '.csv',
            ['N°', 'Date(s) des messes', 'Créneau', 'Type d’intention', 'Pour qui', 'Intention', 'Formule', 'Offrande (FCFA)', 'Paiement', 'Demandeur', 'Téléphone'],
            $rows
        );
    }

    /**
     * Filtres communs liste / export.
     * ?status=to_process|to_pay|paid|all (ou une valeur de request_status, comportement historique),
     * ?q= (numéro, nom, pour qui, téléphone), ?date= (date d'une messe programmée), ?from=&to=, ?type=, ?fullname=, ?phone=
     */
    private function filteredQuery(Request $request): Builder
    {
        $query = Mess::query();

        match ($request->input('status')) {
            null, '', 'all' => null,
            'to_process'    => $query->where('request_status', 'pending'),
            'to_pay'        => $query->where('payment_status', 'to_pay'),
            'paid'          => $query->where('payment_status', 'succeeded'),
            default         => $query->where('request_status', $request->input('status')),
        };

        if ($request->filled('q')) {
            $term = '%' . trim($request->input('q')) . '%';
            $query->where(fn ($q) => $q->where('number', 'LIKE', $term)
                ->orWhere('fullname', 'LIKE', $term)
                ->orWhere('for_whom', 'LIKE', $term)
                ->orWhere('phone', 'LIKE', $term));
        }

        if ($request->filled('date')) {
            $date = $request->input('date');
            $query->where(fn ($q) => $q->whereHas('schedules', fn ($s) => $s->where('date', $date))
                ->orWhere(fn ($legacy) => $legacy->whereDoesntHave('schedules')->whereDate('date_at', $date)));
        }

        foreach (['type' => 'type', 'fullname' => 'fullname', 'phone' => 'phone'] as $param => $column) {
            if ($request->filled($param)) {
                $query->where($column, 'LIKE', '%' . $request->input($param) . '%');
            }
        }
        if ($request->filled('from')) {
            $query->whereDate('date_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('date_at', '<=', $request->input('to'));
        }

        return $query;
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
        return new MessResource($this->repo->find($id, ['schedules', 'timeSlot']));
    }

    /**
     * Update messe
     */
    public function update(UpdateRequest $request, string $id)
    {
        $messe = $this->repo->update($id, $request->validated());
        return new MessResource($messe->load(['schedules', 'timeSlot']));
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
