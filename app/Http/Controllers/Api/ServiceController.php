<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesPublicationScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Service\StoreRequest;
use App\Http\Requests\Service\UpdateRequest;
use App\Http\Resources\ServiceResource;
use App\Repositories\Contracts\ServiceRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ServiceController extends Controller
{
    use ResolvesPublicationScope;

    /** Colonnes autorisées pour ?sort_by= (compatibilité avec l'existant). */
    private const SORTABLE = ['id', 'title', 'sort_order', 'category', 'status', 'created_at', 'updated_at'];

    protected ServiceRepositoryInterface $repo;

    public function __construct(ServiceRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Mouvements et groupes (paginé, per_page ≤ 100).
     * Public : publiés uniquement. Admin authentifié + ?all=1 : tous les statuts.
     * Tri par défaut : sort_order ASC, id ASC ; ?sort_by=&sort_dir= restent acceptés.
     */
    public function index(Request $request)
    {
        $query = $this->applyPublicationScope($this->repo->query(), $request);

        if ($request->filled('title')) {
            $query->where('title', 'LIKE', '%' . $request->title . '%');
        }

        if ($request->filled('description')) {
            $query->where('description', 'LIKE', '%' . $request->description . '%');
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('sort_by') && in_array($request->sort_by, self::SORTABLE, true)) {
            $direction = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
            $query->orderBy($request->sort_by, $direction);
        } else {
            $query->orderBy('sort_order')->orderBy('id');
        }

        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));

        return ServiceResource::collection($query->paginate($perPage));
    }

    public function store(StoreRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('services', 'public');
            $data['image'] = 'storage/' . $path;
        }

        $service = $this->repo->create($data);

        return (new ServiceResource($service))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Détail : 404 si non publié (sauf admin authentifié).
     */
    public function show(string $id)
    {
        $service = $this->repo->find($id);
        $this->ensureVisible($service);

        return new ServiceResource($service);
    }

    public function update(UpdateRequest $request, string $id)
    {
        $data = $request->validated();
        $existing = $this->repo->find($id);

        if ($request->hasFile('image')) {
            if ($existing && $existing->image && Storage::disk('public')->exists(preg_replace('#^storage/#', '', $existing->image))) {
                Storage::disk('public')->delete(preg_replace('#^storage/#', '', $existing->image));
            }
            $path = $request->file('image')->store('services', 'public');
            $data['image'] = 'storage/' . $path;
        }

        $service = $this->repo->update($id, $data);
        return new ServiceResource($service);
    }

    public function destroy(string $id)
    {
        $this->repo->delete($id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Service supprimé'
        ], Response::HTTP_NO_CONTENT);
    }
}
