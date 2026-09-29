<?php

namespace App\Services;

use App\Models\Mess;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Accès à l'API Wave Checkout (sessions de paiement).
 * Utilisé par WaveCheckoutController (routes /wave/*) et par les demandes de messe.
 */
class WaveCheckoutService
{
    public const BASE_URL = 'https://api.wave.com';

    public function apiKey(): ?string
    {
        return config('services.wave.api_key') ?: null;
    }

    public function isConfigured(): bool
    {
        return (bool) $this->apiKey();
    }

    public function frontendUrl(): string
    {
        return rtrim(config('services.wave.frontend_url', 'https://paroisse-st-sauveur-mis-ricordieux.vercel.app'), '/');
    }

    /**
     * Crée une session de paiement (montant en FCFA, entier). Les exceptions réseau sont propagées.
     */
    public function createSession(int|float $amount, string $clientReference, string $successUrl, string $errorUrl): Response
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey(),
            'Content-Type'  => 'application/json',
        ])->post(self::BASE_URL . '/v1/checkout/sessions', [
            'amount'           => strval(intval($amount)),
            'currency'         => 'XOF',
            'success_url'      => $successUrl,
            'error_url'        => $errorUrl,
            'client_reference' => $clientReference,
        ]);
    }

    /** Récupère une session de paiement. */
    public function getSession(string $sessionId): Response
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey(),
        ])->get(self::BASE_URL . '/v1/checkout/sessions/' . $sessionId);
    }

    /** Demande de messe en ligne (client_reference = numéro SSM-AAAA-NNNN) ? */
    public static function isMassRequestReference(?string $clientReference): bool
    {
        return (bool) ($clientReference && preg_match('/^SSM-\d{4}-\d+$/', $clientReference));
    }

    /** Paiement confirmé : la demande de messe passe à « succeeded » et est acceptée. */
    public function markMassRequestPaid(string $number, ?string $sessionId = null): void
    {
        $mess = Mess::where('number', $number)->first();
        if ($mess && $mess->payment_status !== 'succeeded') {
            $mess->update([
                'request_status'   => 'accepted',
                'payment_status'   => 'succeeded',
                'wave_checkout_id' => $sessionId ?: $mess->wave_checkout_id,
            ]);
        }
    }

    /** Paiement abandonné ou échoué : la demande est annulée (les créneaux sont libérés). */
    public function markMassRequestFailed(string $number): void
    {
        $mess = Mess::where('number', $number)->first();
        if ($mess && $mess->payment_status !== 'succeeded') {
            $mess->update([
                'request_status' => 'canceled',
                'payment_status' => 'failed',
            ]);
        }
    }
}
