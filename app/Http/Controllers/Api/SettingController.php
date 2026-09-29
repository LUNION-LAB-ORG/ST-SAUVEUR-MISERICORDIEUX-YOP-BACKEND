<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    /**
     * Liste publique : renvoie toutes les settings groupées.
     * Structure : { group: [ { key, value, type, label } ] }
     */
    public function index(): JsonResponse
    {
        $all = Setting::query()->orderBy('group')->orderBy('key')->get();

        $grouped = [];
        foreach ($all as $s) {
            $grouped[$s->group] ??= [];
            $item = [
                'key'   => $s->key,
                'value' => $this->exposedValue($s),
                'type'  => $s->type,
                'label' => $s->label,
            ];
            // Paramètre secret : jamais renvoyé, seulement l'indication qu'il est renseigné
            if ($s->isSecret()) {
                $item['is_set'] = filled($s->value);
            }
            $grouped[$s->group][] = $item;
        }

        return response()->json(['data' => $grouped]);
    }

    /**
     * Map plate { key: value } pour consommation rapide (footer, header).
     * Public (cache 5 min via Setting::allAsMap).
     */
    public function map(): JsonResponse
    {
        $map = Setting::allAsMap();

        // Transformer les images relatives → absolues
        $appUrl = rtrim(env('APP_URL', ''), '/');
        foreach ($map as $key => $value) {
            $setting = Setting::find($key);
            if ($setting && in_array($setting->type, ['image', 'file'], true) && $value) {
                $map[$key] = str_starts_with($value, 'http') ? $value : $appUrl . '/' . ltrim($value, '/');
            }
        }

        return response()->json(['data' => $map]);
    }

    /**
     * Upsert en masse : body = [ { key, value } ]
     * Admin uniquement.
     */
    public function updateMany(Request $request): JsonResponse
    {
        $request->validate([
            'settings' => 'required|array|min:1',
            'settings.*.key' => 'required|string|max:100',
            'settings.*.value' => 'nullable|string',
        ]);

        $items = $request->input('settings', []);
        $settings = Setting::whereIn('key', array_column($items, 'key'))->get()->keyBy('key');

        // Contrôle par clé (tout ou rien) : payment.* / whatsapp.* / secrets → admin, etc.
        foreach ($items as $item) {
            $setting = $settings->get($item['key']);
            if ($setting && !$request->user()->hasRole(...Setting::editorRoles($setting->key, $setting->type))) {
                return response()->json(['error' => 'Accès refusé pour votre rôle.'], 403);
            }
            if ($setting && $setting->type === 'json' && filled($item['value'] ?? null)) {
                json_decode($item['value']);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return response()->json([
                        'message' => 'Valeur JSON invalide pour « ' . ($setting->label ?: $setting->key) . ' ».',
                        'errors'  => ['settings' => ['Valeur JSON invalide pour ' . $setting->key . '.']],
                    ], 422);
                }
            }
        }

        foreach ($items as $item) {
            $setting = $settings->get($item['key']);
            if (!$setting) {
                continue;
            }

            $value = $item['value'] ?? null;

            // Secret : une valeur vide ne l'efface pas (le formulaire reçoit toujours null)
            if ($setting->isSecret() && blank($value)) {
                continue;
            }
            if ($setting->type === 'boolean' && $value !== null) {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
            }

            $setting->value = $value;
            $setting->save();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Paramètres mis à jour',
            'data' => Setting::allAsMap(),
        ]);
    }

    /**
     * Upload d'une image (logo, hero).
     * Body: multipart { key, image }
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'key' => 'required|string|max:100',
            'image' => 'required|image|mimes:jpg,jpeg,png,webp,svg|max:5120',
        ]);

        $setting = Setting::find($request->input('key'));
        if (!$setting || $setting->type !== 'image') {
            return response()->json(['error' => 'Clé invalide pour une image'], 422);
        }
        if (!$request->user()->hasRole(...Setting::editorRoles($setting->key, $setting->type))) {
            return response()->json(['error' => 'Accès refusé pour votre rôle.'], 403);
        }

        // Supprimer ancienne image
        if ($setting->value) {
            $old = preg_replace('#^storage/#', '', $setting->value);
            if (Storage::disk('public')->exists($old)) {
                Storage::disk('public')->delete($old);
            }
        }

        $path = $request->file('image')->store('settings', 'public');
        $setting->value = 'storage/' . $path;
        $setting->save();

        // Logo personnalisé : l'accueil bascule sur le logo téléversé
        if ($setting->key === 'images.logo') {
            $flag = Setting::find('images.logo_custom');
            if ($flag && $flag->value !== '1') {
                $flag->value = '1';
                $flag->save();
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'key' => $setting->key,
                'value' => env('APP_URL') . '/' . ltrim($setting->value, '/'),
            ],
        ]);
    }

    /**
     * Upload d'un document (ex. feuille d'annonces PDF).
     * Body: multipart { key, file } — uniquement pour une clé de type « file ».
     */
    public function uploadFile(Request $request): JsonResponse
    {
        $request->validate([
            'key'  => 'required|string|max:100',
            'file' => 'required|file|mimes:pdf|max:10240',
        ]);

        $setting = Setting::find($request->input('key'));
        if (!$setting || $setting->type !== 'file') {
            return response()->json(['error' => 'Clé invalide pour un fichier'], 422);
        }
        if (!$request->user()->hasRole(...Setting::editorRoles($setting->key, $setting->type))) {
            return response()->json(['error' => 'Accès refusé pour votre rôle.'], 403);
        }

        // Supprimer l'ancien fichier
        if ($setting->value) {
            $old = preg_replace('#^storage/#', '', $setting->value);
            if (Storage::disk('public')->exists($old)) {
                Storage::disk('public')->delete($old);
            }
        }

        $path = $request->file('file')->store('documents', 'public');
        $setting->value = 'storage/' . $path;
        $setting->save();

        return response()->json([
            'status' => 'success',
            'data' => [
                'key' => $setting->key,
                'value' => rtrim(env('APP_URL', ''), '/') . '/' . ltrim($setting->value, '/'),
            ],
        ]);
    }

    /**
     * Pour les settings de type image ou fichier, renvoyer l'URL absolue.
     */
    private function exposedValue(Setting $s): ?string
    {
        if ($s->isSecret()) {
            return null;
        }

        if (in_array($s->type, ['image', 'file'], true) && $s->value) {
            return str_starts_with($s->value, 'http')
                ? $s->value
                : rtrim(env('APP_URL', ''), '/') . '/' . ltrim($s->value, '/');
        }
        return $s->value;
    }
}
