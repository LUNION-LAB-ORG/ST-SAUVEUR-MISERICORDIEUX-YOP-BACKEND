<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mediation\StoreRequest;
use App\Http\Requests\Mediation\UpdateRequest;
use App\Http\Resources\MediationResource;
use App\Repositories\Contracts\MediationRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MediationController extends Controller
{
    protected MediationRepositoryInterface $repo;

    public function __construct(MediationRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    /**
     * List mediations (paginated)
     */
    public function index(Request $request)
    {
        $conditions = [];

        // Filters
        if ($request->filled('title')) {
            $conditions[] = ['title', 'LIKE', '%' . $request->title . '%'];
        }

        if ($request->filled('author')) {
            $conditions[] = ['author', 'LIKE', '%' . $request->author . '%'];
        }

        if ($request->filled('category')) {
            $conditions[] = ['category', '=', $request->category];
        }

        if ($request->filled('status')) {
            $conditions[] = ['mediation_status', '=', $request->status];
        }

        if ($request->filled('from')) {
            $conditions[] = ['date_at', '>=', $request->from];
        }

        if ($request->filled('to')) {
            $conditions[] = ['date_at', '<=', $request->to];
        }

        $mediations = $this->repo->paginate(
            with: [],
            page: (int) $request->input('per_page', 15),
            conditions: $conditions,
            skip: (int) $request->input('skip', 0),
            orderBy: $request->input('sort_by', 'id'),
            direction: $request->input('sort_dir', 'desc'),
        );

        return MediationResource::collection($mediations);
    }

    /**
     * Store a new mediation
     */
    public function store(StoreRequest $request)
    {
        $mediation = $this->repo->create($request->validated());

        return (new MediationResource($mediation))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show mediation details
     */
    public function show(Request $request, string $id)
    {
        $mediation = $this->repo->find($id);

        // Incrémenter les vues sur consultation publique (non-authentifiée)
        if ($mediation && !$request->user()) {
            $mediation->increment('views');
        }

        return new MediationResource($mediation);
    }

    /**
     * Update mediation
     */
    public function update(UpdateRequest $request, string $id)
    {
        $mediation = $this->repo->update($id, $request->validated());
        return new MediationResource($mediation);
    }

    /**
     * Soft delete mediation
     */
    public function destroy(string $id)
    {
        $this->repo->delete($id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Médiation supprimée'
        ], Response::HTTP_NO_CONTENT);
    }
}
