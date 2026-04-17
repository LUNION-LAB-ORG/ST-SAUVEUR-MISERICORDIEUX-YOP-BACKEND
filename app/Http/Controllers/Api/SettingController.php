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
            $grouped[$s->group][] = [
                'key'   => $s->key,
                'value' => $this->exposedValue($s),
                'type'  => $s->type,
                'label' => $s->label,
            ];
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
            if ($setting && $setting->type === 'image' && $value) {
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

        foreach ($request->input('settings', []) as $item) {
            $setting = Setting::find($item['key']);
            if ($setting) {
                $setting->value = $item['value'] ?? null;
                $setting->save();
            }
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

        return response()->json([
            'status' => 'success',
            'data' => [
                'key' => $setting->key,
                'value' => env('APP_URL') . '/' . ltrim($setting->value, '/'),
            ],
        ]);
    }

    /**
     * Pour les settings de type image, renvoyer l'URL absolue.
     */
    private function exposedValue(Setting $s): ?string
    {
        if ($s->type === 'image' && $s->value) {
            return str_starts_with($s->value, 'http')
                ? $s->value
                : rtrim(env('APP_URL', ''), '/') . '/' . ltrim($s->value, '/');
        }
        return $s->value;
    }
}
