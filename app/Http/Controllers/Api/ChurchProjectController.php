<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\StoresPublicImages;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChurchProject\UpdateRequest;
use App\Http\Resources\ChurchProjectResource;
use App\Models\ChurchProject;
use Illuminate\Http\Request;

class ChurchProjectController extends Controller
{
    use StoresPublicImages;

    /**
     * Projet « Nouvelle église » (public), avec montant collecté et avancement calculés.
     */
    public function show()
    {
        return new ChurchProjectResource(ChurchProject::current());
    }

    /**
     * Mise à jour (admin). JSON ou multipart (POST + _method=PUT) avec `image`.
     */
    public function update(UpdateRequest $request)
    {
        $project = ChurchProject::current();
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $this->deletePublicImage($project->image);
            $data['image'] = $this->storePublicImage($request->file('image'), 'church');
        } else {
            unset($data['image']);
        }

        if (array_key_exists('phases', $data)) {
            $data['phases'] = array_values(array_map(
                fn ($phase) => ['name' => $phase['name'], 'status' => $phase['status']],
                $data['phases']
            ));
        }

        $project->update($data);

        return new ChurchProjectResource($project->fresh());
    }

    /**
     * Ajout d'une image à la galerie (admin). Multipart : `image`.
     */
    public function addGalleryImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $project = ChurchProject::current();
        $gallery = $project->gallery ?? [];
        $gallery[] = $this->storePublicImage($request->file('image'), 'church');

        $project->update(['gallery' => array_values($gallery)]);

        return new ChurchProjectResource($project->fresh());
    }

    /**
     * Retrait d'une image de la galerie par son index (admin).
     */
    public function removeGalleryImage(int $index)
    {
        $project = ChurchProject::current();
        $gallery = $project->gallery ?? [];

        if (!array_key_exists($index, $gallery)) {
            return response()->json(['message' => 'Image introuvable dans la galerie.'], 404);
        }

        $this->deletePublicImage($gallery[$index]);
        unset($gallery[$index]);

        $project->update(['gallery' => array_values($gallery)]);

        return new ChurchProjectResource($project->fresh());
    }
}
