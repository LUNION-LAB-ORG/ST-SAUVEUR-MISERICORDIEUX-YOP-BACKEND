<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesPublicationScope;
use App\Http\Controllers\Concerns\StoresPublicImages;
use App\Http\Controllers\Concerns\TogglesDeviceLikes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Publication\StoreRequest;
use App\Http\Requests\Publication\UpdateRequest;
use App\Http\Resources\PublicationResource;
use App\Models\Publication;
use App\Models\PublicationLike;
use App\Repositories\Contracts\PublicationRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PublicationController extends Controller
{
    use ResolvesPublicationScope, StoresPublicImages, TogglesDeviceLikes;

    protected PublicationRepositoryInterface $repo;

    public function __construct(PublicationRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Publications (paginé, 9 par page par défaut, 50 max).
     * Public : publiées dont la date de publication est atteinte. Admin + ?all=1 : toutes.
     * Filtres : ?type=photo|video|text, ?featured=1, ?exclude=slug, ?category=
     */
    public function index(Request $request)
    {
        $request->validate(['type' => 'nullable|in:photo,video,text']);

        $query = $this->withCommentsCount($this->repo->query());

        if (!$this->wantsAllStatuses($request)) {
            $query->visible();
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }
        if ($request->filled('exclude')) {
            $query->where('slug', '!=', $request->input('exclude'));
        }

        if ($request->boolean('featured')) {
            $query->where('is_featured', true)->orderByDesc('is_featured');
        }

        $query->orderByDesc('published_at')->orderByDesc('id');

        $perPage = max(1, min(50, (int) $request->input('per_page', 9)));

        return PublicationResource::collection($query->paginate($perPage));
    }

    /** Détail par id ou slug. */
    public function show(string $id)
    {
        return new PublicationResource($this->findVisible($id));
    }

    public function store(StoreRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('cover')) {
            $data['cover'] = $this->storePublicImage($request->file('cover'), 'publications');
        } else {
            unset($data['cover']);
        }

        $publication = $this->repo->create($data);

        return (new PublicationResource($publication->fresh()))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateRequest $request, string $id)
    {
        $publication = $this->repo->find($id);
        $data = $request->validated();

        if ($request->hasFile('cover')) {
            $this->deletePublicImage($publication->cover);
            $data['cover'] = $this->storePublicImage($request->file('cover'), 'publications');
        } else {
            unset($data['cover']);
        }

        return new PublicationResource($this->repo->update($publication->id, $data));
    }

    public function destroy(string $id)
    {
        $this->repo->find($id);
        $this->repo->delete($id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Publication supprimée',
        ], Response::HTTP_NO_CONTENT);
    }

    /** Ajout d'une photo à la galerie (admin). Multipart : `image`. */
    public function addGalleryImage(Request $request, string $id)
    {
        $request->validate(['image' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120']);

        $publication = $this->repo->find($id);
        $gallery = $publication->gallery ?? [];
        $gallery[] = $this->storePublicImage($request->file('image'), 'publications');
        $publication->update(['gallery' => array_values($gallery)]);

        return new PublicationResource($publication->fresh());
    }

    /** Retrait d'une photo de la galerie par son index (admin). */
    public function removeGalleryImage(string $id, int $index)
    {
        $publication = $this->repo->find($id);
        $gallery = $publication->gallery ?? [];

        if (!array_key_exists($index, $gallery)) {
            return response()->json(['message' => 'Image introuvable dans la galerie.'], 404);
        }

        $this->deletePublicImage($gallery[$index]);
        unset($gallery[$index]);
        $publication->update(['gallery' => array_values($gallery)]);

        return new PublicationResource($publication->fresh());
    }

    /** « J'aime » à bascule, un par appareil. Body : { device_id } */
    public function like(Request $request, string $id): JsonResponse
    {
        $request->validate(['device_id' => 'required|string|max:100']);

        $publication = $this->findVisible($id);

        return response()->json([
            'data' => $this->toggleLike(PublicationLike::class, 'publication_id', $publication, $request->input('device_id')),
        ]);
    }

    /** État du « j'aime » pour un appareil. ?device_id= */
    public function likeStatus(Request $request, string $id): JsonResponse
    {
        $request->validate(['device_id' => 'required|string|max:100']);

        $publication = $this->findVisible($id);

        return response()->json([
            'data' => $this->deviceLikeStatus(PublicationLike::class, 'publication_id', $publication, $request->input('device_id')),
        ]);
    }

    private function withCommentsCount(Builder $query): Builder
    {
        return $query->withCount(['comments as published_comments_count' => fn ($q) => $q->where('status', 'published')]);
    }

    /** Publication visible par id ou slug (404 sinon, sauf admin authentifié). */
    private function findVisible(string $idOrSlug): Publication
    {
        $publication = Publication::findByIdOrSlug($idOrSlug);
        abort_if(!$publication || (!$this->isAdmin() && !$publication->isVisible()), 404);

        return $publication;
    }
}
