<?php

namespace App\Support;

/**
 * Construit l'URL absolue d'un fichier stocké (même logique que PastorResource).
 */
class MediaUrl
{
    public static function absolute(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return rtrim((string) env('APP_URL'), '/') . '/' . ltrim($path, '/');
    }
}
