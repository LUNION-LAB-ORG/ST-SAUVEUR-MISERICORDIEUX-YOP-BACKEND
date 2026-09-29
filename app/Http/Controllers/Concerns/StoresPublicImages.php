<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Upload / suppression d'images sur le disque `public`.
 * Le chemin enregistré suit la convention existante : « storage/<dossier>/<fichier> ».
 */
trait StoresPublicImages
{
    protected function storePublicImage(UploadedFile $file, string $directory): string
    {
        return 'storage/' . $file->store($directory, 'public');
    }

    protected function deletePublicImage(?string $path): void
    {
        if (!$path || str_starts_with($path, 'http')) {
            return;
        }

        $relative = preg_replace('#^/?storage/#', '', $path);
        if (Storage::disk('public')->exists($relative)) {
            Storage::disk('public')->delete($relative);
        }
    }
}
