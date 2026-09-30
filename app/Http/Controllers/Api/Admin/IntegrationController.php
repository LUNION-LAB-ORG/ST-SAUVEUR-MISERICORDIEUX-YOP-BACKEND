<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\LiturgyDay;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class IntegrationController extends Controller
{
    /**
     * État des intégrations (admin). Aucune clé n'est jamais renvoyée.
     */
    public function index(): JsonResponse
    {
        return response()->json(['data' => [
            $this->aelf(),
            ['key' => 'whatsapp', 'status' => 'not_configured', 'detail' => 'Aucun service d’envoi WhatsApp n’est encore branché.'],
            ['key' => 'youtube', 'status' => 'connected', 'detail' => 'Vidéos intégrées depuis leur lien YouTube.'],
            ['key' => 'maps', 'status' => 'connected', 'detail' => 'Carte intégrée à la page Contact.'],
            $this->payment(),
        ]]);
    }

    private function aelf(): array
    {
        $zone = config('services.aelf.zone');
        if (!config('services.aelf.base_url') || !$zone) {
            return ['key' => 'aelf', 'status' => 'not_configured', 'detail' => 'Adresse de l’API AELF non configurée.'];
        }

        $last = LiturgyDay::max('imported_at');
        if (!$last) {
            return ['key' => 'aelf', 'status' => 'error', 'detail' => "Zone : {$zone} · aucun import réussi"];
        }

        $lastAt = Carbon::parse($last);

        return [
            'key'    => 'aelf',
            'status' => $lastAt->gte(now()->subHours(48)) ? 'connected' : 'error',
            'detail' => "Zone : {$zone} · dernier import " . $lastAt->copy()->setTimezone('Africa/Abidjan')->format('d/m/Y H:i'),
        ];
    }

    private function payment(): array
    {
        $aggregator = trim((string) Setting::rawValue('payment.aggregator'));
        $hasKey = filled(Setting::rawValue('payment.api_key'));

        if ($aggregator === '' && !$hasKey) {
            return ['key' => 'payment', 'status' => 'not_configured', 'detail' => 'Aucun agrégateur de paiement configuré.'];
        }
        if ($aggregator === '' || !$hasKey) {
            return ['key' => 'payment', 'status' => 'error', 'detail' => 'Configuration incomplète : agrégateur et clé d’API requis.'];
        }

        return ['key' => 'payment', 'status' => 'connected', 'detail' => 'Agrégateur : ' . $aggregator];
    }
}
