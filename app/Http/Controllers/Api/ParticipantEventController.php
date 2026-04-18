<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\ParticipantEventResource;
use App\Http\Requests\ParticipantEvent\StoreRequest;
use App\Http\Requests\ParticipantEvent\UpdateRequest;
use App\Models\Event;
use App\Models\ParticipantEvent;
use App\Repositories\Contracts\ParticipantEventRepositoryInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ParticipantEventController extends Controller
{
    protected ParticipantEventRepositoryInterface $repo;

    public function __construct(ParticipantEventRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    public function index(Request $request)
    {
        $conditions = [];

        if ($request->filled('fullname')) {
            $conditions[] = ['fullname', 'LIKE', '%' . $request->fullname . '%'];
        }

        if ($request->filled('email')) {
            $conditions[] = ['email', 'LIKE', '%' . $request->email . '%'];
        }

        if ($request->filled('event_id')) {
            $conditions[] = ['event_id', '=', $request->event_id];
        }

        $participants = $this->repo->paginate(
            with: ['event'],
            page: (int) $request->input('per_page', 15),
            conditions: $conditions,
            skip: (int) $request->input('skip', 0),
            orderBy: $request->input('sort_by', 'id'),
            direction: $request->input('sort_dir', 'desc'),
        );

        return ParticipantEventResource::collection($participants);
    }

    /**
     * POST /api/events/{id}/register — public
     * Inscription à un événement (avec ou sans paiement Wave)
     */
    public function register(Request $request, string $id): JsonResponse
    {
        $event = Event::findOrFail($id);

        $request->validate([
            'fullname'   => 'required|string|max:255',
            'email'      => 'nullable|email|max:255',
            'phone'      => 'nullable|string|max:30',
            'message'    => 'nullable|string',
            'tier_label' => 'nullable|string|max:100',
        ]);

        // Vérifier la deadline d'inscription
        if ($event->registration_deadline && now()->gt($event->registration_deadline)) {
            return response()->json([
                'error' => 'Les inscriptions pour cet événement sont fermées.',
            ], 422);
        }

        // Comptage des places réellement occupées :
        // - succeeded / paid / free : inscription confirmée
        // - pending récents (< 1h) : paiement en cours, la place est réservée
        //   pour l'utilisateur qui est parti chez Wave
        // - pending > 1h : considéré abandonné, la place se libère automatiquement
        $activeCountFilter = function ($q) {
            $q->whereIn('payment_status', ['succeeded', 'paid', 'free'])
              ->orWhere(function ($q2) {
                  $q2->where('payment_status', 'pending')
                     ->where('created_at', '>', now()->subHour());
              });
        };

        // Vérifier les places globales disponibles
        if ($event->max_participants !== null) {
            $activeCount = ParticipantEvent::where('event_id', $event->id)
                ->where($activeCountFilter)
                ->count();
            if ($activeCount >= $event->max_participants) {
                return response()->json([
                    'error' => 'Cet événement est complet.',
                ], 422);
            }
        }

        // Événement GRATUIT → inscription directe
        if (!$event->is_paid) {
            $participant = ParticipantEvent::create([
                'fullname'       => $request->fullname,
                'email'          => $request->email,
                'phone'          => $request->phone,
                'message'        => $request->message,
                'event_id'       => $event->id,
                'payment_status' => 'free',
            ]);

            try { \App\Services\NotificationService::forEventRegistration($participant, $event); } catch (\Throwable $e) {}

            return response()->json([
                'type'        => 'free',
                'participant' => new ParticipantEventResource($participant->load('event')),
                'message'     => 'Inscription confirmée !',
            ], 201);
        }

        // Événement PAYANT → déterminer le tier + montant
        $tiers = is_array($event->pricing_tiers) ? $event->pricing_tiers : [];
        $tierLabel = $request->input('tier_label');
        $amount    = $event->price;

        if (count($tiers) > 0) {
            // Event avec tarifs multiples → tier_label requis
            if (!$tierLabel) {
                return response()->json([
                    'error' => 'Veuillez sélectionner un tarif.',
                ], 422);
            }

            $selectedTier = null;
            foreach ($tiers as $t) {
                if (($t['label'] ?? null) === $tierLabel) {
                    $selectedTier = $t;
                    break;
                }
            }

            if (!$selectedTier) {
                return response()->json([
                    'error' => 'Tarif invalide.',
                ], 422);
            }

            $amount = (float) ($selectedTier['amount'] ?? 0);

            // Vérifier capacité par tier si max_participants défini
            // Même logique que le count global : on n'inclut les pending que s'ils
            // ont moins d'1h (au-delà, considérés comme abandonnés → place libre).
            $tierMax = $selectedTier['max_participants'] ?? null;
            if ($tierMax !== null && (int) $tierMax > 0) {
                $tierTaken = ParticipantEvent::where('event_id', $event->id)
                    ->where('tier_label', $tierLabel)
                    ->where($activeCountFilter)
                    ->count();
                if ($tierTaken >= (int) $tierMax) {
                    return response()->json([
                        'error' => "Plus de places disponibles pour le tarif '$tierLabel'.",
                    ], 422);
                }
            }
        }

        $clientRef = 'event-' . $event->id . '-' . now()->timestamp;

        $participant = ParticipantEvent::create([
            'fullname'         => $request->fullname,
            'email'            => $request->email,
            'phone'            => $request->phone,
            'message'          => $request->message,
            'event_id'         => $event->id,
            'payment_status'   => 'pending',
            'payment_reference'=> $clientRef,
            'amount'           => $amount,
            'tier_label'       => $tierLabel,
        ]);

        $apiKey      = config('services.wave.api_key');
        $frontendUrl = config('services.wave.frontend_url', 'https://paroisse-st-sauveur-mis-ricordieux.vercel.app');
        $successUrl  = $frontendUrl . '/evenement/' . $event->id . '/inscription/succes?ref=' . $clientRef;
        $errorUrl    = $frontendUrl . '/evenement/' . $event->id . '/inscription/erreur?ref=' . $clientRef;

        if (!$apiKey) {
            // Dev sans clé Wave → retourner directement la confirmation
            $participant->update(['payment_status' => 'succeeded']);
            return response()->json([
                'type'        => 'paid_dev',
                'participant' => new ParticipantEventResource($participant->load('event')),
                'message'     => 'Inscription confirmée (mode dev, paiement simulé).',
            ], 201);
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ])->post('https://api.wave.com/v1/checkout/sessions', [
                'amount'           => strval(intval($amount)),
                'currency'         => 'XOF',
                'success_url'      => $successUrl,
                'error_url'        => $errorUrl,
                'client_reference' => $clientRef,
            ]);

            if ($response->failed()) {
                Log::error('Wave Event Registration Error', ['status' => $response->status(), 'body' => $response->json()]);
                $participant->delete();
                return response()->json(['error' => 'Erreur paiement Wave.'], 502);
            }

            $session = $response->json();

            $participant->update(['wave_checkout_id' => $session['id']]);

            return response()->json([
                'type'             => 'paid',
                'wave_launch_url'  => $session['wave_launch_url'],
                'checkout_id'      => $session['id'],
                'amount'           => $session['amount'],
                'expires_at'       => $session['when_expires'] ?? null,
                'participant_id'   => $participant->id,
                'message'          => 'Redirection vers Wave pour le paiement.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Wave Event Registration Exception', ['message' => $e->getMessage()]);
            $participant->delete();
            return response()->json(['error' => 'Erreur de connexion Wave.'], 503);
        }
    }

    public function store(StoreRequest $request)
    {
        $data = $request->validated();

        $participant = $this->repo->create($data);

        return (new ParticipantEventResource($participant->load('event')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(string $id): ParticipantEventResource
    {
        return new ParticipantEventResource(
            $this->repo->find($id, ['event'])
        );
    }

    public function update(UpdateRequest $request, string $id): ParticipantEventResource
    {
        $data = $request->validated();

        $participant = $this->repo->update($id, $data);

        return new ParticipantEventResource(
            $participant->load('event')
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $this->repo->delete($id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Participant supprimé'
        ], Response::HTTP_NO_CONTENT);
    }
}
