<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesPublicationScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Council\StoreRequest;
use App\Http\Requests\Council\UpdateRequest;
use App\Http\Resources\CouncilResource;
use App\Repositories\Contracts\CouncilRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CouncilController extends Controller
{
    use ResolvesPublicationScope;

    protected CouncilRepositoryInterface $repo;

    public function __construct(CouncilRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Liste complète (non paginée) des conseils et services.
     * Public : publiés uniquement. Admin + ?all=1 : tous les statuts.
     */
    public function index(Request $request)
    {
        $items = $this->applyPublicationScope($this->repo->query(), $request)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return CouncilResource::collection($items);
    }

    public function store(StoreRequest $request)
    {
        $data = $request->validated();

        $item = $this->repo->create($data);

        return (new CouncilResource($item->fresh()))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Détail : 404 si non publié (sauf admin authentifié).
     */
    public function show(string $id)
    {
        $item = $this->repo->find($id);
        $this->ensureVisible($item);

        return new CouncilResource($item);
    }

    public function update(UpdateRequest $request, string $id)
    {
        $item = $this->repo->find($id);
        $data = $request->validated();

        $item = $this->repo->update($item->id, $data);

        return new CouncilResource($item);
    }

    public function destroy(string $id)
    {
        $this->repo->find($id);
        $this->repo->delete($id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Conseil supprimé',
        ], Response::HTTP_NO_CONTENT);
    }
}
