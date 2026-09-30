<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Enregistre les actions du back-office dans le journal d'activité.
 * La description est une phrase française prête à afficher, sans donnée personnelle
 * au-delà du nom de l'objet concerné.
 */
class ActivityLogger
{
    private static bool $enabled = true;

    public static function log(string $action, ?Model $subject, string $description): void
    {
        if (!self::$enabled) {
            return;
        }

        // Clé numérique → subject_id ; clé texte (ex. paramètre « parish.phone ») → subject_key
        $key = $subject?->getKey();
        $numeric = is_int($key) || (is_string($key) && ctype_digit($key));

        try {
            ActivityLog::create([
                'user_id'      => self::currentUserId(),
                'action'       => $action,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id'   => $numeric ? (int) $key : null,
                'subject_key'  => $key !== null && !$numeric ? mb_substr((string) $key, 0, 100) : null,
                'description'  => mb_substr($description, 0, 500),
                'created_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            // Le journal ne doit jamais bloquer l'action elle-même
            Log::warning('Journal d\'activité : écriture impossible', ['message' => $e->getMessage()]);
        }
    }

    /** Exécute un traitement sans journalisation (seeders, imports techniques). */
    public static function withoutLogging(callable $callback): mixed
    {
        $previous = self::$enabled;
        self::$enabled = false;

        try {
            return $callback();
        } finally {
            self::$enabled = $previous;
        }
    }

    private static function currentUserId(): ?int
    {
        try {
            $user = auth()->user() ?? auth('sanctum')->user();
        } catch (\Throwable $e) {
            $user = null;
        }

        return $user?->getKey();
    }
}
