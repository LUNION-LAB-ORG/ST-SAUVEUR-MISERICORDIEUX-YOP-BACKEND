<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use App\Http\Resources\NewsResource;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\News\StoreRequest;
use App\Http\Requests\News\UpdateRequest;
use App\Repositories\Contracts\NewRepositoryInterface;

class NewsController extends Controller
{
    protected NewRepositoryInterface $repo;

    public function __construct(NewRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    /**
     * List news (paginated)
     */
    public function index(Request $request)
    {
        $conditions = [];

        if ($request->filled('title')) {
            $conditions[] = ['title', 'LIKE', '%' . $request->title . '%'];
        }

        if ($request->filled('location')) {
            $conditions[] = ['location', 'LIKE', '%' . $request->location . '%'];
        }

        if ($request->filled('status')) {
            $conditions[] = ['new_status', '=', $request->status];
        }

        if ($request->filled('from')) {
            $conditions[] = ['published_at', '>=', $request->from];
        }

        if ($request->filled('to')) {
            $conditions[] = ['published_at', '<=', $request->to];
        }

        $news = $this->repo->paginate(
            with: [],
            page: (int) $request->input('per_page', 15),
            conditions: $conditions,
            skip: (int) $request->input('skip', 0),
            orderBy: $request->input('sort_by', 'id'),
            direction: $request->input('sort_dir', 'desc'),
        );

        return NewsResource::collection($news);
    }

    /**
     * Store a new news
     */
    public function store(StoreRequest $request)
    {
        $data = $request->validated();

        // Gérer l'upload d'image
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            
            // Option 1: Stocker dans storage/app/public/events
            $path = $image->store('news', 'public');

            // Mettre à jour le champ image avec le chemin relatif
            $data['image'] = 'storage/' . $path;
        }

        $newsItem = $this->repo->create($data);

        return (new NewsResource($newsItem))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show news details
     */
    public function show(Request $request, string $id)
    {
        $news = $this->repo->find($id);

        // Incrémenter le compteur de vues (sauf si l'utilisateur est authentifié via admin)
        // Pour le public non-authentifié, on incrémente à chaque consultation.
        if ($news && !$request->user()) {
            $news->increment('views');
        }

        return new NewsResource($news);
    }

    /**
     * Update news
     */
    public function update(UpdateRequest $request, string $id)
    {
        $data = $request->validated();

        $news = $this->repo->find($id); // récupérer l'événement existant

        // Gestion de l'image
        if ($request->hasFile('image')) {
            $image = $request->file('image');

            // Supprimer l'ancienne image si elle existe
            if ($news->image && Storage::disk('public')->exists($news->image)) {
                Storage::disk('public')->delete($news->image);
            }

            // Stocker la nouvelle image
            $path = $image->store('news', 'public');
            $data['image'] = 'storage/' . $path;
        }

        $newsItem = $this->repo->update($id, $data);
        return new NewsResource($newsItem);
    }

    /**
     * Soft delete news
     */
    public function destroy(string $id)
    {
        $this->repo->delete($id);

        return response()->json([
            'status'  => 'success',
            'message' => 'News supprimée'
        ], Response::HTTP_NO_CONTENT);
    }
}
