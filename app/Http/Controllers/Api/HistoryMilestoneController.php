<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesPublicationScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\HistoryMilestone\StoreRequest;
use App\Http\Requests\HistoryMilestone\UpdateRequest;
use App\Http\Resources\HistoryMilestoneResource;
use App\Repositories\Contracts\HistoryMilestoneRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HistoryMilestoneController extends Controller
{
    use ResolvesPublicationScope;

    protected HistoryMilestoneRepositoryInterface $repo;

    public function __construct(HistoryMilestoneRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Liste complète (non paginée) des jalons de l'histoire.
     * Public : publiés uniquement. Admin + ?all=1 : tous les statuts.
     */
    public function index(Request $request)
    {
        $items = $this->applyPublicationScope($this->repo->query(), $request)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return HistoryMilestoneResource::collection($items);
    }

    public function store(StoreRequest $request)
    {
        $data = $request->validated();

        $item = $this->repo->create($data);

        return (new HistoryMilestoneResource($item->fresh()))
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

        return new HistoryMilestoneResource($item);
    }

    public function update(UpdateRequest $request, string $id)
    {
        $item = $this->repo->find($id);
        $data = $request->validated();

        $item = $this->repo->update($item->id, $data);

        return new HistoryMilestoneResource($item);
    }

    public function destroy(string $id)
    {
        $this->repo->find($id);
        $this->repo->delete($id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Jalon supprimé',
        ], Response::HTTP_NO_CONTENT);
    }
}
