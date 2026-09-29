<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MassRequest\StoreRequest;
use App\Models\MassSchedule;
use App\Models\Mess;
use App\Services\MassRequestService;
use App\Services\WaveCheckoutService;
use App\Support\IcsCalendar;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class MassRequestController extends Controller
{
    public function __construct(
        protected MassRequestService $service,
        protected WaveCheckoutService $wave,
    ) {
    }

    /**
     * Disponibilités des messes (public). GET /mass-requests/availability?from=YYYY-MM-DD&days=14
     */
    public function availability(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'days' => 'nullable|integer|min:1|max:31',
        ]);

        $from = $request->filled('from')
            ? Carbon::createFromFormat('!Y-m-d', $request->input('from'), MassRequestService::TIMEZONE)
            : Carbon::now(MassRequestService::TIMEZONE)->startOfDay();

        return response()->json([
            'data' => $this->service->availability($from, (int) $request->input('days', 14)),
        ]);
    }

    /**
     * Nouvelle demande de messe (public). Crée la demande, les messes programmées et,
     * pour Wave, la session de paiement (client_reference = numéro de la demande).
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (!in_array($data['payment_method'], ['wave', 'secretariat'], true)) {
            throw ValidationException::withMessages(['payment_method' => 'Moyen de paiement bientôt disponible.']);
        }
        if ($data['payment_method'] === 'wave' && !$this->wave->isConfigured()) {
            return response()->json(['error' => 'Configuration Wave manquante. Contactez l\'administrateur.'], 500);
        }

        $formula = $data['formula'] ?? 'single';
        $count = MassRequestService::FORMULAS[$formula];
        $amount = $this->offeringAmount($data, $count);

        if ($data['payment_method'] === 'wave' && $amount < 100) {
            throw ValidationException::withMessages(['amount' => 'Le paiement Wave requiert une offrande d’au moins 100 FCFA.']);
        }

        $date = Carbon::createFromFormat('!Y-m-d', $data['date'], MassRequestService::TIMEZONE);

        $mess = $this->service->create([
            'type'            => $data['intention_type'],
            'intention_type'  => $data['intention_type'],
            'for_whom'        => $data['for_whom'],
            'message'         => $data['intention'] ?? null,
            'is_confidential' => (bool) ($data['is_confidential'] ?? false),
            'formula'         => $formula,
            'fullname'        => $data['fullname'],
            'phone'           => $data['phone'],
            'email'           => $data['email'] ?? null,
            'will_attend'     => (bool) ($data['will_attend'] ?? false),
            'reminder'        => (bool) ($data['reminder'] ?? true),
            'amount'          => $amount,
            'payment_method'  => $data['payment_method'],
            'payment_status'  => $data['payment_method'] === 'secretariat' ? 'to_pay' : 'pending',
            'request_status'  => 'pending',
        ], $date, (int) $data['time_slot_id'], $count);

        $launchUrl = null;

        if ($data['payment_method'] === 'wave') {
            $launchUrl = $this->startWaveCheckout($mess);
            if ($launchUrl instanceof JsonResponse) {
                return $launchUrl;
            }
        }

        try { \App\Services\NotificationService::forMesse($mess); } catch (\Throwable $e) {}

        return response()->json(['data' => $this->payload($mess->fresh('schedules'), $launchUrl)], 201);
    }

    /**
     * Récapitulatif d'une demande (public, protégé par le jeton du reçu). GET /mass-requests/{number}?t=
     */
    public function show(Request $request, string $number): JsonResponse
    {
        $mess = $this->findWithToken($number, (string) $request->query('t'));

        return response()->json(['data' => $this->payload($mess, null, true)]);
    }

    /**
     * Calendrier des messes programmées (un VEVENT par messe). GET /mass-requests/{number}/ics?t=
     */
    public function ics(Request $request, string $number)
    {
        $mess = $this->findWithToken($number, (string) $request->query('t'));
        $calendar = new IcsCalendar();

        foreach ($mess->schedules as $i => $schedule) {
            $start = Carbon::parse($schedule->date . ' ' . $schedule->hhmm(), IcsCalendar::TIMEZONE);
            $slot = $schedule->timeSlot;
            $end = $slot ? Carbon::parse($schedule->date . ' ' . MassRequestService::slotTime($slot, 'end_time'), IcsCalendar::TIMEZONE) : null;
            if (!$end || $end->lte($start)) {
                $end = $start->copy()->addHour();
            }

            $calendar->addEvent([
                'uid'         => strtolower($mess->number) . '-' . ($i + 1) . '@saint-sauveur-misericordieux',
                'start'       => $start,
                'end'         => $end,
                'summary'     => $mess->is_confidential ? 'Messe (intention confidentielle)' : 'Messe pour ' . $mess->for_whom,
                'location'    => $slot?->location ?: 'Paroisse Saint Sauveur Miséricordieux',
                'description' => 'Demande n° ' . $mess->number . ($mess->masses_count > 1 ? ' — messe ' . ($i + 1) . '/' . $mess->masses_count : ''),
            ]);
        }

        return $calendar->download($mess->number . '.ics');
    }

    /**
     * Liste du célébrant (admin), groupée par créneau. GET /mass-schedules?date=YYYY-MM-DD
     */
    public function schedules(Request $request): JsonResponse
    {
        $request->validate(['date' => 'nullable|date_format:Y-m-d']);
        $date = $request->input('date') ?: Carbon::now(MassRequestService::TIMEZONE)->toDateString();

        $rows = MassSchedule::query()
            ->with('mess')
            ->where('date', $date)
            ->whereHas('mess', fn ($q) => $q->where('request_status', '!=', 'canceled'))
            ->orderBy('time')
            ->orderBy('id')
            ->get();

        $groups = $rows
            ->groupBy(fn (MassSchedule $s) => $s->hhmm() . '|' . $s->time_slot_id)
            ->map(function ($items) {
                $first = $items->first();

                return [
                    'time'        => $first->hhmm(),
                    'label'       => $first->label ?: 'Messe',
                    'intentions'  => $items->map(fn (MassSchedule $s) => [
                        'number'         => $s->mess->number,
                        'intention_type' => $s->mess->intention_type,
                        'for_whom'       => $s->mess->is_confidential ? 'Intention confidentielle' : $s->mess->for_whom,
                        'intention'      => $s->mess->is_confidential ? null : $s->mess->message,
                        'payment_status' => $s->mess->payment_status,
                    ])->values()->all(),
                ];
            })
            ->values();

        return response()->json(['data' => $groups]);
    }

    /* ============== Helpers privés ============== */

    /** Offrande totale : indicative × nombre de messes, ou montant libre ≥ minimum. */
    private function offeringAmount(array $data, int $count): int
    {
        $settings = $this->service->settings();
        $offering = $data['offering'] ?? ($settings['offering_amount'] ? 'indicative' : 'free');

        if ($offering === 'indicative') {
            if (!$settings['offering_amount']) {
                throw ValidationException::withMessages(['offering' => 'Aucune offrande indicative n’est définie : merci d’indiquer un montant libre.']);
            }
            return $settings['offering_amount'] * $count;
        }

        if (!isset($data['amount'])) {
            throw ValidationException::withMessages(['amount' => 'Merci d’indiquer le montant de votre offrande.']);
        }
        if ((int) $data['amount'] < $settings['min_offering']) {
            throw ValidationException::withMessages(['amount' => "L'offrande minimale est de {$settings['min_offering']} FCFA."]);
        }

        return (int) $data['amount'];
    }

    /** Crée la session Wave ; en cas d'échec la demande est annulée et une réponse d'erreur est renvoyée. */
    private function startWaveCheckout(Mess $mess): string|JsonResponse
    {
        $base = $this->wave->frontendUrl() . '/demande-messe';
        $successUrl = $base . '/confirmation?n=' . $mess->number . '&t=' . $mess->access_token;
        $errorUrl = $base . '/erreur?n=' . $mess->number;

        try {
            $response = $this->wave->createSession($mess->amount, $mess->number, $successUrl, $errorUrl);

            if ($response->failed()) {
                Log::error('Wave Mass Request Error', ['status' => $response->status(), 'body' => $response->json()]);
                $this->cancel($mess);
                return response()->json(['error' => 'Erreur lors de la création du paiement Wave.'], 502);
            }

            $session = $response->json();
            $mess->update([
                'wave_reference'   => $mess->number,
                'wave_checkout_id' => $session['id'] ?? null,
            ]);

            return (string) ($session['wave_launch_url'] ?? '');
        } catch (\Throwable $e) {
            Log::error('Wave Mass Request Exception', ['message' => $e->getMessage()]);
            $this->cancel($mess);
            return response()->json(['error' => 'Erreur de connexion au service Wave.'], 503);
        }
    }

    /** Annule une demande dont le paiement n'a pas pu démarrer (libère les créneaux). */
    private function cancel(Mess $mess): void
    {
        $mess->update(['request_status' => 'canceled', 'payment_status' => 'failed']);
        $mess->delete();
    }

    private function findWithToken(string $number, string $token): Mess
    {
        $mess = Mess::where('number', $number)->with('schedules.timeSlot')->first();
        abort_if(!$mess || !$mess->access_token || !hash_equals($mess->access_token, $token), 404);

        return $mess;
    }

    private function payload(Mess $mess, ?string $launchUrl, bool $withDetails = false): array
    {
        $payload = [
            'number'          => $mess->number,
            'access_token'    => $mess->access_token,
            'amount'          => (int) $mess->amount,
            'payment_status'  => $mess->payment_status,
            'payment_method'  => $mess->payment_method,
            'wave_launch_url' => $launchUrl ?: null,
            'schedules'       => $mess->schedules->map(fn (MassSchedule $s) => [
                'date'    => $s->date,
                'time'    => $s->hhmm(),
                'label'   => $s->label,
                'shifted' => (bool) $s->shifted,
            ])->values()->all(),
        ];

        if ($withDetails) {
            $payload += [
                'intention_type'  => $mess->intention_type,
                'for_whom'        => $mess->for_whom,
                'is_confidential' => (bool) $mess->is_confidential,
                'formula'         => $mess->formula,
                'fullname'        => $mess->fullname,
                'created_at'      => optional($mess->created_at)->toDateTimeString(),
            ];
        }

        return $payload;
    }
}
