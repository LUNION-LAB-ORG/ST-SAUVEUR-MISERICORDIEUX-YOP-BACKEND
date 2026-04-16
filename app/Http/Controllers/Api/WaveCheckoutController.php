<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Donation;
use App\Models\Mess;
use App\Models\ParticipantEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WaveCheckoutController extends Controller
{
    private string $baseUrl = 'https://api.wave.com';

    /**
     * Créer une session de paiement Wave
     * POST /api/wave/checkout
     */
    public function createSession(Request $request)
    {
        $request->validate([
            'amount'           => 'required|numeric|min:100',
            'type'             => 'required|string|in:donation,messe,event',
            'client_reference' => 'nullable|string|max:255',
            'donator'          => 'nullable|string|max:255',
            'project'          => 'nullable|string|max:255',
            'description'      => 'nullable|string',
        ]);

        $apiKey = config('services.wave.api_key');

        if (!$apiKey) {
            return response()->json([
                'error' => 'Configuration Wave manquante. Contactez l\'administrateur.',
            ], 500);
        }

        $frontendUrl = config('services.wave.frontend_url', 'https://paroisse-st-sauveur-mis-ricordieux.vercel.app');
        $clientRef   = $request->client_reference ?? ($request->type . '-' . now()->timestamp);

        // URLs de retour selon le type de paiement
        if ($request->type === 'messe') {
            $successUrl = $frontendUrl . '/demande-messe/succes?ref=' . $clientRef;
            $errorUrl   = $frontendUrl . '/demande-messe/erreur?ref=' . $clientRef;
        } elseif ($request->type === 'event') {
            $eventId    = $request->input('event_id', '');
            $successUrl = $frontendUrl . '/evenement/' . $eventId . '/inscription/succes?ref=' . $clientRef;
            $errorUrl   = $frontendUrl . '/evenement/' . $eventId . '/inscription/erreur?ref=' . $clientRef;
        } else {
            $successUrl = $frontendUrl . '/faire-don/paiement/succes?ref=' . $clientRef;
            $errorUrl   = $frontendUrl . '/faire-don/paiement/erreur?ref=' . $clientRef;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ])->post($this->baseUrl . '/v1/checkout/sessions', [
                'amount'           => strval(intval($request->amount)),
                'currency'         => 'XOF',
                'success_url'      => $successUrl,
                'error_url'        => $errorUrl,
                'client_reference' => $clientRef,
            ]);

            if ($response->failed()) {
                Log::error('Wave Checkout Error', [
                    'status' => $response->status(),
                    'body'   => $response->json(),
                ]);

                return response()->json([
                    'error'   => 'Erreur lors de la création du paiement Wave.',
                    'details' => $response->json(),
                ], $response->status());
            }

            $session = $response->json();

            // Enregistrer en base avec statut "processing"
            if ($request->type === 'donation') {
                Donation::create([
                    'donator'       => $request->donator ?? 'Anonyme',
                    'amount'        => $request->amount,
                    'project'       => $request->project ?? 'Fonctionnement',
                    'paymethod'     => 'wave',
                    'paytransaction'=> $session['id'] ?? null,
                    'description'   => $request->description ?? 'Don via Wave',
                    'donation_at'   => now(),
                ]);
            } elseif ($request->type === 'messe') {
                Mess::create([
                    'type'           => $request->mess_type ?? 'intention',
                    'fullname'       => $request->donator ?? 'Anonyme',
                    'email'          => $request->email ?? null,
                    'phone'          => $request->phone ?? '',
                    'message'        => $request->description ?? '',
                    'request_status' => 'pending',
                    'amount'         => $request->amount,
                    'date_at'        => now()->toDateString(),
                    'time_at'        => now()->toTimeString(),
                ]);
            }

            return response()->json([
                'checkout_id'     => $session['id'],
                'wave_launch_url' => $session['wave_launch_url'],
                'amount'          => $session['amount'],
                'currency'        => $session['currency'],
                'expires_at'      => $session['when_expires'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('Wave Checkout Exception', ['message' => $e->getMessage()]);

            return response()->json([
                'error' => 'Erreur de connexion au service Wave.',
            ], 503);
        }
    }

    /**
     * Vérifier le statut d'une session Wave
     * GET /api/wave/checkout/{id}/status
     */
    public function checkStatus(string $id)
    {
        $apiKey = config('services.wave.api_key');

        if (!$apiKey) {
            return response()->json(['error' => 'Configuration Wave manquante.'], 500);
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
            ])->get($this->baseUrl . '/v1/checkout/sessions/' . $id);

            if ($response->failed()) {
                return response()->json(['error' => 'Session introuvable.'], 404);
            }

            $session = $response->json();

            // Mettre à jour le don si paiement réussi
            if (($session['payment_status'] ?? '') === 'succeeded') {
                $donation = Donation::where('paytransaction', $id)->first();
                if ($donation) {
                    $donation->update([
                        'paytransaction' => $session['transaction_id'] ?? $id,
                    ]);
                }
            }

            return response()->json([
                'checkout_status' => $session['checkout_status'],
                'payment_status'  => $session['payment_status'],
                'transaction_id'  => $session['transaction_id'] ?? null,
                'amount'          => $session['amount'],
                'when_completed'  => $session['when_completed'] ?? null,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur de vérification.'], 503);
        }
    }

    /**
     * Recevoir les webhooks Wave
     * POST /api/wave/webhook
     */
    public function webhook(Request $request)
    {
        $webhookSecret = config('services.wave.webhook_secret');

        // Vérification de la signature HMAC-SHA256
        if ($webhookSecret) {
            $signatureHeader = $request->header('Wave-Signature', '');
            $parts           = [];

            foreach (explode(',', $signatureHeader) as $part) {
                [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
                $parts[$key]   = $value;
            }

            $timestamp = $parts['t'] ?? '';
            $signature = $parts['v1'] ?? '';
            $rawBody   = $request->getContent();
            $expected  = hash_hmac('sha256', $timestamp . '.' . $rawBody, $webhookSecret);

            // Protection anti-replay : rejeter les webhooks de plus de 5 minutes
            if (abs(time() - (int) $timestamp) > 300) {
                return response()->json(['error' => 'Webhook expiré.'], 400);
            }

            if (!hash_equals($expected, $signature)) {
                Log::warning('Wave Webhook: signature invalide', [
                    'expected'  => $expected,
                    'received'  => $signature,
                ]);
                return response()->json(['error' => 'Signature invalide.'], 401);
            }
        }

        $event   = $request->input('type');
        $payload = $request->input('data', []);

        Log::info('Wave Webhook reçu', ['type' => $event]);

        switch ($event) {
            case 'checkout.session.completed':
                $sessionId   = $payload['id'] ?? null;
                $transId     = $payload['transaction_id'] ?? null;
                $clientRef   = $payload['client_reference'] ?? null;

                if ($sessionId) {
                    $donation = Donation::where('paytransaction', $sessionId)->first();
                    if ($donation && $transId) {
                        $donation->update(['paytransaction' => $transId]);
                    }
                }

                if ($clientRef && str_starts_with($clientRef, 'messe-')) {
                    $mess = Mess::where('request_status', 'pending')
                        ->latest()
                        ->first();
                    if ($mess) {
                        $mess->update(['request_status' => 'accepted']);
                    }
                }

                // Confirmer l'inscription à un événement payant
                if ($clientRef && str_starts_with($clientRef, 'event-')) {
                    $participant = ParticipantEvent::where('payment_reference', $clientRef)->first();
                    if ($participant) {
                        $participant->update([
                            'payment_status'   => 'succeeded',
                            'wave_checkout_id' => $sessionId,
                        ]);
                    }
                }
                break;

            case 'checkout.session.payment_failed':
                // Marquer l'inscription comme échouée
                $clientRef = $payload['client_reference'] ?? null;
                Log::info('Wave: paiement échoué', ['data' => $payload]);
                if ($clientRef && str_starts_with($clientRef, 'event-')) {
                    $participant = ParticipantEvent::where('payment_reference', $clientRef)->first();
                    if ($participant) {
                        $participant->update(['payment_status' => 'failed']);
                    }
                }
                break;

            default:
                Log::info('Wave Webhook: événement non géré', ['type' => $event]);
        }

        return response()->json(['status' => 'ok']);
    }
}
