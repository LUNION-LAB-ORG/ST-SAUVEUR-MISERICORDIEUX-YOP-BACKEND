<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pastor\StoreRequest;
use App\Http\Requests\Pastor\UpdateRequest;
use App\Http\Resources\PastorResource;
use App\Repositories\Contracts\PastorRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class PastorController extends Controller
{
    protected PastorRepositoryInterface $repo;

    public function __construct(PastorRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    /**
     * List pastors (paginated)
     */
    public function index(Request $request)
    {
        $conditions = [];

        if ($request->filled('fullname')) {
            $conditions[] = ['fullname', 'LIKE', '%' . $request->fullname . '%'];
        }

        if ($request->filled('started_at_from')) {
            $conditions[] = ['started_at', '>=', $request->started_at_from];
        }

        if ($request->filled('started_at_to')) {
            $conditions[] = ['started_at', '<=', $request->started_at_to];
        }

        if ($request->filled('ended_at_from')) {
            $conditions[] = ['ended_at', '>=', $request->ended_at_from];
        }

        if ($request->filled('ended_at_to')) {
            $conditions[] = ['ended_at', '<=', $request->ended_at_to];
        }

        $pastors = $this->repo->paginate(
            with: [],
            page: (int) $request->input('per_page', 15),
            conditions: $conditions,
            skip: (int) $request->input('skip', 0),
            orderBy: $request->input('sort_by', 'id'),
            direction: $request->input('sort_dir', 'desc'),
        );

        return PastorResource::collection($pastors);
    }

    /**
     * Store a new pastor
     */
    public function store(StoreRequest $request)
    {
        $data = $request->validated();
        $data['description'] = $data['description'] ?? '';

        // Gérer l'upload d'image
        if ($request->hasFile('photo')) {
            $photo = $request->file('photo');
            
            // Option 1: Stocker dans storage/app/public/events
            $path = $photo->store('pastors', 'public');

            // Mettre à jour le champ image avec le chemin relatif
            $data['photo'] = 'storage/' . $path;
        }

        $pastor = $this->repo->create($data);

        return (new PastorResource($pastor))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show pastor details
     */
    public function show(string $id)
    {
        return new PastorResource($this->repo->find($id));
    }

    /**
     * Update pastor
     */
    public function update(UpdateRequest $request, string $id)
    {
        $data = $request->validated();
        if (array_key_exists('description', $data)) {
            $data['description'] = $data['description'] ?? '';
        }

        $pastor = $this->repo->find($id); // récupérer l'événement existant

        // Gestion de l'image
        if ($request->hasFile('photo')) {
            $photo = $request->file('photo');

            // Supprimer l'ancienne image si elle existe
            $ancienne = $pastor->photo ? preg_replace('#^storage/#', '', $pastor->photo) : null;
            if ($ancienne && Storage::disk('public')->exists($ancienne)) {
                Storage::disk('public')->delete($ancienne);
            }

            // Stocker la nouvelle image
            $path = $photo->store('pastors', 'public');
            $data['photo'] = 'storage/' . $path;
        }

        $pastor = $this->repo->update($id, $data);
        return new PastorResource($pastor);
    }

    /**
     * Soft delete pastor
     */
    public function destroy(string $id)
    {
        $this->repo->delete($id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Pastor supprimé'
        ], Response::HTTP_NO_CONTENT);
    }
}
